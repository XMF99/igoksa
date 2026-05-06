<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Employee;
use App\Models\Sahab\EmployeeDocument;
use App\Models\Sahab\BusinessDocument;
use App\Models\Sahab\Attendance;
use App\Models\Sahab\Leave;
use App\Models\Sahab\Outlet;
use App\Models\Sahab\SurpriseTest;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HrController — الموارد البشريّة (مستوحى من دفترة)
 * --------------------------------------------------------------------
 *  - الموظّفون CRUD
 *  - الوثائق الرسميّة + التنبيهات (60/30/7 يوم)
 *  - الحضور بـ GPS + الاختبار المفاجئ
 *  - الإجازات والموافقات
 *  - وثائق المحلّ
 * --------------------------------------------------------------------
 */
class HrController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsapp,
    ) {
        $this->middleware('auth:sanctum');
    }

    // ============================================================
    //  EMPLOYEES
    // ============================================================

    public function employees(Request $request)
    {
        $employees = Employee::with('outlet', 'documents')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->outlet_id, fn($q) => $q->where('outlet_id', $request->outlet_id))
            ->orderBy('full_name')
            ->paginate(20);

        return response()->json($employees);
    }

    public function createEmployee(Request $request)
    {
        $validated = $request->validate([
            'outlet_id'         => 'required|exists:sahab_outlets,id',
            'employee_code'     => 'required|string|max:20',
            'full_name'         => 'required|string|max:120',
            'national_id'       => 'nullable|string|max:20',
            'mobile'            => 'required|string|max:20',
            'email'             => 'nullable|email',
            'birth_date'        => 'nullable|date',
            'gender'            => 'nullable|in:male,female',
            'nationality'       => 'nullable|string|max:30',
            'position'          => 'required|string|max:60',
            'department'        => 'nullable|string|max:60',
            'hire_date'         => 'required|date',
            'contract_end_date' => 'nullable|date|after:hire_date',
            'contract_type'     => 'required|in:permanent,temporary,part_time,intern',
            'basic_salary'      => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transport_allowance'=> 'nullable|numeric|min:0',
            'bank_name'         => 'nullable|string|max:60',
            'iban'              => 'nullable|string|max:30',
            'shift_start'       => 'nullable',
            'shift_end'         => 'nullable',
            'gps_required'      => 'boolean',
        ]);

        $employee = Employee::create($validated);
        return response()->json(['success' => true, 'employee' => $employee], 201);
    }

    // ============================================================
    //  EMPLOYEE DOCUMENTS (الإقامة، الجواز، التأمين...)
    // ============================================================

    public function uploadEmployeeDocument(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'document_type'    => 'required|in:iqama,work_permit,passport,health_insurance,driver_license,gosi,medical_check,training_cert,food_handler,contract,other',
            'document_number'  => 'nullable|string|max:60',
            'issue_date'       => 'nullable|date',
            'expiry_date'      => 'nullable|date|after:issue_date',
            'issuing_authority'=> 'nullable|string|max:120',
            'file'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'notes'            => 'nullable|string',
        ]);

        if ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')
                ->store("documents/employees/{$employee->id}", 'public');
        }

        $document = $employee->documents()->create($validated);
        return response()->json(['success' => true, 'document' => $document], 201);
    }

    /**
     * جلب الوثائق المنتهية أو القاربة على الانتهاء
     * (لاستخدامها في Cron job يومياً)
     */
    public function expiringDocuments(Request $request)
    {
        $days = (int) $request->input('days', 30);

        $employeeDocs = EmployeeDocument::with('employee')
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->get();

        $businessDocs = BusinessDocument::with('outlet')
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now())
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'employee_documents' => $employeeDocs,
            'business_documents' => $businessDocs,
            'expiring_within_days' => $days,
        ]);
    }

    /**
     * إرسال تنبيهات الانتهاء (يُستدعى من Cron)
     */
    public function sendExpiryAlerts()
    {
        $sent = 0;
        $today = now()->startOfDay();

        // وثائق الموظّفين
        foreach (EmployeeDocument::with('employee.tenant')->whereNotNull('expiry_date')->get() as $doc) {
            $daysLeft = $doc->daysUntilExpiry();

            if (in_array($daysLeft, [60, 30, 7])) {
                $field = "alert_{$daysLeft}_days";
                if ($doc->{$field}) {
                    $tenant = $doc->employee?->tenant;
                    if ($tenant && $tenant->mobile) {
                        $this->whatsapp->sendDocumentExpiryAlert(
                            $tenant->mobile,
                            $doc->employee->full_name,
                            $this->getDocumentTypeLabel($doc->document_type),
                            $daysLeft,
                            $doc->expiry_date->format('Y-m-d'),
                        );
                        $doc->update(['last_alert_sent_at' => now()]);
                        $sent++;
                    }
                }
            }
        }

        // وثائق المحلّ
        foreach (BusinessDocument::with('outlet.tenant')->whereNotNull('expiry_date')->get() as $doc) {
            $daysLeft = $doc->daysUntilExpiry();

            if (in_array($daysLeft, [60, 30, 7])) {
                $field = "alert_{$daysLeft}_days";
                if ($doc->{$field}) {
                    $tenant = $doc->outlet?->tenant ?? \App\Models\Sahab\Tenant::find($doc->tenant_id);
                    if ($tenant && $tenant->mobile) {
                        $this->whatsapp->sendBusinessDocumentExpiryAlert(
                            $tenant->mobile,
                            $tenant->name,
                            $this->getBusinessDocumentTypeLabel($doc->document_type),
                            $daysLeft,
                            $doc->expiry_date->format('Y-m-d'),
                        );
                        $doc->update(['last_alert_sent_at' => now()]);
                        $sent++;
                    }
                }
            }
        }

        return ['sent' => $sent];
    }

    // ============================================================
    //  ATTENDANCE (الحضور بـ GPS)
    // ============================================================

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:sahab_employees,id',
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
            'method'      => 'required|in:mobile_app,biometric,qr_code,manual,card',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $today = now()->toDateString();

        // التحقّق من المسافة عن الفرع (Geofencing)
        $distance = null;
        $allowed = true;

        if ($employee->gps_required && $employee->outlet) {
            $outlet = $employee->outlet;
            $distance = $this->calculateDistance(
                $validated['latitude'], $validated['longitude'],
                $outlet->latitude, $outlet->longitude
            );

            if ($distance > $outlet->geofence_radius_meters) {
                $allowed = false;
            }
        }

        if (!$allowed) {
            return response()->json([
                'error'    => 'أنت بعيد عن موقع العمل',
                'distance' => round($distance),
                'max_allowed' => $employee->outlet->geofence_radius_meters,
            ], 422);
        }

        // التأخير
        $checkInTime = now();
        $lateMinutes = 0;
        if ($employee->shift_start) {
            $shiftStart = $checkInTime->copy()->setTimeFromTimeString($employee->shift_start);
            if ($checkInTime->gt($shiftStart)) {
                $lateMinutes = (int) $checkInTime->diffInMinutes($shiftStart);
            }
        }

        $attendance = Attendance::updateOrCreate(
            [
                'tenant_id'  => $employee->tenant_id,
                'employee_id'=> $employee->id,
                'date'       => $today,
            ],
            [
                'check_in_time'           => $checkInTime->format('H:i:s'),
                'check_in_lat'            => $validated['latitude'],
                'check_in_lng'            => $validated['longitude'],
                'check_in_distance_meters'=> round($distance ?? 0),
                'check_in_method'         => $validated['method'],
                'late_minutes'            => $lateMinutes,
                'status'                  => $lateMinutes > 0 ? 'late' : 'present',
            ]
        );

        return response()->json(['success' => true, 'attendance' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:sahab_employees,id',
            'latitude'    => 'required|numeric',
            'longitude'   => 'required|numeric',
        ]);

        $today = now()->toDateString();
        $attendance = Attendance::where('employee_id', $validated['employee_id'])
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json(['error' => 'لم تسجّل دخولك اليوم'], 400);
        }

        // حساب ساعات العمل
        $checkIn = \Carbon\Carbon::parse($today . ' ' . $attendance->check_in_time);
        $checkOut = now();
        $workedHours = $checkOut->diffInMinutes($checkIn) / 60;

        $attendance->update([
            'check_out_time' => $checkOut->format('H:i:s'),
            'check_out_lat'  => $validated['latitude'],
            'check_out_lng'  => $validated['longitude'],
            'worked_hours'   => round($workedHours, 2),
        ]);

        return response()->json(['success' => true, 'attendance' => $attendance]);
    }

    /**
     * بدء اختبار مفاجئ للموظّف
     */
    public function triggerSurpriseTest(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:sahab_employees,id',
            'test_type'   => 'required|in:qr_scan,photo_selfie,gps_check,pin_code',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        $test = SurpriseTest::create([
            'tenant_id'    => $employee->tenant_id,
            'employee_id'  => $employee->id,
            'test_type'    => $validated['test_type'],
            'triggered_at' => now(),
            'expires_at'   => now()->addMinutes(10),
            'result'       => 'pending',
        ]);

        // إخطار الموظّف عبر واتساب/Push
        if ($employee->mobile) {
            $this->whatsapp->sendSurpriseTestRequest(
                $employee->mobile,
                $employee->full_name,
                $validated['test_type']
            );
        }

        return response()->json(['success' => true, 'test' => $test]);
    }

    // ============================================================
    //  LEAVES
    // ============================================================

    public function requestLeave(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:sahab_employees,id',
            'leave_type'  => 'required|in:annual,sick,emergency,unpaid,maternity,hajj,bereavement,marriage,other',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'nullable|string',
        ]);

        $start = \Carbon\Carbon::parse($validated['start_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        $days = $start->diffInDays($end) + 1;

        $leave = Leave::create([
            ...$validated,
            'days_count' => $days,
            'status'     => 'pending',
        ]);

        return response()->json(['success' => true, 'leave' => $leave], 201);
    }

    public function approveLeave(Leave $leave)
    {
        $this->authorize('approve', $leave);

        $leave->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // إخطار الموظّف
        $employee = $leave->employee;
        if ($employee && $employee->mobile) {
            $this->whatsapp->sendLeaveApproved($employee->mobile, $leave);
        }

        return response()->json(['success' => true]);
    }

    // ============================================================
    //  Helpers
    // ============================================================
    protected function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        if (!$lat2 || !$lng2) return 0;
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    protected function getDocumentTypeLabel(string $type): string
    {
        return [
            'iqama' => 'الإقامة',
            'work_permit' => 'رخصة العمل',
            'passport' => 'جواز السفر',
            'health_insurance' => 'التأمين الصحّي',
            'driver_license' => 'رخصة القيادة',
            'gosi' => 'التأمينات الاجتماعيّة',
            'medical_check' => 'الفحص الطبي',
            'training_cert' => 'شهادة تدريب',
            'food_handler' => 'شهادة سلامة غذائيّة',
            'contract' => 'عقد العمل',
        ][$type] ?? $type;
    }

    protected function getBusinessDocumentTypeLabel(string $type): string
    {
        return [
            'commercial_registration' => 'السجل التجاري',
            'municipal_license' => 'الرخصة البلديّة',
            'rent_contract' => 'عقد الإيجار',
            'civil_defense' => 'شهادة الدفاع المدني',
            'zakat_cert' => 'شهادة الزكاة',
            'vat_cert' => 'شهادة ضريبة القيمة المضافة',
            'health_cert' => 'الشهادة الصحّيّة',
            'chamber_membership' => 'عضويّة الغرفة التجاريّة',
            'gosi_cert' => 'شهادة التأمينات',
            'saudization_cert' => 'شهادة السعودة',
            'food_license' => 'رخصة الأغذية',
        ][$type] ?? $type;
    }
}
