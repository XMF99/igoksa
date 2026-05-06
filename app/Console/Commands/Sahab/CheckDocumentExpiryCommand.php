<?php

namespace App\Console\Commands\Sahab;

use App\Models\Sahab\BusinessDocument;
use App\Models\Sahab\EmployeeDocument;
use App\Models\Sahab\Tenant;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Command: sahab:check-document-expiry
 *  يفحص الوثائق المنتهية أو القاربة على الانتهاء
 *  ويرسل تنبيهات على واتساب (60 / 30 / 7 يوم)
 *
 *  جدولة: يوميّاً الساعة 9 صباحاً
 */
class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'sahab:check-document-expiry
                          {--tenant=* : فحص مستأجر محدّد فقط}';
    protected $description = 'فحص الوثائق وإرسال تنبيهات الانتهاء';

    public function handle(WhatsAppService $whatsapp): int
    {
        $this->info('🔍 بدء فحص الوثائق...');
        $sent = 0;
        $errors = 0;

        $thresholds = [60, 30, 7];

        // وثائق الموظّفين
        foreach (EmployeeDocument::with('employee.tenant')->whereNotNull('expiry_date')->cursor() as $doc) {
            try {
                $daysLeft = (int) now()->diffInDays($doc->expiry_date, false);

                if (!in_array($daysLeft, $thresholds)) continue;

                $field = "alert_{$daysLeft}_days";
                if (!$doc->{$field}) continue;

                $tenant = $doc->employee?->tenant;
                if (!$tenant || !$tenant->mobile) continue;

                $whatsapp->sendDocumentExpiryAlert(
                    $tenant->mobile,
                    $doc->employee->full_name,
                    $this->labelForType($doc->document_type),
                    $daysLeft,
                    $doc->expiry_date->format('Y-m-d')
                );

                $doc->update(['last_alert_sent_at' => now()]);
                $sent++;
                $this->line("  ✓ {$doc->employee->full_name} — {$daysLeft} يوم");
            } catch (\Exception $e) {
                $errors++;
                $this->error("خطأ: {$e->getMessage()}");
            }
        }

        // وثائق المحلّ
        foreach (BusinessDocument::with('outlet.tenant')->whereNotNull('expiry_date')->cursor() as $doc) {
            try {
                $daysLeft = (int) now()->diffInDays($doc->expiry_date, false);

                if (!in_array($daysLeft, $thresholds)) continue;

                $field = "alert_{$daysLeft}_days";
                if (!$doc->{$field}) continue;

                $tenant = $doc->outlet?->tenant ?? Tenant::find($doc->tenant_id);
                if (!$tenant || !$tenant->mobile) continue;

                $whatsapp->sendBusinessDocumentExpiryAlert(
                    $tenant->mobile,
                    $tenant->name,
                    $this->labelForBusinessType($doc->document_type),
                    $daysLeft,
                    $doc->expiry_date->format('Y-m-d')
                );

                $doc->update(['last_alert_sent_at' => now()]);
                $sent++;
                $this->line("  ✓ {$tenant->name} — {$daysLeft} يوم");
            } catch (\Exception $e) {
                $errors++;
            }
        }

        $this->info("");
        $this->info("📊 النتيجة:");
        $this->info("   تنبيهات أُرسلت: {$sent}");
        if ($errors) $this->warn("   أخطاء: {$errors}");

        return self::SUCCESS;
    }

    protected function labelForType(string $type): string
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

    protected function labelForBusinessType(string $type): string
    {
        return [
            'commercial_registration' => 'السجل التجاري',
            'municipal_license' => 'الرخصة البلديّة',
            'rent_contract' => 'عقد الإيجار',
            'civil_defense' => 'شهادة الدفاع المدني',
            'zakat_cert' => 'شهادة الزكاة',
            'vat_cert' => 'شهادة ض.م',
            'health_cert' => 'الشهادة الصحّيّة',
        ][$type] ?? $type;
    }
}
