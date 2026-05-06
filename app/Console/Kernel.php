<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Console Kernel — جدولة مهام سحاب
 *  أضف هذا للـ Console/Kernel.php الرئيسي في ERPGo
 */
class Kernel extends ConsoleKernel
{
    /**
     * المهام المجدولة.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ====== 9 صباحاً يوميّاً — تنبيهات الوثائق المنتهية ======
        $schedule->command('sahab:check-document-expiry')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Riyadh')
                 ->onFailure(fn() => \Log::error('فشل تنبيه الوثائق'))
                 ->withoutOverlapping();

        // ====== 9 مساءً يوميّاً — التقارير اليوميّة ======
        $schedule->command('sahab:send-daily-reports --frequency=daily')
                 ->dailyAt('21:00')
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping();

        // ====== 9 مساءً الأحد — التقارير الأسبوعيّة ======
        $schedule->command('sahab:send-daily-reports --frequency=weekly')
                 ->weeklyOn(0, '21:00')   // 0 = الأحد
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping();

        // ====== أوّل الشهر 9 صباحاً — التقارير الشهريّة ======
        $schedule->command('sahab:send-daily-reports --frequency=monthly')
                 ->monthlyOn(1, '09:00')
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping();

        // ====== أوّل الشهر 10 صباحاً — إهلاك الأصول ======
        $schedule->command('sahab:depreciate-assets')
                 ->monthlyOn(1, '10:00')
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping();

        // ====== كل ساعة — تنظيف الاختبارات المفاجئة المنتهية ======
        $schedule->call(function () {
            \App\Models\Sahab\SurpriseTest::where('result', 'pending')
                ->where('expires_at', '<', now())
                ->update(['result' => 'expired']);
        })->hourly();

        // ====== يوميّاً منتصف الليل — فحص نهاية التجارب المجّانيّة ======
        $schedule->call(function () {
            \App\Models\Sahab\Tenant::where('subscription_status', 'trial')
                ->where('trial_ends_at', '<', now())
                ->update(['subscription_status' => 'past_due']);
        })->dailyAt('00:30')->timezone('Asia/Riyadh');
    }

    /**
     * تسجيل الـ Commands.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
