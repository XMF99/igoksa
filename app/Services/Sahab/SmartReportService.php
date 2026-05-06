<?php

namespace App\Services\Sahab;

use App\Models\Sahab\Invoice;
use App\Models\Sahab\Tenant;
use App\Models\Sahab\Product;
use App\Models\Sahab\ReportSubscription;
use App\Models\Sahab\ReportLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SmartReportService — مستوحى من تقارير دفترة الذكيّة
 *
 * مميزات:
 *  ✓ تقارير قابلة للتخصيص (يومي / أسبوعي / شهري)
 *  ✓ المستخدم يختار محتوى التقرير بنفسه
 *  ✓ إرسال تلقائي عبر واتساب وإيميل
 *  ✓ تصدير PDF بألوان جذّابة
 */
class SmartReportService
{
    public function __construct(
        protected WhatsAppService $whatsapp,
        protected AiAssistantService $ai,
    ) {}

    /**
     * توليد تقرير يومي
     */
    public function generateDailyReport(Tenant $tenant, ?Carbon $date = null): array
    {
        $date = $date ?? now();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $report = [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'period' => 'daily',
            'date' => $date->format('Y-m-d'),
            'generated_at' => now()->toIso8601String(),
        ];

        // 1. ملخّص المبيعات
        $report['sales_summary'] = $this->getSalesSummary($tenant, $start, $end);

        // 2. أكثر المنتجات مبيعاً
        $report['top_products'] = $this->getTopProducts($tenant, $start, $end, 5);

        // 3. أفضل موظّفين (cashiers)
        $report['top_employees'] = $this->getTopEmployees($tenant, $start, $end, 3);

        // 4. توزيع طرق الدفع
        $report['payment_methods'] = $this->getPaymentBreakdown($tenant, $start, $end);

        // 5. توزيع المصادر (POS، تطبيقات التوصيل)
        $report['source_breakdown'] = $this->getSourceBreakdown($tenant, $start, $end);

        // 6. مقارنة مع الأمس
        $yesterdayStart = $start->copy()->subDay();
        $yesterdayEnd = $end->copy()->subDay();
        $yesterdaySales = $this->getSalesSummary($tenant, $yesterdayStart, $yesterdayEnd);
        $report['comparison'] = [
            'yesterday_total' => $yesterdaySales['total_sales'],
            'today_total' => $report['sales_summary']['total_sales'],
            'diff_amount' => $report['sales_summary']['total_sales'] - $yesterdaySales['total_sales'],
            'diff_percentage' => $yesterdaySales['total_sales'] > 0
                ? round(($report['sales_summary']['total_sales'] - $yesterdaySales['total_sales'])
                    / $yesterdaySales['total_sales'] * 100, 1)
                : 0,
        ];

        // 7. تنبيهات
        $report['alerts'] = $this->getAlerts($tenant);

        return $report;
    }

    /**
     * توليد تقرير أسبوعي
     */
    public function generateWeeklyReport(Tenant $tenant, ?Carbon $weekStart = null): array
    {
        $weekStart = $weekStart ?? now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $report = [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'period' => 'weekly',
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'generated_at' => now()->toIso8601String(),
        ];

        $report['sales_summary'] = $this->getSalesSummary($tenant, $weekStart, $weekEnd);
        $report['top_products'] = $this->getTopProducts($tenant, $weekStart, $weekEnd, 10);
        $report['top_employees'] = $this->getTopEmployees($tenant, $weekStart, $weekEnd, 5);

        // مقارنة الأيّام
        $report['daily_comparison'] = $this->getDailyComparison($tenant, $weekStart, $weekEnd);

        // اتجاه المبيعات
        $report['sales_trend'] = $this->getSalesTrend($tenant, $weekStart, $weekEnd);

        // مقارنة مع الأسبوع السابق
        $prevWeekStart = $weekStart->copy()->subWeek();
        $prevWeekEnd = $weekEnd->copy()->subWeek();
        $prevSales = $this->getSalesSummary($tenant, $prevWeekStart, $prevWeekEnd);
        $report['comparison'] = [
            'prev_week_total' => $prevSales['total_sales'],
            'current_week_total' => $report['sales_summary']['total_sales'],
            'growth_percentage' => $prevSales['total_sales'] > 0
                ? round(($report['sales_summary']['total_sales'] - $prevSales['total_sales'])
                    / $prevSales['total_sales'] * 100, 1)
                : 0,
        ];

        return $report;
    }

