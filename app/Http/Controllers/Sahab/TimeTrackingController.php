<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\TimeEntry;
use Illuminate\Http\Request;

/**
 * TimeTrackingController — تتبّع الوقت
 *  للحرف، الاستشارات، الخدمات، المشاريع
 */
class TimeTrackingController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('time_tracking')) {
            return $this->upgradePrompt();
        }

        $entries = TimeEntry::where('tenant_id', $tenant->id)
            ->with(['employee:id,full_name', 'workOrder:id,order_number,title', 'customer:id,name'])
            ->when($request->date, fn($q, $d) => $q->whereDate('date', $d))
            ->when($request->employee_id, fn($q, $e) => $q->where('employee_id', $e))
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate(30);

        $totalHours = TimeEntry::where('tenant_id', $tenant->id)
            ->whereDate('date', today())
            ->sum('duration_hours');

        $billableHours = TimeEntry::where('tenant_id', $tenant->id)
            ->whereDate('date', today())
            ->where('is_billable', true)
            ->sum('duration_hours');

        return view('sahab.time_tracking.index', compact('entries', 'totalHours', 'billableHours'));
    }

    /**
     * بدء تسجيل الوقت
     */
    public function startTimer(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('time_tracking')) return $this->upgradePrompt();

        $validated = $request->validate([
            'employee_id'      => 'required|exists:sahab_employees,id',
            'work_order_id'    => 'nullable|exists:sahab_work_orders,id',
            'customer_id'      => 'nullable|exists:sahab_customers,id',
            'task_description' => 'required|string',
            'is_billable'      => 'boolean',
            'hourly_rate'      => 'nullable|numeric|min:0',
        ]);

        // إيقاف أي عدّاد جاري للموظف
        TimeEntry::where('employee_id', $validated['employee_id'])
            ->where('status', 'running')
            ->update(['status' => 'paused']);

        $entry = TimeEntry::create($validated + [
            'tenant_id'  => $tenant->id,
            'date'       => today(),
            'start_time' => now()->format('H:i:s'),
            'status'     => 'running',
        ]);

        return response()->json(['success' => true, 'entry' => $entry]);
    }

    /**
     * إيقاف العدّاد
     */
    public function stopTimer(Request $request, TimeEntry $entry)
    {
        $this->authorizeTenant($request, $entry);

        $start = \Carbon\Carbon::parse($entry->date . ' ' . $entry->start_time);
        $duration = round($start->diffInMinutes(now()) / 60, 2);

        $entry->update([
            'end_time'       => now()->format('H:i:s'),
            'duration_hours' => $duration,
            'total_amount'   => $entry->is_billable ? $duration * ($entry->hourly_rate ?? 0) : 0,
            'status'         => 'completed',
        ]);

        return response()->json(['success' => true, 'entry' => $entry]);
    }

    protected function authorizeTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant_id) abort(403);
    }

    protected function upgradePrompt()
    {
        return response()->json([
            'error'   => 'feature_not_available',
            'message' => 'تتبّع الوقت متاح في الباقة الاحترافيّة والمؤسّسيّة.',
            'upgrade_url' => '/app/subscription',
        ], 403);
    }
}
