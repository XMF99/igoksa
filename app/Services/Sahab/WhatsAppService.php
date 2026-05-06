<?php

namespace App\Services\Sahab;

use App\Models\Sahab\Invoice;
use App\Models\Sahab\MessagingLog;
use App\Models\Sahab\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppService — إرسال رسائل واتساب
 *
 * يدعم 3 مزوّدين:
 *  1. wa.me (مجاني — يفتح المحادثة فقط)
 *  2. Unifonic (سعودي — للإرسال التلقائي)
 *  3. Twilio WhatsApp (دولي)
 *
 * مستوحى من: WhatsStore/app/Services/WhatsAppService.php
 * مع تكييف للسوق السعودي
 */
class WhatsAppService
{
    /**
     * إرسال تأكيد الفاتورة للعميل
     */
    public function sendInvoiceConfirmation(Invoice $invoice, string $whatsappNumber): bool
    {
        $tenant = Tenant::find($invoice->tenant_id);
        if (!$tenant) return false;

        $cleanNumber = $this->cleanNumber($whatsappNumber);
        if (!$cleanNumber) return false;

        $message = $this->buildInvoiceMessage($invoice, $tenant);
        $provider = $tenant->getSetting('whatsapp_provider', 'wa_me');

        return match ($provider) {
            'unifonic' => $this->sendViaUnifonic($cleanNumber, $message, $tenant),
            'twilio' => $this->sendViaTwilio($cleanNumber, $message, $tenant),
            default => $this->prepareWaMeUrl($cleanNumber, $message, $tenant->id),
        };
    }

    /**
     * إرسال تقرير يومي للمالك
     */
    public function sendDailyReport(Tenant $tenant, array $reportData, string $ownerNumber): bool
    {
        $message = $this->buildReportMessage($tenant, $reportData);
        $cleanNumber = $this->cleanNumber($ownerNumber);

        return $this->sendViaUnifonic($cleanNumber, $message, $tenant);
    }

    /**
     * إرسال تنبيه: وثيقة قارب انتهاؤها
     */
    public function sendDocumentExpiryAlert(Tenant $tenant, string $ownerNumber, array $documents): bool
    {
        $message = "🚨 *تنبيه: وثائق قارب انتهاء صلاحيّتها*\n\n";
        foreach ($documents as $doc) {
            $type = $this->translateDocType($doc['type']);
            $days = $doc['days_left'];
            $emoji = $days <= 7 ? '🔴' : ($days <= 30 ? '🟡' : '🟢');
            $message .= "{$emoji} {$type}";
            if (!empty($doc['employee_name'])) {
                $message .= " ({$doc['employee_name']})";
            }
            $message .= " — متبقّي {$days} يوم\n";
        }
        $message .= "\n📅 جدّد قبل فوات الأوان لتجنّب الغرامات.";

        return $this->sendViaUnifonic($this->cleanNumber($ownerNumber), $message, $tenant);
    }

    /**
     * إرسال تنبيه: نشاط مشبوه
     */
    public function sendSuspiciousActivityAlert(Tenant $tenant, string $ownerNumber, array $activity): bool
    {
        $emoji = match ($activity['severity']) {
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '⚡',
            default => 'ℹ️',
        };

        $message = "{$emoji} *تنبيه: نشاط مشبوه في محلّك*\n\n";
        $message .= "*{$activity['title']}*\n\n";
        $message .= $activity['description'];
        $message .= "\n\n_مصدر التنبيه: نظام كاشف الاحتيال في سحاب_";

        return $this->sendViaUnifonic($this->cleanNumber($ownerNumber), $message, $tenant);
    }

    // ================================================================
    //  بناء الرسائل
    // ================================================================

    protected function buildInvoiceMessage(Invoice $invoice, Tenant $tenant): string
    {
        $items = $invoice->items()->get();
        $itemsText = '';
        foreach ($items as $item) {
            $itemsText .= "• {$item->product_name} ×{$item->quantity}\n";
        }

        $msg = "🧾 *فاتورة {$tenant->name}*\n\n";
        $msg .= "رقم الفاتورة: `{$invoice->invoice_number}`\n";
        $msg .= "التاريخ: " . $invoice->invoice_date->format('Y-m-d H:i') . "\n\n";
        $msg .= "*الأصناف:*\n{$itemsText}\n";
        $msg .= "المجموع الفرعي: " . number_format($invoice->subtotal, 2) . " ر.س\n";
        $msg .= "الضريبة: " . number_format($invoice->vat_amount, 2) . " ر.س\n";
        $msg .= "*الإجمالي: " . number_format($invoice->total_amount, 2) . " ر.س*\n\n";
        $msg .= "شكراً لزيارتكم 🌟";

        return $msg;
    }