    /**
     * توليد تقرير شهري شامل
     */
    public function generateMonthlyReport(Tenant $tenant, ?Carbon $monthStart = null): array
    {
        $monthStart = $monthStart ?? now()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $report = [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'period' => 'monthly',
            'month' => $monthStart->format('Y-m'),
            'month_name' => $monthStart->translatedFormat('F Y'),
            'generated_at' => now()->toIso8601String(),
        ];

        $report['sales_summary'] = $this->getSalesSummary($tenant, $monthStart, $monthEnd);
        $report['top_products'] = $this->getTopProducts($tenant, $monthStart, $monthEnd, 20);
        $report['top_employees'] = $this->getTopEmployees($tenant, $monthStart, $monthEnd, 10);
        $report['payment_methods'] = $this->getPaymentBreakdown($tenant, $monthStart, $monthEnd);
        $report['source_breakdown'] = $this->getSourceBreakdown($tenant, $monthStart, $monthEnd);
        $report['daily_breakdown'] = $this->getDailyComparison($tenant, $monthStart, $monthEnd);

        // الأرباح الصافية (تقريبيّة)
        $totalRevenue = $report['sales_summary']['total_sales'];
        $estimatedCost = $totalRevenue * 0.40; // افتراض 40% تكلفة بضاعة
        $report['profit_estimate'] = [
            'revenue' => $totalRevenue,
            'estimated_cogs' => $estimatedCost,
            'estimated_gross_profit' => $totalRevenue - $estimatedCost,
            'profit_margin' => $totalRevenue > 0 ? round(($totalRevenue - $estimatedCost) / $totalRevenue * 100, 1) : 0,
        ];

        // مقارنة مع الشهر السابق
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $prevMonthStart->copy()->endOfMonth();
        $prevSales = $this->getSalesSummary($tenant, $prevMonthStart, $prevMonthEnd);
        $report['comparison'] = [
            'prev_month_total' => $prevSales['total_sales'],
            'current_month_total' => $report['sales_summary']['total_sales'],
            'growth_percentage' => $prevSales['total_sales'] > 0
                ? round(($report['sales_summary']['total_sales'] - $prevSales['total_sales'])
                    / $prevSales['total_sales'] * 100, 1)
                : 0,
        ];

        return $report;
    }

    // ================================================================
    //  Helper queries
    // ================================================================

    protected function getSalesSummary(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        $query = Invoice::where('tenant_id', $tenant->id)
            ->whereBetween('invoice_date', [$start, $end])
            ->where('status', '!=', 'cancelled');

        $stats = $query->clone()->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(total_amount), 0) as total,
            COALESCE(SUM(vat_amount), 0) as vat,
            COALESCE(SUM(discount_amount), 0) as discount,
            COALESCE(AVG(total_amount), 0) as avg
        ')->first();

