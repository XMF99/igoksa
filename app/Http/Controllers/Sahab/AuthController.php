<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Sahab\Tenant;
use App\Models\Sahab\Plan;
use App\Models\Sahab\Employee;
use App\Models\Sahab\OtpCode;
use App\Services\Sahab\OtpService;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AuthController — مصادقة سحاب الكاملة
 * --------------------------------------------------------------------
 *  مسارات التسجيل/الدخول:
 *
 *  أ) التسجيل الجديد:
 *     1. POST /api/auth/register/start  ← إدخال (اسم المحلّ، الإيميل، الجوّال) → إرسال OTP
 *     2. POST /api/auth/register/verify ← إدخال الكود → إنشاء الحساب + تجربة 14 يوم
 *
 *  ب) تسجيل الدخول:
 *     - بكلمة المرور: POST /api/auth/login (email + password)
 *     - بالـ OTP فقط (بدون كلمة مرور):
 *        1. POST /api/auth/login/otp/start  ← إدخال الجوّال → إرسال كود
 *        2. POST /api/auth/login/otp/verify ← إدخال الكود → token
 *
 *  ج) إعادة تعيين كلمة المرور:
 *     1. POST /api/auth/password/forgot ← إدخال الجوّال → إرسال كود
 *     2. POST /api/auth/password/verify ← الكود + كلمة جديدة
 *
 *  د) دخول الموظّف:
 *     1. POST /api/auth/employee/login/start   ← إدخال الجوّال → كود
 *     2. POST /api/auth/employee/login/verify  ← كود → token
 * --------------------------------------------------------------------
 */