    protected function buildReportMessage(Tenant $tenant, array $data): string
    {
        $msg = "📊 *تقرير اليوم — {$tenant->name}*\n";
        $msg .= "_" . now()->format('Y-m-d') . "_\n\n";

        if (isset($data['total_sales'])) {
            $msg .= "💰 إجمالي المبيعات: *" . number_format($data['total_sales'], 2) . " ر.س*\n";
        }
        if (isset($data['invoice_count'])) {
            $msg .= "🧾 عدد الفواتير: *{$data['invoice_count']}*\n";
        }
        if (isset($data['avg_invoice'])) {
            $msg .= "📈 متوسّط الفاتورة: " . number_format($data['avg_invoice'], 2) . " ر.س\n";
        }
        if (isset($data['top_product'])) {
            $msg .= "🏆 أكثر منتج مبيعاً: *{$data['top_product']}*\n";
        }
        if (isset($data['top_employee'])) {
            $msg .= "⭐ أفضل موظّف: *{$data['top_employee']}*\n";
        }
        if (isset($data['delivery_orders'])) {
            $msg .= "🛵 طلبات التوصيل: {$data['delivery_orders']}\n";
        }

        if (!empty($data['comparison'])) {
            $msg .= "\n*مقارنة بالأمس:*\n";
            $diff = $data['comparison']['diff_percentage'];
            $emoji = $diff >= 0 ? '📈' : '📉';
            $sign = $diff >= 0 ? '+' : '';
            $msg .= "{$emoji} {$sign}{$diff}%\n";
        }

        if (!empty($data['low_stock_count']) && $data['low_stock_count'] > 0) {
            $msg .= "\n⚠️ {$data['low_stock_count']} منتج قارب نفاد مخزونه";
        }

        return $msg;
    }

    // ================================================================
    //  المزوّدون
    // ================================================================

