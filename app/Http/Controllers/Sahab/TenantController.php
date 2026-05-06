<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Tenant;
use App\Models\Sahab\Plan;
use App\Models\User;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TenantController — تسجيل المحلّ، اختيار الباقة، الإعدادات
 * --------------------------------------------------------------------
 *  - signup           : التسجيل من صفحة الإعلانات
 *  - activate         : تفعيل الحساب من رابط الواتساب
 *  - chooseplan       : اختيار/تغيير الباقة
 *  - settings         : إعدادات المحلّ
 *  - dashboard        : لوحة الإحصائيّات
 * --------------------------------------------------------------------
 */
class TenantController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsapp,
    ) {}

    /**
     * صفحة الإعلانات الرئيسيّة
     */
    public function landing()
    {
        $plans = Plan::where('is_active', true)->orderBy('display_order')->get();
        $featureLabels = $this->getFeatureLabels();
        return view('sahab.landing.index', compact('plans', 'featureLabels'));
    }

    /**
     * عرض صفحة الباقات
     */
    public function plans()
    {
        $plans = Plan::where('is_active', true)->orderBy('display_order')->get();
        $featureLabels = $this->getFeatureLabels();
        return view('sahab.landing.plans', compact('plans', 'featureLabels'));
    }

    /**
     * تسميات الميزات بالعربي (للـ UI)
     */
    protected function getFeatureLabels(): array
    {
        return [
            // === المبيعات ===
            'pos'                    => 'كاشير سريع متعدّد الأجهزة',
            'invoices'               => 'الفواتير وعروض الأسعار',
            'zatca_phase1'           => 'فاتورة زاتكا (المرحلة الأولى)',
            'zatca_phase2'           => 'فاتورة زاتكا (المرحلة الثانية)',
            'thermal_print'          => 'طباعة حراريّة 58/80mm',
            'price_offers'           => 'العروض والخصومات',
            'sales_targets'          => 'المبيعات المستهدفة والعمولات',
            'installments'           => 'إدارة الأقساط',

            // === العملاء ===
            'customers'              => 'إدارة العملاء',
            'customer_followup'      => 'متابعة العملاء (الديون والأرصدة)',
            'loyalty_program'        => 'نقاط ولاء العملاء',
            'memberships'            => 'الاشتراكات والعضويّات',
            'customer_credits'       => 'النقاط والأرصدة',
            'medical_insurance'      => 'التأمينات الطبيّة',

            // === المخزون ===
            'products'               => 'إدارة المنتجات',
            'inventory'              => 'إدارة المخزون',
            'inventory_basic'        => 'الجرد الأساسي',
            'inventory_advanced'     => 'الجرد المتقدّم',
            'suppliers'              => 'إدارة الموردين',
            'purchases_basic'        => 'المشتريات الأساسيّة',
            'purchase_cycle'         => 'دورة المشتريات الكاملة',
            'stock_permits'          => 'الأذون المخزنيّة',
            'manufacturing'          => 'إدارة التصنيع',

            // === الحسابات ===
            'expenses'               => 'إدارة المصروفات',
            'chart_of_accounts'      => 'دليل الحسابات',
            'journal_entries'        => 'قيود اليوميّة',
            'cost_centers'           => 'مراكز التكلفة',
            'cheque_cycle'           => 'دورة الشيكات',
            'fixed_assets'           => 'إدارة الأصول الثابتة',
            'asset_depreciation'     => 'إهلاك الأصول الآلي',
            'bank_reconciliation'    => 'التسويات البنكيّة',
            'accounting_full'        => 'محاسبة متكاملة',

            // === الموارد البشريّة ===
            'employees_management'   => 'شؤون الموظّفين',
            'attendance'             => 'الحضور والانصراف',
            'gps_attendance'         => 'حضور موظّفين بـ GPS',
            'geofencing'             => 'Geofencing للمحلّ',
            'employee_contracts'     => 'إدارة العقود',
            'employee_requests'      => 'الطلبات (إجازات، استئذان)',
            'document_tracking'      => 'متابعة الوثائق',
            'employee_documents'     => 'وثائق الموظّفين',
            'organizational_structure' => 'الهيكل التنظيمي',
            'payroll_full'           => 'إدارة المرتبات الكاملة',
            'surprise_tests'         => 'اختبار مفاجئ للموظّفين',
            'hrm_full'               => 'موارد بشريّة كاملة',

            // === التشغيل ===
            'work_orders'            => 'أوامر الشغل',
            'service_workflow'       => 'دورة العمل (Workflow)',
            'time_tracking'          => 'تتبّع الوقت',
            'bookings'               => 'إدارة الحجوزات',
            'rental_units'           => 'إدارة الإيجارات والوحدات',
            'rental_contracts'       => 'عقود الإيجار',

            // === الذكاء والتقارير ===
            'smart_reports'          => 'التقارير الذكيّة (يومي/أسبوعي/شهري) ⭐',
            'smart_reports_whatsapp' => 'إرسال التقارير على واتساب',
            'monthly_reports'        => 'تقارير شهريّة PDF',
            'custom_reports'         => 'تقارير مخصّصة',
            'ai_assistant'           => 'مساعد ذكي بالـ AI',
            'ocr_invoices'           => 'قراءة فواتير ذكيّة (OCR)',
            'predictive_analytics'   => 'توقّعات ذكيّة',

            // === الأمان والولاء ===
            'fraud_detection'        => 'كاشف الأنشطة المشبوهة',
            'apple_wallet'           => 'بطاقات Apple Wallet',
            'multi_outlet'           => 'فروع متعدّدة',
            'otp_login'              => 'دخول بكود واتساب',

            // === التكاملات ===
            'delivery_apps_integration' => 'ربط 8 تطبيقات توصيل',
            'kds'                    => 'شاشة المطبخ KDS',
            'whatsapp_store'         => '🛍 متجر واتساب لعملائك ⭐',
            'custom_domain'          => 'دومين مخصّص',
            'api_access'             => 'وصول API للمطوّرين',
            'custom_integrations'    => 'تكاملات مخصّصة',
            'webhooks'               => 'Webhooks',

            // === الـ ERP ===
            'erp_full'               => 'نظام ERP كامل',
            'crm'                    => 'إدارة علاقات العملاء (CRM)',

            // === الدعم ===
            'whatsapp_support'       => 'دعم فني عبر واتساب',
            'priority_support'       => 'دعم بالأولويّة',
            'dedicated_manager'      => 'مدير حساب مخصّص',
            '24x7_support'           => 'دعم 24/7',
            'onboarding'             => 'جلسة تأسيس شخصيّة',
            'all'                    => '✓ كل الميزات',
        ];
    }

    /**
     * تسمية ميزة معيّنة (للاستخدام كـ static helper من Blade)
     */
    public static function featureLabel(string $key): string
    {
        return (new self(app(\App\Services\Sahab\WhatsAppService::class)))
            ->getFeatureLabels()[$key] ?? $key;
    }

    /**
     * تسجيل مستأجر جديد (من صفحة الإعلانات)
     */
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:120',
            'owner_name'    => 'required|string|max:120',
            'mobile'        => 'required|string|max:20|regex:/^(\+?966|0)?5\d{8}$/',
            'email'         => 'required|email|max:120',
            'plan_slug'     => 'required|exists:sahab_plans,slug',
            'industry'      => 'nullable|in:restaurant,cafe,grocery,pharmacy,retail,salon,fashion,electronics,service,other',
            'city'          => 'nullable|string|max:60',
        ]);

        return DB::transaction(function () use ($validated) {
            $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

            // معايرة رقم الجوّال
            $mobile = $this->normalizeMobile($validated['mobile']);

            // تحقّق من عدم التكرار
            $exists = Tenant::where('mobile', $mobile)
                ->orWhere('email', $validated['email'])->first();
            if ($exists) {
                return response()->json([
                    'error' => 'يوجد حساب مسجّل بنفس الجوّال أو البريد. سجّل دخول أو استخدم رقم آخر.',
                ], 422);
            }

            // إنشاء المستأجر
            $tenant = Tenant::create([
                'name'        => $validated['business_name'],
                'owner_name'  => $validated['owner_name'],
                'mobile'      => $mobile,
                'email'       => $validated['email'],
                'industry'    => $validated['industry'] ?? 'other',
                'city'        => $validated['city'] ?? 'الرياض',
                'plan_id'     => $plan->id,
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'billing_cycle' => 'monthly',
            ]);

            // إنشاء حساب المستخدم (المالك)
            $tempPassword = Str::random(10);
            $user = User::create([
                'name'      => $validated['owner_name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($tempPassword),
                'tenant_id' => $tenant->id,
                'mobile'    => $mobile,
            ]);

            // ربط الدور
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('owner');
            }

            // إرسال رمز الدخول على واتساب
            $this->whatsapp->sendActivation($mobile, [
                'business_name'  => $tenant->name,
                'tenant_code'    => $tenant->code,
                'login_url'      => url('/login'),
                'temp_password'  => $tempPassword,
                'plan_name'      => $plan->name_ar,
                'trial_days'     => 14,
            ]);

            return response()->json([
                'success'     => true,
                'message'     => 'تمّ التسجيل! تحقّق من واتساب لاستلام رمز الدخول.',
                'tenant_code' => $tenant->code,
            ]);
        });
    }

    /**
     * لوحة المعلومات الرئيسيّة
     */
    public function dashboard(Request $request)
    {
        $tenant = auth()->user()->tenant;
        $today = now()->toDateString();

        // إحصائيّات اليوم
        $today_invoices = \App\Models\Sahab\Invoice::whereDate('invoice_date', $today)->count();
        $today_sales = \App\Models\Sahab\Invoice::whereDate('invoice_date', $today)->sum('total_amount');
        $today_avg = $today_invoices > 0 ? $today_sales / $today_invoices : 0;

        // مقارنة بأمس
        $yesterday_sales = \App\Models\Sahab\Invoice::whereDate('invoice_date', now()->subDay()->toDateString())->sum('total_amount');
        $sales_growth = $yesterday_sales > 0 ? (($today_sales - $yesterday_sales) / $yesterday_sales) * 100 : 0;

        // طلبات خارجيّة
        $external_orders = \App\Models\Sahab\ExternalOrder::whereDate('received_at', $today)->count();

        // المنتجات الأكثر مبيعاً اليوم
        $top_products = DB::table('sahab_invoice_items')
            ->join('sahab_invoices', 'sahab_invoice_items.invoice_id', '=', 'sahab_invoices.id')
            ->where('sahab_invoices.tenant_id', $tenant->id)
            ->whereDate('sahab_invoices.invoice_date', $today)
            ->select('product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(total) as revenue'))
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // وثائق قاربة على الانتهاء
        $expiring_docs = \App\Models\Sahab\BusinessDocument::where('tenant_id', $tenant->id)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        $expiring_employee_docs = \App\Models\Sahab\EmployeeDocument::where('tenant_id', $tenant->id)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        // مخزون منخفض
        $low_stock = \App\Models\Sahab\Product::where('tenant_id', $tenant->id)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->where('track_inventory', true)
            ->count();

        // تنبيهات الأنشطة المشبوهة
        $suspicious = \App\Models\Sahab\SuspiciousActivity::where('tenant_id', $tenant->id)
            ->where('status', 'new')
            ->count();

        return view('sahab.dashboard', compact(
            'tenant', 'today_invoices', 'today_sales', 'today_avg',
            'sales_growth', 'external_orders', 'top_products',
            'expiring_docs', 'expiring_employee_docs', 'low_stock', 'suspicious'
        ));
    }

    /**
     * تغيير الباقة
     */
    public function changePlan(Request $request)
    {
        $validated = $request->validate([
            'plan_slug'     => 'required|exists:sahab_plans,slug',
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $tenant = auth()->user()->tenant;
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $tenant->update([
            'plan_id'       => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
        ]);

        // إذا كان مفعّل، يبقى مفعّلاً. إذا في تجربة وانتهت، يحتاج دفع.
        return response()->json([
            'success' => true,
            'tenant'  => $tenant->fresh(),
            'plan'    => $plan,
        ]);
    }

    // ====================================================================
    protected function normalizeMobile(string $mobile): string
    {
        $clean = preg_replace('/[\s\-\+\(\)]/', '', $mobile);
        // 5XXXXXXXX → 9665XXXXXXXX
        if (preg_match('/^5\d{8}$/', $clean)) return '966' . $clean;
        // 05XXXXXXXX → 9665XXXXXXXX
        if (preg_match('/^05\d{8}$/', $clean)) return '966' . substr($clean, 1);
        // 9665XXXXXXXX → نفسه
        if (preg_match('/^9665\d{8}$/', $clean)) return $clean;
        return $clean;
    }
}
