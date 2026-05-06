<?php

namespace App\Services\Sahab;

use App\Models\Sahab\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OtpService — إدارة رموز التحقّق OTP
 * --------------------------------------------------------------------
 *  يُستخدم لـ:
 *    - تسجيل المستأجرين الجدد
 *    - تسجيل الدخول بدون كلمة مرور
 *    - إعادة تعيين كلمة المرور
 *    - دخول الموظّفين
 *    - تأكيد العمليّات الحسّاسة (حذف فاتورة، خصم كبير، إلخ)
 *
 *  الميزات:
 *    ✓ كود من 6 أرقام
 *    ✓ صلاحيّة 10 دقائق
 *    ✓ Rate limit (max 5/ساعة لكل رقم)
 *    ✓ يُرسل عبر الواتساب (Unifonic)
 *    ✓ يفشل بعد 5 محاولات خاطئة
 * --------------------------------------------------------------------
 */
class OtpService
{
    public function __construct(
        protected WhatsAppService $whatsapp,
    ) {}

    /**
     * إرسال OTP للجوّال
     *
     * @param string $mobile رقم الجوّال (سيُعاير)
     * @param string $purpose الغرض (register, login, ...)
     * @param array $context بيانات إضافيّة (user_id, tenant_id)
     * @return array ['success' => bool, 'expires_at' => Carbon, 'error' => ?string]
     */
    public function sendOtp(string $mobile, string $purpose, array $context = []): array
    {
        $mobile = $this->normalizeMobile($mobile);

        // Rate limiting
        $key = "otp:{$mobile}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return [
                'success' => false,
                'error'   => "كثرة المحاولات. حاول بعد {$seconds} ثانية.",
                'retry_after' => $seconds,
            ];
        }

        // إلغاء الأكواد السابقة لنفس الغرض
        OtpCode::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subSecond()]);

        // توليد الكود
        $code = $this->generateCode();
        $expiresAt = now()->addMinutes(10);

        $otp = OtpCode::create([
            'mobile'     => $mobile,
            'code'       => $code,
            'purpose'    => $purpose,
            'user_id'    => $context['user_id'] ?? null,
            'tenant_id'  => $context['tenant_id'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => $expiresAt,
        ]);

        // إرسال على الواتساب
        try {
            $this->whatsapp->sendOtp($mobile, $code, $purpose);
            RateLimiter::hit($key, 3600); // ساعة واحدة

            return [
                'success'       => true,
                'expires_at'    => $expiresAt,
                'expires_in'    => 600, // 10 دقائق بالثواني
                'mobile_masked' => $this->maskMobile($mobile),
            ];
        } catch (\Exception $e) {
            Log::error('OTP send failed', ['mobile' => $mobile, 'error' => $e->getMessage()]);
            $otp->delete();
            return [
                'success' => false,
                'error'   => 'فشل إرسال الرسالة. حاول مرّة أخرى.',
            ];
        }
    }

    /**
     * التحقّق من الكود
     */
    public function verifyOtp(string $mobile, string $code, string $purpose): array
    {
        $mobile = $this->normalizeMobile($mobile);

        $otp = OtpCode::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            return [
                'success' => false,
                'error'   => 'لا يوجد كود نشط. اطلب كوداً جديداً.',
            ];
        }

        // تحقّق من المحاولات
        if ($otp->attempts >= 5) {
            $otp->update(['expires_at' => now()->subSecond()]);
            return [
                'success' => false,
                'error'   => 'تجاوزت عدد المحاولات. اطلب كوداً جديداً.',
            ];
        }

        $otp->increment('attempts');

        // مقارنة الكود
        if (!hash_equals($otp->code, $code)) {
            return [
                'success'  => false,
                'error'    => 'الكود غير صحيح',
                'attempts_left' => 5 - $otp->attempts,
            ];
        }

        // ✓ نجح
        $otp->update(['verified_at' => now()]);

        return [
            'success'    => true,
            'otp'        => $otp,
            'user_id'    => $otp->user_id,
            'tenant_id'  => $otp->tenant_id,
        ];
    }

    /**
     * إعادة إرسال الكود (مع تأخير 60 ثانية)
     */
    public function resendOtp(string $mobile, string $purpose, array $context = []): array
    {
        $mobile = $this->normalizeMobile($mobile);

        // تحقّق من آخر محاولة
        $lastOtp = OtpCode::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->orderByDesc('id')->first();

        if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
            $waitSeconds = 60 - $lastOtp->created_at->diffInSeconds(now());
            return [
                'success' => false,
                'error'   => "انتظر {$waitSeconds} ثانية قبل طلب كود جديد",
                'retry_after' => $waitSeconds,
            ];
        }

        return $this->sendOtp($mobile, $purpose, $context);
    }

    /**
     * توليد كود 6 أرقام
     */
    protected function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * معايرة رقم الجوّال السعودي → 9665XXXXXXXX
     */
    public function normalizeMobile(string $mobile): string
    {
        $clean = preg_replace('/[\s\-\+\(\)]/', '', $mobile);
        if (preg_match('/^5\d{8}$/', $clean)) return '966' . $clean;
        if (preg_match('/^05\d{8}$/', $clean)) return '966' . substr($clean, 1);
        if (preg_match('/^966\d{9}$/', $clean)) return $clean;
        if (preg_match('/^\+966\d{9}$/', $clean)) return substr($clean, 1);
        return $clean;
    }

    /**
     * إخفاء جزء من الرقم (لعرض آمن في الواجهة)
     * 9665XXXXXXXX → 9665XX****XX
     */
    public function maskMobile(string $mobile): string
    {
        $clean = $this->normalizeMobile($mobile);
        if (strlen($clean) < 9) return $mobile;
        return substr($clean, 0, 6) . str_repeat('*', 4) . substr($clean, -2);
    }

    /**
     * فحص: هل يوجد كود نشط لهذا الرقم/الغرض
     */
    public function hasActiveOtp(string $mobile, string $purpose): bool
    {
        return OtpCode::where('mobile', $this->normalizeMobile($mobile))
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