    /**
     * إرسال عبر Unifonic (السعودي)
     */
    protected function sendViaUnifonic(string $number, string $message, Tenant $tenant): bool
    {
        $appsId = $tenant->getSetting('unifonic_apps_id') ?: env('UNIFONIC_APPS_ID');
        $apiKey = $tenant->getSetting('unifonic_api_key') ?: env('UNIFONIC_API_KEY');

        if (!$appsId || !$apiKey) {
            Log::warning('Unifonic credentials missing', ['tenant' => $tenant->id]);
            return false;
        }

        try {
            $response = Http::asForm()->post('https://api.unifonic.com/wa/Messages/Send', [
                'AppSid' => $appsId,
                'Recipient' => $number,
                'Body' => $message,
                'CorrelationID' => 'sahab_' . $tenant->id . '_' . time(),
            ]);

            $data = $response->json();
            $success = ($data['success'] ?? false) === true;

            MessagingLog::create([
                'tenant_id' => $tenant->id,
                'channel' => 'whatsapp',
                'provider' => 'unifonic',
                'recipient' => $number,
                'body_preview' => mb_substr($message, 0, 200),
                'external_id' => $data['data']['MessageID'] ?? null,
                'status' => $success ? 'sent' : 'failed',
                'error' => $success ? null : ($data['message'] ?? 'unknown'),
                'cost_sar' => 0.15, // متوسّط تكلفة الرسالة
            ]);

            return $success;
        } catch (\Exception $e) {
            Log::error('Unifonic send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * إرسال عبر Twilio
     */
    protected function sendViaTwilio(string $number, string $message, Tenant $tenant): bool
    {
        $accountSid = env('TWILIO_ACCOUNT_SID');
        $authToken = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886');

        if (!$accountSid || !$authToken) return false;

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => "whatsapp:{$number}",
                    'Body' => $message,
                ]);

            $success = $response->successful();
            $data = $response->json();

            MessagingLog::create([
                'tenant_id' => $tenant->id,
                'channel' => 'whatsapp',
                'provider' => 'twilio',
                'recipient' => $number,
                'body_preview' => mb_substr($message, 0, 200),
                'external_id' => $data['sid'] ?? null,
                'status' => $success ? 'sent' : 'failed',
                'error' => $success ? null : ($data['message'] ?? 'unknown'),
            ]);

            return $success;
        } catch (\Exception $e) {
            Log::error('Twilio send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * تجهيز رابط wa.me (يفتح في المتصفّح/التطبيق)
     */
    protected function prepareWaMeUrl(string $number, string $message, int $tenantId): bool
    {
        $url = "https://wa.me/{$number}?text=" . urlencode($message);

        MessagingLog::create([
            'tenant_id' => $tenantId,
            'channel' => 'whatsapp',
            'provider' => 'wa_me',
            'recipient' => $number,
            'body_preview' => mb_substr($message, 0, 200),
            'external_id' => null,
            'status' => 'queued',
            'cost_sar' => 0,
        ]);

        // حفظ الرابط في session للـ frontend
        if (function_exists('session')) {
            session(['whatsapp_redirect_url' => $url]);
        }

        return true;
    }

    // ================================================================
    //  Helpers
    // ================================================================

    protected function cleanNumber(?string $number): ?string
    {
        if (!$number) return null;
        $clean = preg_replace('/[^0-9]/', '', $number);

        // تحويل للصيغة الدوليّة
        if (str_starts_with($clean, '05')) {
            $clean = '966' . substr($clean, 1);
        } elseif (str_starts_with($clean, '5') && strlen($clean) === 9) {
            $clean = '966' . $clean;
        } elseif (str_starts_with($clean, '00')) {
            $clean = substr($clean, 2);
        }

        return strlen($clean) >= 10 && strlen($clean) <= 15 ? $clean : null;
    }

    protected function translateDocType(string $type): string
    {
        return match ($type) {
            'iqama' => 'الإقامة',
            'work_permit' => 'رخصة العمل',
            'passport' => 'جواز السفر',
            'health_insurance' => 'التأمين الصحّي',
            'commercial_registration' => 'السجل التجاري',
            'municipal_license' => 'الرخصة البلديّة',
            'rent_contract' => 'عقد الإيجار',
            'civil_defense' => 'الدفاع المدني',
            default => $type,
        };
    }

    // ============================================================
    //  OTP MESSAGES
    // ============================================================

    /**
     * إرسال OTP عبر الواتساب
     */
    public function sendOtp(string $mobile, string $code, string $purpose): bool
    {
        $message = $this->buildOtpMessage($code, $purpose);
        return $this->sendMessage($mobile, $message);
    }

    /**
     * بناء نص رسالة OTP حسب الغرض
     */
    protected function buildOtpMessage(string $code, string $purpose): string
    {
        $title = match($purpose) {
            'register'         => '🌥 *مرحباً بك في سحاب*',
            'login'            => '🔐 *تسجيل دخول إلى سحاب*',
            'reset_password'   => '🔑 *إعادة تعيين كلمة المرور*',
            'verify_mobile'    => '✓ *التحقّق من جوّالك*',
            'employee_login'   => '👤 *دخول موظّف*',
            'sensitive_action' => '⚠️ *تأكيد عمليّة*',
            default            => '🔢 *كود التحقّق*',
        };

        $msg  = "{$title}\n\n";
        $msg .= "كود التحقّق الخاص بك:\n\n";
        $msg .= "*{$code}*\n\n";
        $msg .= "صالح لمدّة 10 دقائق فقط.\n";
        $msg .= "_لا تشارك هذا الكود مع أيّ شخص._\n\n";

        if ($purpose === 'register') {
            $msg .= "بعد إدخال الكود ستحصل على تجربة 14 يوم مجّانيّة.\n\n";
        }

        $msg .= "---\nمنصّة سحاب 🌥";

        return $msg;
    }

    // ============================================================
    //  ACTIVATION MESSAGES (للتسجيل الجديد)
    // ============================================================

    public function sendActivation(string $mobile, array $data): bool
    {
        $msg  = "🌥 *مرحباً بك في سحاب، {$data['owner_name']}!*\n\n";
        $msg .= "تمّ إنشاء حساب محلّك بنجاح:\n";
        $msg .= "*{$data['business_name']}*\n\n";
        $msg .= "📋 رمز محلّك: `{$data['tenant_code']}`\n";
        $msg .= "📦 الباقة: {$data['plan_name']}\n";
        $msg .= "🎁 تجربة مجّانيّة: {$data['trial_days']} يوم\n\n";
        $msg .= "🔗 رابط الدخول:\n{$data['login_url']}\n\n";
        if (!empty($data['temp_password'])) {
            $msg .= "🔐 كلمة المرور المؤقّتة:\n*{$data['temp_password']}*\n\n";
            $msg .= "_ننصحك بتغييرها فور الدخول._\n\n";
        }
        $msg .= "💡 يمكنك أيضاً الدخول بكود واتساب يومي بدون كلمة مرور.\n\n";
        $msg .= "نحن هنا لمساعدتك في أي وقت!\n\n";
        $msg .= "---\nمنصّة سحاب 🌥";

        return $this->sendMessage($mobile, $msg);
    }

    // ============================================================
    //  WORK ORDER & APPOINTMENT NOTIFICATIONS
    // ============================================================

    public function sendWorkOrderUpdate(string $mobile, $workOrder, string $oldStatus): bool
    {
        $statusLabels = [
            'pending'     => '⏳ قيد الانتظار',
            'scheduled'   => '📅 مجدول',
            'in_progress' => '🔧 قيد التنفيذ',
            'on_hold'     => '⏸ متوقّف مؤقّتاً',
            'completed'   => '✅ مكتمل',
            'cancelled'   => '❌ ملغي',
        ];

        $msg  = "📋 *تحديث على طلبك*\n\n";
        $msg .= "رقم الطلب: {$workOrder->order_number}\n";
        $msg .= "الحالة الجديدة: {$statusLabels[$workOrder->status]}\n";
        if ($workOrder->scheduled_at) {
            $msg .= "📅 الموعد: " . $workOrder->scheduled_at->format('Y-m-d H:i') . "\n";
        }
        if ($workOrder->completion_notes) {
            $msg .= "\n📝 {$workOrder->completion_notes}\n";
        }
        $msg .= "\n---\nمنصّة سحاب 🌥";

        return $this->sendMessage($mobile, $msg);
    }

    public function sendBookingConfirmation(string $mobile, $booking): bool
    {
        $msg  = "✅ *تأكيد الحجز*\n\n";
        $msg .= "رقم الحجز: {$booking->booking_number}\n";
        $msg .= "📅 التاريخ: " . $booking->start_at->format('Y-m-d') . "\n";
        $msg .= "🕐 الوقت: " . $booking->start_at->format('H:i') . " - " . $booking->end_at->format('H:i') . "\n";
        $msg .= "👥 عدد الأشخاص: {$booking->guest_count}\n";
        if ($booking->total_amount > 0) {
            $msg .= "💰 المبلغ: " . number_format($booking->total_amount, 2) . " ر.س\n";
        }
        $msg .= "\nشكراً لاختيارك. نراك قريباً! 🌥";

        return $this->sendMessage($mobile, $msg);
    }

    // ============================================================
    //  Generic message sender (للـ OTP والرسائل العامّة بدون tenant)
    // ============================================================
    public function sendMessage(string $mobile, string $message, ?Tenant $tenant = null): bool
    {
        $normalized = $this->cleanNumber($mobile);
        if (!$normalized) {
            \Log::warning('Invalid mobile for WhatsApp', ['mobile' => $mobile]);
            return false;
        }

        // إذا فيه tenant — استخدم إعداداته. وإلاّ استخدم env العام
        if ($tenant) {
            $provider = $tenant->getSetting('whatsapp_provider') ?: env('WHATSAPP_PROVIDER', 'unifonic');
            return match($provider) {
                'unifonic' => $this->sendViaUnifonic($normalized, $message, $tenant),
                'twilio'   => $this->sendViaTwilio($normalized, $message, $tenant),
                default    => $this->sendViaWaMe($normalized, $message),
            };
        }

        // بدون tenant (لرسائل النظام العامّة كـ OTP)
        return $this->sendSystemMessage($normalized, $message);
    }

    /**
     * إرسال رسالة نظام (OTP، رسائل ترحيب) — يستخدم env فقط
     */
    protected function sendSystemMessage(string $mobile, string $message): bool
    {
        $provider = env('WHATSAPP_PROVIDER', 'unifonic');

        try {
            if ($provider === 'unifonic') {
                $appSid = env('UNIFONIC_APP_SID') ?: env('UNIFONIC_APPS_ID');
                if (!$appSid) {
                    \Log::warning('Unifonic credentials missing — logging message only', [
                        'mobile'  => $mobile,
                        'preview' => mb_substr($message, 0, 100),
                    ]);
                    return true; // في وضع التطوير نعتبره نجح
                }

                $response = \Http::asForm()
                    ->timeout(10)
                    ->post('https://el.cloud.unifonic.com/rest/Messages/send', [
                        'AppSid'    => $appSid,
                        'Recipient' => $mobile,
                        'Body'      => $message,
                    ]);

                return $response->successful();
            }

            if ($provider === 'twilio') {
                $sid   = env('TWILIO_ACCOUNT_SID');
                $token = env('TWILIO_AUTH_TOKEN');
                $from  = env('TWILIO_FROM_WHATSAPP');

                if (!$sid || !$token || !$from) {
                    \Log::info("WhatsApp dev-mode (twilio not configured) → {$mobile}: " . mb_substr($message, 0, 80));
                    return true;
                }

                $response = \Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->timeout(10)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => "whatsapp:{$from}",
                        'To'   => "whatsapp:+{$mobile}",
                        'Body' => $message,
                    ]);

                return $response->successful();
            }

            // wa.me fallback (مجرّد رابط)
            \Log::info("WhatsApp dev-mode → {$mobile}: " . mb_substr($message, 0, 80));
            return true;
        } catch (\Exception $e) {
            \Log::error('WhatsApp send failed', ['mobile' => $mobile, 'error' => $e->getMessage()]);
            return false;
        }
    }

    protected function sendViaWaMe(string $mobile, string $message): bool
    {
        // wa.me رابط فقط (مفيد للاختبار المحلّي)
        \Log::info("WhatsApp wa.me link", [
            'mobile' => $mobile,
            'url'    => "https://wa.me/{$mobile}?text=" . urlencode($message),
        ]);
        return true;
    }
}
