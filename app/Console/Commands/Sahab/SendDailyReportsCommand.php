<?php

namespace App\Console\Commands\Sahab;

use App\Models\Sahab\ReportSubscription;
use App\Models\Sahab\Tenant;
use App\Services\Sahab\SmartReportService;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Command: sahab:send-daily-reports
 *  يولّد ويرسل التقارير اليوميّة لكل المستأجرين النشطين
 *  حسب اشتراك التقارير الذكيّة
 *
 *  جدولة: يوميّاً 9 مساءً
 */
class SendDailyReportsCommand extends Command
{
    protected $signature = 'sahab:send-daily-reports
                          {--tenant= : مستأجر محدّد فقط}
                          {--frequency=daily : daily|weekly|monthly}';
    protected $description = 'إرسال التقارير اليوميّة/الأسبوعيّة/الشهريّة';

    public function handle(SmartReportService $reports, WhatsAppService $whatsapp): int
    {
        $frequency = $this->option('frequency');
        $this->info("📊 إرسال التقارير ({$frequency})...");

        $query = ReportSubscription::with('tenant')
            ->where('frequency', $frequency)
            ->where('is_active', true);

        if ($tid = $this->option('tenant')) {
            $query->where('tenant_id', $tid);
        }

        $sent = 0;
        $errors = 0;

        foreach ($query->cursor() as $subscription) {
            try {
                $tenant = $subscription->tenant;
                if (!$tenant || !$tenant->isActive()) continue;

                // توليد التقرير
                $report = match($frequency) {
                    'daily'   => $reports->generateDailyReport($tenant, $subscription),
                    'weekly'  => $reports->generateWeeklyReport($tenant, $subscription),
                    'monthly' => $reports->generateMonthlyReport($tenant, $subscription),
                };

                // إرسال على القنوات المختارة
                $recipients = $subscription->recipients ?? [];
                foreach ($recipients as $rec) {
                    if ($rec['channel'] === 'whatsapp') {
                        match($frequency) {
                            'daily'   => $whatsapp->sendDailyReport($rec['value'], $tenant->name, $report),
                            'weekly'  => $whatsapp->sendWeeklyReport($rec['value'], $tenant->name, $report),
                            'monthly' => $whatsapp->sendMonthlyReport($rec['value'], $tenant->name, $report),
                        };
                    }
                }

                $subscription->update(['last_sent_at' => now()]);
                $sent++;
                $this->line("  ✓ {$tenant->name}");
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ✗ خطأ في {$subscription->tenant?->name}: {$e->getMessage()}");
            }
        }

        $this->info("");
        $this->info("📊 النتيجة: أُرسل {$sent} | أخطاء {$errors}");

        return self::SUCCESS;
    }
}