class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otp,
        protected WhatsAppService $whatsapp,
    ) {}

    // ============================================================
    //  أ) التسجيل الجديد — Step 1: إرسال OTP
    // ============================================================
    public function registerStart(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:120',
            'owner_name'    => 'required|string|max:120',
            'mobile'        => 'required|string|max:20|regex:/^(\+?966|0)?5\d{8}$/',
            'email'         => 'required|email|max:120',
            'plan_slug'     => 'nullable|string|exists:sahab_plans,slug',
            'industry'      => 'nullable|string',
            'city'          => 'nullable|string|max:60',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        // التحقّق من عدم وجود حساب مسبق
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'error' => 'email_exists',
                'message' => 'يوجد حساب مسجّل بهذا البريد. سجّل دخول أو استعد كلمة المرور.',
            ], 422);
        }

        if (Tenant::where('mobile', $mobile)->exists()) {
            return response()->json([
                'error' => 'mobile_exists',
                'message' => 'يوجد حساب مسجّل بهذا الجوّال. سجّل دخول مباشرةً.',
            ], 422);
        }

        // حفظ بيانات التسجيل في cache بانتظار التحقّق من OTP
        $registrationToken = Str::random(40);
        cache()->put("registration:{$registrationToken}", $validated + ['mobile' => $mobile], now()->addMinutes(15));

        // إرسال OTP
        $result = $this->otp->sendOtp($mobile, 'register');

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json([
            'success'            => true,
            'message'            => 'تمّ إرسال كود التحقّق على واتساب',
            'mobile_masked'      => $result['mobile_masked'],
            'expires_in'         => $result['expires_in'],
            'registration_token' => $registrationToken,
        ]);
    }

    // ============================================================
    //  أ) التسجيل الجديد — Step 2: التحقّق من الكود + إنشاء الحساب
    // ============================================================
    public function registerVerify(Request $request)
    {
        $validated = $request->validate([
            'registration_token' => 'required|string',
            'code'               => 'required|string|size:6',
        ]);

        // جلب بيانات التسجيل
        $data = cache()->get("registration:{$validated['registration_token']}");
        if (!$data) {
            return response()->json([
                'error' => 'token_expired',
                'message' => 'انتهت جلسة التسجيل. ابدأ من جديد.',
            ], 422);
        }

        // التحقّق من OTP
        $otpResult = $this->otp->verifyOtp($data['mobile'], $validated['code'], 'register');
        if (!$otpResult['success']) {
            return response()->json($otpResult, 422);
        }

        // إنشاء الحساب الكامل
        return DB::transaction(function () use ($data, $validated) {
            // الباقة
            $plan = Plan::where('slug', $data['plan_slug'] ?? 'starter')->first()
                ?? Plan::where('slug', 'starter')->first();

            // كلمة مرور مؤقّتة
            $tempPassword = Str::random(10);

            // إنشاء المستأجر
            $tenant = Tenant::create([
                'name'                => $data['business_name'],
                'owner_name'          => $data['owner_name'],
                'mobile'              => $data['mobile'],
                'email'               => $data['email'],
                'industry'            => $data['industry'] ?? 'other',
                'city'                => $data['city'] ?? 'الرياض',
                'plan_id'             => $plan?->id,
                'subscription_status' => 'trial',
                'trial_ends_at'       => now()->addDays(14),
                'billing_cycle'       => 'monthly',
                'mobile_verified_at'  => now(),
            ]);

            // إنشاء المستخدم
            $user = User::create([
                'name'              => $data['owner_name'],
                'email'             => $data['email'],
                'mobile'            => $data['mobile'],
                'password'          => Hash::make($tempPassword),
                'tenant_id'         => $tenant->id,
                'mobile_verified_at'=> now(),
                'email_verified_at' => null, // سيُحقّق لاحقاً
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('owner');
            }

            // إعدادات أوّليّة (دليل حسابات افتراضي إلخ)
            if (method_exists($tenant, 'setupDefaults')) {
                $tenant->setupDefaults();
            }

            // إرسال رسالة الترحيب
            $this->whatsapp->sendActivation($data['mobile'], [
                'business_name' => $tenant->name,
                'owner_name'    => $data['owner_name'],
                'tenant_code'   => $tenant->code,
                'login_url'     => url('/login'),
                'temp_password' => $tempPassword,
                'plan_name'     => $plan?->name_ar ?? 'البداية',
                'trial_days'    => 14,
            ]);

            // إنشاء token للدخول الفوري
            $token = $user->createToken('registration')->plainTextToken;

            // مسح المؤقّت
            cache()->forget("registration:{$validated['registration_token']}");

            return response()->json([
                'success' => true,
                'message' => '🎉 تمّ إنشاء حسابك بنجاح! تجربة 14 يوم مجّانيّة بانتظارك.',
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'mobile'=> $user->mobile,
                ],
                'tenant' => [
                    'id'   => $tenant->id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                    'plan' => $plan?->slug,
                    'trial_days_left' => 14,
                ],
                'token'    => $token,
                'redirect' => '/app/dashboard',
            ]);
        });
    }

    // ============================================================
    //  ب) تسجيل الدخول بكلمة المرور (التقليدي)
    // ============================================================
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'       => 'required_without:mobile|email',
            'mobile'      => 'required_without:email|string',
            'password'    => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = !empty($validated['email'])
            ? User::where('email', $validated['email'])->first()
            : User::where('mobile', $this->otp->normalizeMobile($validated['mobile']))->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['credentials' => ['البيانات غير صحيحة']]);
        }

        return $this->buildLoginResponse($user, $validated['device_name'] ?? 'web');
    }

    // ============================================================
    //  ب) تسجيل الدخول بـ OTP — Step 1: إرسال الكود
    // ============================================================
    public function loginOtpStart(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|max:20',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $user = User::where('mobile', $mobile)->first();
        if (!$user) {
            return response()->json([
                'error' => 'user_not_found',
                'message' => 'لا يوجد حساب مسجّل بهذا الجوّال. سجّل حساباً جديداً.',
            ], 404);
        }

        $result = $this->otp->sendOtp($mobile, 'login', ['user_id' => $user->id]);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json([
            'success'        => true,
            'message'        => 'تمّ إرسال كود الدخول على واتساب',
            'mobile_masked'  => $result['mobile_masked'],
            'expires_in'     => $result['expires_in'],
        ]);
    }

    // ============================================================
    //  ب) تسجيل الدخول بـ OTP — Step 2: التحقّق
    // ============================================================
    public function loginOtpVerify(Request $request)
    {
        $validated = $request->validate([
            'mobile'      => 'required|string',
            'code'        => 'required|string|size:6',
            'device_name' => 'nullable|string',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $result = $this->otp->verifyOtp($mobile, $validated['code'], 'login');
        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $user = User::where('mobile', $mobile)->firstOrFail();

        return $this->buildLoginResponse($user, $validated['device_name'] ?? 'web');
    }

    // ============================================================
    //  ج) إعادة تعيين كلمة المرور — Step 1
    // ============================================================
    public function passwordForgot(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $user = User::where('mobile', $mobile)->first();
        if (!$user) {
            // لا نكشف عدم وجود الحساب لأمان
            return response()->json([
                'success' => true,
                'message' => 'إذا كان الجوّال مسجّلاً، سيصلك كود على الواتساب',
            ]);
        }

        $result = $this->otp->sendOtp($mobile, 'reset_password', ['user_id' => $user->id]);

        return response()->json([
            'success'        => true,
            'message'        => 'إذا كان الجوّال مسجّلاً، سيصلك كود على الواتساب',
            'mobile_masked'  => $result['mobile_masked'] ?? null,
            'expires_in'     => $result['expires_in'] ?? 600,
        ]);
    }

    // ============================================================
    //  ج) إعادة تعيين كلمة المرور — Step 2
    // ============================================================
    public function passwordVerify(Request $request)
    {
        $validated = $request->validate([
            'mobile'       => 'required|string',
            'code'         => 'required|string|size:6',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $result = $this->otp->verifyOtp($mobile, $validated['code'], 'reset_password');
        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $user = User::where('mobile', $mobile)->firstOrFail();
        $user->update(['password' => Hash::make($validated['new_password'])]);

        // إلغاء كل الـ tokens القديمة
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تمّ تغيير كلمة المرور. سجّل دخول من جديد.',
        ]);
    }

    // ============================================================
    //  د) دخول الموظّف — Step 1
    // ============================================================
    public function employeeLoginStart(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $employee = Employee::where('mobile', $mobile)
            ->where('status', 'active')
            ->withoutGlobalScopes()
            ->first();

        if (!$employee) {
            return response()->json([
                'error'   => 'employee_not_found',
                'message' => 'لا يوجد موظّف مسجّل بهذا الجوّال. تواصل مع المدير.',
            ], 404);
        }

        $result = $this->otp->sendOtp($mobile, 'employee_login', [
            'tenant_id' => $employee->tenant_id,
        ]);

        return response()->json([
            'success'       => $result['success'] ?? false,
            'message'       => 'تمّ إرسال كود الدخول على الواتساب',
            'mobile_masked' => $result['mobile_masked'] ?? null,
            'expires_in'    => $result['expires_in'] ?? null,
            'employee_name' => $employee->full_name,
        ]);
    }

    // ============================================================
    //  د) دخول الموظّف — Step 2
    // ============================================================
    public function employeeLoginVerify(Request $request)
    {
        $validated = $request->validate([
            'mobile'      => 'required|string',
            'code'        => 'required|string|size:6',
            'device_name' => 'nullable|string',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);

        $result = $this->otp->verifyOtp($mobile, $validated['code'], 'employee_login');
        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $employee = Employee::where('mobile', $mobile)
            ->where('status', 'active')
            ->withoutGlobalScopes()
            ->firstOrFail();

        // إيجاد/إنشاء حساب user للموظّف
        $user = User::where('mobile', $mobile)->first();
        if (!$user) {
            $user = User::create([
                'name'              => $employee->full_name,
                'email'             => $employee->email ?? "emp{$employee->id}@" . parse_url(url('/'), PHP_URL_HOST),
                'mobile'            => $mobile,
                'password'          => Hash::make(Str::random(20)), // عشوائي - لا يستخدمه
                'tenant_id'         => $employee->tenant_id,
                'employee_id'       => $employee->id,
                'mobile_verified_at'=> now(),
            ]);
            if (method_exists($user, 'assignRole')) {
                $user->assignRole($employee->role ?? 'cashier');
            }
        }

        return $this->buildLoginResponse($user, $validated['device_name'] ?? 'employee_app');
    }

    // ============================================================
    //  إعادة إرسال OTP
    // ============================================================
    public function resendOtp(Request $request)
    {
        $validated = $request->validate([
            'mobile'  => 'required|string',
            'purpose' => 'required|in:register,login,reset_password,employee_login',
        ]);

        $mobile = $this->otp->normalizeMobile($validated['mobile']);
        $result = $this->otp->resendOtp($mobile, $validated['purpose']);

        return response()->json($result);
    }

    // ============================================================
    //  تسجيل الخروج
    // ============================================================
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'تمّ تسجيل الخروج']);
    }

    // ============================================================
    //  المعلومات الحاليّة
    // ============================================================
    public function me(Request $request)
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        return response()->json([
            'user'   => $user,
            'tenant' => $tenant ? [
                'id'                  => $tenant->id,
                'code'                => $tenant->code,
                'name'                => $tenant->name,
                'logo'                => $tenant->logo,
                'plan'                => $tenant->plan?->slug,
                'features'            => $tenant->plan?->features ?? [],
                'subscription_status' => $tenant->subscription_status,
                'trial_days_left'     => method_exists($tenant, 'trialDaysLeft') ? $tenant->trialDaysLeft() : null,
            ] : null,
        ]);
    }

    // ============================================================
    //  Helpers
    // ============================================================
    protected function buildLoginResponse(User $user, string $deviceName)
    {
        if (!$user->tenant_id) {
            return response()->json([
                'error'   => 'no_tenant_assigned',
                'message' => 'حسابك غير مرتبط بمحلّ.',
            ], 403);
        }

        $tenant = $user->tenant;
        if (!$tenant || !method_exists($tenant, 'isActive') || !$tenant->isActive()) {
            if ($tenant && $tenant->subscription_status === 'trial' && $tenant->trial_ends_at?->isPast()) {
                return response()->json([
                    'error'   => 'trial_expired',
                    'message' => 'انتهت تجربتك. اختر باقة لمتابعة العمل.',
                    'redirect'=> '/app/subscription',
                ], 402);
            }
            return response()->json([
                'error'   => 'subscription_inactive',
                'message' => 'الاشتراك غير نشط. تواصل مع الدعم.',
            ], 402);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'mobile'=> $user->mobile,
                'roles' => method_exists($user, 'roles') ? $user->roles->pluck('name') : [],
            ],
            'tenant' => [
                'id'                  => $tenant->id,
                'code'                => $tenant->code,
                'name'                => $tenant->name,
                'plan'                => $tenant->plan?->slug,
                'features'            => $tenant->plan?->features ?? [],
                'subscription_status' => $tenant->subscription_status,
                'trial_days_left'     => method_exists($tenant, 'trialDaysLeft') ? $tenant->trialDaysLeft() : null,
            ],
            'redirect' => '/app/dashboard',
        ]);
    }
}
