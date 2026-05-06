<?php

namespace App\Services\Sahab;

use App\Models\Sahab\BusinessDocument;
use App\Models\Sahab\EmployeeDocument;
use App\Models\Sahab\Tenant;
use Carbon\Carbon;

/**
 * DocumentExpiryService — متابعة الوثائق ومتى تنتهي
 *
 * مميزات دفترة:
 *  ✓ الإقامة، رخصة العمل، الجواز، التأمين الصحّي
 *  ✓ السجل التجاري، البلديّة، الإيجار، الدفاع المدني
 *  ✓ تنبيهات قبل 60 / 30 / 7 أيام
 *  ✓ إرسال إشعار للمسؤول
 */
class DocumentExpiryService
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    /**
     * فحص الوثائق المنتهية أو المقاربة على الانتهاء (يُستدعى يوميّاً)
     */
    public function checkAndNotify(): array
    {
        $tenants = Tenant::where('subscription_status', 'active')
            ->orWhere(function ($q) {
                $q->where('subscription_status', 'trial')
                  ->where('trial_ends_at', '>', now());
            })
            ->get();

        $stats = ['notified' => 0, 'documents_checked' => 0];

        foreach ($tenants as $tenant) {
            $expiring = $this->getExpiringDocuments($tenant);
            $stats['documents_checked'] += count($expiring['employee']) + count($expiring['business']);

            if (count($expiring['employee']) > 0 || count($expiring['business']) > 0) {
                $this->notifyOwner($tenant, $expiring);
                $stats['notified']++;
            }
        }

        return $stats;
    }

    /**
     * استرجاع كل الوثائق المنتهية أو المقاربة
     */
    public function getExpiringDocuments(Tenant $tenant): array
    {
        $now = now();
        $thresholds = [60, 30, 7];

        // وثائق الموظّفين
        $employeeDocs = EmployeeDocument::with('employee')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $now)
            ->where('expiry_date', '<=', $now->copy()->addDays(60))
            ->get()
            ->filter(function ($doc) use ($thresholds) {
                $daysLeft = (int) now()->diffInDays($doc->expiry_date, false);
                return $this->shouldAlert($doc, $daysLeft, $thresholds);
            })
            ->map(fn($d) => [
                'id' => $d->id,
                'type' => $d->document_type,
                'employee_name' => $d->employee->full_name ?? 'غير معروف',
                'document_number' => $d->document_number,
                'expiry_date' => $d->expiry_date->format('Y-m-d'),
                'days_left' => (int) now()->diffInDays($d->expiry_date, false),
            ])
            ->values()
            ->toArray();

        // وثائق المحلّ
        $businessDocs = BusinessDocument::where('tenant_id', $tenant->id)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', $now)
            ->where('expiry_date', '<=', $now->copy()->addDays(60))
            ->get()
            ->filter(function ($doc) use ($thresholds) {
                $daysLeft = (int) now()->diffInDays($doc->expiry_date, false);
                return $this->shouldAlert($doc, $daysLeft, $thresholds);
            })
            ->map(fn($d) => [
                'id' => $d->id,
                'type' => $d->document_type,
                'document_number' => $d->document_number,
                'expiry_date' => $d->expiry_date->format('Y-m-d'),
                'days_left' => (int) now()->diffInDays($d->expiry_date, false),
                'renewal_cost' => $d->renewal_cost,
            ])
            ->values()
            ->toArray();

        return [
            'employee' => $employeeDocs,
            'business' => $businessDocs,
        ];
    }

    /**
     * هل يجب إرسال تنبيه لهذه الوثيقة الآن؟
     */
    protected function shouldAlert($document, int $daysLeft, array $thresholds): bool
    {
        // تجنّب التكرار: لا نُرسل تنبيهَين في نفس اليوم
        if ($document->last_alert_sent_at && $document->last_alert_sent_at->isToday()) {
            return false;
        }

        foreach ($thresholds as $threshold) {
            if ($daysLeft <= $threshold) {
                $field = "alert_{$threshold}_days";
                if (!isset($document->{$field}) || $document->{$field}) {
                    $document->update(['last_alert_sent_at' => now()]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * إرسال إشعار لصاحب المحلّ
     */
    protected function notifyOwner(Tenant $tenant, array $expiring): bool
    {
        $allDocs = [];

        foreach ($expiring['employee'] as $doc) {
            $allDocs[] = $doc + ['employee_name' => $doc['employee_name']];
        }
        foreach ($expiring['business'] as $doc) {
            $allDocs[] = $doc;
        }

        if (empty($allDocs)) return false;

        return $this->whatsapp->sendDocumentExpiryAlert(
            $tenant,
            $tenant->mobile,
            $allDocs
        );
    }

    /**
     * استرجاع لوحة الوثائق للعرض في الواجهة
     */
    public function getDashboard(Tenant $tenant): array
    {
        $employeeDocs = EmployeeDocument::with('employee')
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->get();

        $businessDocs = BusinessDocument::where('tenant_id', $tenant->id)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->get();

        $now = now();

        $stats = [
            'employee' => [
                'total' => $employeeDocs->count(),
                'expired' => $employeeDocs->filter(fn($d) => $d->expiry_date < $now)->count(),
                'expiring_7' => $employeeDocs->filter(fn($d) => $d->expiry_date >= $now && $d->expiry_date <= $now->copy()->addDays(7))->count(),
                'expiring_30' => $employeeDocs->filter(fn($d) => $d->expiry_date > $now->copy()->addDays(7) && $d->expiry_date <= $now->copy()->addDays(30))->count(),
                'valid' => $employeeDocs->filter(fn($d) => $d->expiry_date > $now->copy()->addDays(30))->count(),
            ],
            'business' => [
                'total' => $businessDocs->count(),
                'expired' => $businessDocs->filter(fn($d) => $d->expiry_date < $now)->count(),
                'expiring_7' => $businessDocs->filter(fn($d) => $d->expiry_date >= $now && $d->expiry_date <= $now->copy()->addDays(7))->count(),
                'expiring_30' => $businessDocs->filter(fn($d) => $d->expiry_date > $now->copy()->addDays(7) && $d->expiry_date <= $now->copy()->addDays(30))->count(),
                'valid' => $businessDocs->filter(fn($d) => $d->expiry_date > $now->copy()->addDays(30))->count(),
            ],
        ];

        return [
            'stats' => $stats,
            'employee_docs' => $employeeDocs->map(fn($d) => [
                'id' => $d->id,
                'employee_name' => $d->employee->full_name ?? 'غير معروف',
                'employee_id' => $d->employee_id,
                'type' => $d->document_type,
                'type_label' => $this->translateType($d->document_type),
                'document_number' => $d->document_number,
                'issue_date' => $d->issue_date?->format('Y-m-d'),
                'expiry_date' => $d->expiry_date?->format('Y-m-d'),
                'days_left' => $d->expiry_date ? (int) now()->diffInDays($d->expiry_date, false) : null,
                'status' => $this->getStatus($d->expiry_date),
                'file_path' => $d->file_path,
            ]),
            'business_docs' => $businessDocs->map(fn($d) => [
                'id' => $d->id,
                'type' => $d->document_type,
                'type_label' => $this->translateType($d->document_type),
                'document_number' => $d->document_number,
                'issue_date' => $d->issue_date?->format('Y-m-d'),
                'expiry_date' => $d->expiry_date?->format('Y-m-d'),
                'days_left' => $d->expiry_date ? (int) now()->diffInDays($d->expiry_date, false) : null,
                'status' => $this->getStatus($d->expiry_date),
                'renewal_cost' => $d->renewal_cost,
                'file_path' => $d->file_path,
            ]),
        ];
    }

    protected function getStatus(?Carbon $expiryDate): string
    {
        if (!$expiryDate) return 'unknown';

        $daysLeft = (int) now()->diffInDays($expiryDate, false);

        if ($daysLeft < 0) return 'expired';
        if ($daysLeft <= 7) return 'critical';
        if ($daysLeft <= 30) return 'warning';
        if ($daysLeft <= 60) return 'attention';
        return 'valid';
    }

    protected function translateType(string $type): string
    {
        return match ($type) {
            'iqama' => 'الإقامة',
            'work_permit' => 'رخصة العمل',
            'passport' => 'جواز السفر',
            'health_insurance' => 'التأمين الصحّي',
            'driver_license' => 'رخصة القيادة',
            'gosi' => 'التأمينات الاجتماعيّة',
            'medical_check' => 'الفحص الطبي',
            'training_cert' => 'شهادة تدريب',
            'food_handler' => 'شهادة سلامة غذائيّة',
            'contract' => 'العقد',
            'commercial_registration' => 'السجل التجاري',
            'municipal_license' => 'الرخصة البلديّة',
            'rent_contract' => 'عقد الإيجار',
            'civil_defense' => 'الدفاع المدني',
            'zakat_cert' => 'شهادة الزكاة',
            'vat_cert' => 'شهادة ضريبة القيمة المضافة',
            'health_cert' => 'الشهادة الصحّيّة',
            'chamber_membership' => 'عضويّة الغرفة التجاريّة',
            'gosi_cert' => 'شهادة التأمينات',
            'saudization_cert' => 'شهادة السعودة',
            'food_license' => 'رخصة الأغذية',
            default => $type,
        };
    }
}