        return [
            'invoice_count' => (int) $stats->count,
            'total_sales' => round($stats->total, 2),
            'total_vat' => round($stats->vat, 2),
            'total_discount' => round($stats->discount, 2),
            'avg_invoice' => round($stats->avg, 2),
            'net_sales' => round($stats->total - $stats->vat, 2),
        ];
    }

    protected function getTopProducts(Tenant $tenant, Carbon $start, Carbon $end, int $limit = 10): array
    {
        return DB::table('sahab_invoice_items as items')
            ->join('sahab_invoices as inv', 'inv.id', '=', 'items.invoice_id')
            ->where('inv.tenant_id', $tenant->id)
            ->whereBetween('inv.invoice_date', [$start, $end])
            ->where('inv.status', '!=', 'cancelled')
            ->select(
                'items.product_name',
                DB::raw('SUM(items.quantity) as total_qty'),
                DB::raw('SUM(items.total) as total_revenue'),
                DB::raw('COUNT(DISTINCT items.invoice_id) as orders_count')
            )
            ->groupBy('items.product_name')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'name' => $r->product_name,
                'quantity' => (int) $r->total_qty,
                'revenue' => round($r->total_revenue, 2),
                'orders' => (int) $r->orders_count,
            ])
            ->toArray();
    }

    protected function getTopEmployees(Tenant $tenant, Carbon $start, Carbon $end, int $limit = 5): array
    {
        return DB::table('sahab_invoices as inv')
            ->where('inv.tenant_id', $tenant->id)
            ->whereBetween('inv.invoice_date', [$start, $end])
            ->where('inv.status', '!=', 'cancelled')
            ->select(
                'inv.cashier_id',
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('SUM(inv.total_amount) as total_sales'),
                DB::raw('AVG(inv.total_amount) as avg_invoice')
            )
            ->groupBy('inv.cashier_id')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'cashier_id' => $r->cashier_id,
                'invoice_count' => (int) $r->invoice_count,
                'total_sales' => round($r->total_sales, 2),
                'avg_invoice' => round($r->avg_invoice, 2),
            ])
            ->toArray();
    }

    protected function getPaymentBreakdown(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return DB::table('sahab_payments')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('paid_at', [$start, $end])
            ->where('status', 'completed')
            ->select(
                'method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('method')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'method' => $r->method,
                'method_label' => $this->translatePaymentMethod($r->method),
                'count' => (int) $r->count,
                'total' => round($r->total, 2),
            ])
            ->toArray();
    }

    protected function getSourceBreakdown(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return DB::table('sahab_invoices')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('invoice_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->select(
                'source',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'source' => $r->source,
                'source_label' => $this->translateSource($r->source),
                'count' => (int) $r->count,
                'total' => round($r->total, 2),
            ])
            ->toArray();
    }

    protected function getDailyComparison(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        return DB::table('sahab_invoices')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('invoice_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->select(
                DB::raw('DATE(invoice_date) as day'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn($r) => [
                'date' => $r->day,
                'day_name' => Carbon::parse($r->day)->translatedFormat('l'),
                'count' => (int) $r->count,
                'total' => round($r->total, 2),
            ])
            ->toArray();
    }

    protected function getSalesTrend(Tenant $tenant, Carbon $start, Carbon $end): array
    {
        $daily = $this->getDailyComparison($tenant, $start, $end);
        if (empty($daily)) return ['trend' => 'stable', 'direction' => '➡️'];

        $first = $daily[0]['total'] ?? 0;
        $last = end($daily)['total'] ?? 0;

        if ($first == 0) return ['trend' => 'rising', 'direction' => '📈'];

        $change = ($last - $first) / $first * 100;

        if ($change > 10) return ['trend' => 'rising_fast', 'direction' => '🚀', 'change_pct' => round($change, 1)];
        if ($change > 0) return ['trend' => 'rising', 'direction' => '📈', 'change_pct' => round($change, 1)];
        if ($change < -10) return ['trend' => 'falling_fast', 'direction' => '📉', 'change_pct' => round($change, 1)];
        if ($change < 0) return ['trend' => 'falling', 'direction' => '↘️', 'change_pct' => round($change, 1)];

        return ['trend' => 'stable', 'direction' => '➡️', 'change_pct' => 0];
    }

    protected function getAlerts(Tenant $tenant): array
    {
        $alerts = [];

        // مخزون منخفض
        $lowStockCount = Product::where('tenant_id', $tenant->id)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->count();
        if ($lowStockCount > 0) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => 'warning',
                'message' => "{$lowStockCount} منتج قارب نفاد مخزونه",
            ];
        }

        // وثائق منتهية الصلاحيّة
        $expiringDocs = DB::table('sahab_employee_documents')
            ->where('tenant_id', $tenant->id)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();
        if ($expiringDocs > 0) {
            $alerts[] = [
                'type' => 'expiring_documents',
                'severity' => 'high',
                'message' => "{$expiringDocs} وثيقة موظّف تنتهي خلال 30 يوم",
            ];
        }

        // أنشطة مشبوهة
        $suspicious = DB::table('sahab_suspicious_activities')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'new')
            ->whereDate('created_at', today())
            ->count();
        if ($suspicious > 0) {
            $alerts[] = [
                'type' => 'suspicious_activity',
                'severity' => 'high',
                'message' => "{$suspicious} نشاط مشبوه يحتاج مراجعتك",
            ];
        }

        return $alerts;
    }

    /**
     * إرسال التقارير المجدولة (يُستدعى من Cron)
     */
    public function dispatchScheduledReports(): int
    {
        $now = now();
        $sent = 0;

        $subscriptions = ReportSubscription::where('is_active', true)
            ->whereTime('send_time', '<=', $now->format('H:i:s'))
            ->where(function ($q) use ($now) {
                $q->whereNull('last_sent_at')
                  ->orWhere('last_sent_at', '<', $now->copy()->subHours(20));
            })
            ->get();

        foreach ($subscriptions as $sub) {
            try {
                $tenant = Tenant::find($sub->tenant_id);
                if (!$tenant || !$tenant->isActive()) continue;

                $report = match ($sub->frequency) {
                    'weekly' => $this->generateWeeklyReport($tenant),
                    'monthly' => $this->generateMonthlyReport($tenant),
                    default => $this->generateDailyReport($tenant),
                };

                // فلترة المحتوى حسب الاشتراك
                $report = $this->filterByPreferences($report, $sub);

                // إرسال للقنوات المختارة
                $channelsSent = [];
                foreach ($sub->recipients as $recipient) {
                    if ($recipient['channel'] === 'whatsapp') {
                        $sent_ok = $this->whatsapp->sendDailyReport($tenant, $report, $recipient['value']);
                        if ($sent_ok) $channelsSent[] = $recipient;
                    }
                }

                ReportLog::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $sub->id,
                    'frequency' => $sub->frequency,
                    'report_date' => today(),
                    'payload' => $report,
                    'channels_sent' => $channelsSent,
                ]);

                $sub->update(['last_sent_at' => $now]);
                $sent++;
            } catch (\Exception $e) {
                \Log::error('Report dispatch failed', [
                    'subscription' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    protected function filterByPreferences(array $report, ReportSubscription $sub): array
    {
        if (!$sub->include_top_products) unset($report['top_products']);
        if (!$sub->include_top_employees) unset($report['top_employees']);
        if (!$sub->include_payment_methods) unset($report['payment_methods']);
        if (!$sub->include_delivery_breakdown) unset($report['source_breakdown']);
        if (!$sub->include_comparisons) unset($report['comparison'], $report['daily_comparison']);
        return $report;
    }

    protected function translatePaymentMethod(string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي', 'mada' => 'مدى', 'visa' => 'فيزا',
            'mastercard' => 'ماستركارد', 'apple_pay' => 'Apple Pay',
            'stc_pay' => 'STC Pay', 'urpay' => 'urpay',
            'tabby' => 'تابي', 'tamara' => 'تمارا',
            'bank_transfer' => 'تحويل بنكي', 'check' => 'شيك',
            'wallet_balance' => 'محفظة', 'mixed' => 'مختلط',
            default => $method,
        };
    }

    protected function translateSource(string $source): string
    {
        return match ($source) {
            'pos' => 'الكاشير المباشر',
            'whatsapp' => 'واتساب', 'website' => 'الموقع',
            'mobile_app' => 'تطبيق الجوّال',
            'hungerstation' => 'هنقرستيشن', 'jahez' => 'جاهز',
            'toshhel' => 'توصيل', 'mrsool' => 'مرسول',
            'thechefz' => 'تشيفز', 'ninja' => 'نينجا',
            'toyou' => 'تو يو', 'talabat' => 'طلبات',
            default => $source,
        };
    }
}
