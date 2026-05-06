<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\ReportSubscription;
use App\Services\Sahab\SmartReportService;
use Illuminate\Http\Request;

/**
 * ReportController — التقارير الذكيّة
 */
class ReportController extends Controller
{
    public function __construct(
        protected SmartReportService $reports,
    ) {}

    public function today(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('smart_reports')) {
            return $this->upgradePrompt();
        }
        return response()->json($this->reports->getSalesSummary($tenant, today(), today()));
    }

    public function weekly(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('smart_reports')) {
            return $this->upgradePrompt();
        }
        return response()->json($this->reports->generateWeeklyReport($tenant));
    }

    public function monthly(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('smart_reports')) {
            return $this->upgradePrompt();
        }
        return response()->json($this->reports->generateMonthlyReport($tenant));
    }

    public function employeePerformance(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('smart_reports')) {
            return $this->upgradePrompt();
        }
        return response()->json($this->reports->getTopEmployees($tenant));
    }

    /**
     * رسالة موحّدة للترقية
     */
    protected function upgradePrompt()
    {
        return response()->json([
            'error' => 'feature_not_available',
            'message' => 'التقارير الذكيّة متاحة في الباقة الاحترافيّة والمؤسّسيّة فقط.',
            'upgrade_url' => '/app/subscription',
        ], 403);
    }

    /**
     * الاشتراك بالتقارير الذكيّة
     */
    public function subscribe(Request $request)
    {
        // التحقّق: الميزة متاحة في الباقة الاحترافيّة والمؤسّسيّة فقط
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('smart_reports')) {
            return response()->json([
                'error'   => 'feature_not_available',
                'message' => 'التقارير الذكيّة متاحة في الباقة الاحترافيّة والمؤسّسيّة فقط. ترقّى الآن.',
                'upgrade_url' => '/app/subscription',
            ], 403);
        }

        $validated = $request->validate([
            'frequency'    => 'required|in:daily,weekly,monthly',
            'send_time'    => 'required',
            'recipients'   => 'required|array|min:1',
            'recipients.*.channel' => 'required|in:whatsapp,email,sms',
            'recipients.*.value'   => 'required|string',

            'include_sales_summary'      => 'boolean',
            'include_top_products'        => 'boolean',
            'include_top_categories'      => 'boolean',
            'include_top_employees'       => 'boolean',
            'include_payment_methods'     => 'boolean',
            'include_delivery_breakdown'  => 'boolean',
            'include_low_stock'           => 'boolean',
            'include_expiring_documents'  => 'boolean',
            'include_comparisons'         => 'boolean',
            'include_predictions'         => 'boolean',
            'include_ai_insights'         => 'boolean',
        ]);

        $subscription = ReportSubscription::updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'user_id'   => $request->user()->id,
                'frequency' => $validated['frequency'],
            ],
            $validated
        );

        return response()->json(['success' => true, 'subscription' => $subscription]);
    }

    /**
     * Cron: إرسال التقارير اليوميّة
     */
    public function sendDailyReports()
    {
        \Artisan::call('sahab:send-daily-reports --frequency=daily');
        return response()->json(['ok' => true]);
    }

    public function sendWeeklyReports()
    {
        \Artisan::call('sahab:send-daily-reports --frequency=weekly');
        return response()->json(['ok' => true]);
    }

    public function sendMonthlyReports()
    {
        \Artisan::call('sahab:send-daily-reports --frequency=monthly');
        return response()->json(['ok' => true]);
    }
}
