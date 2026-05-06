<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>تسجيل الدخول — سحاب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        teal: { 600: '#0F6E56', 700: '#0A5142', 900: '#083E33' },
        copper: { 400: '#D8A66A', 500: '#B07B3F' },
        sand: { 100: '#FBF8EF', 200: '#F4EEDF' },
        ink: { 700: '#2C3F38', 900: '#0E1F1A' },
      },
      fontFamily: {
        sans: ['Tajawal', 'sans-serif'],
        display: ['Reem Kufi', 'serif'],
      },
    },
  },
}
</script>
<style>
  body { font-family: 'Tajawal', sans-serif; background: #FBF8EF; }
  [x-cloak] { display: none !important; }
  .otp-input { font-family: monospace; font-size: 24px; letter-spacing: 0.5em; text-align: center; }
</style>
</head>
<body x-data="loginApp()" class="min-h-screen flex items-center justify-center px-4 py-8">

<div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8">

  <a href="/" class="flex items-center gap-3 mb-8">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-2xl">س</div>
    <div>
      <div class="font-display font-bold text-2xl">سحاب</div>
      <div class="text-xs text-ink-700/60">منصّة المحلّات</div>
    </div>
  </a>

  {{-- ============== Choose method ============== --}}
  <div x-show="step === 'choose'">
    <h1 class="font-display font-bold text-3xl mb-2">تسجيل الدخول</h1>
    <p class="text-ink-700/70 mb-8 text-sm">اختر طريقة الدخول المناسبة</p>

    <div class="space-y-3">
      <button @click="step = 'otp'"
              class="w-full bg-green-500 hover:bg-green-600 text-white p-5 rounded-2xl text-right transition group">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-xl bg-white/20 grid place-items-center text-3xl">📱</div>
          <div class="flex-1">
            <div class="font-bold text-lg">دخول بكود الواتساب</div>
            <div class="text-sm opacity-90">الأسرع — بدون كلمة مرور</div>
          </div>
          <div class="text-xl group-hover:-translate-x-1 transition">←</div>
        </div>
      </button>

      <button @click="step = 'password'"
              class="w-full bg-white border-2 border-sand-200 hover:border-teal-700 p-5 rounded-2xl text-right transition group">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-xl bg-sand-100 grid place-items-center text-3xl">🔑</div>
          <div class="flex-1">
            <div class="font-bold text-lg">دخول بكلمة المرور</div>
            <div class="text-sm text-ink-700/60">الطريقة التقليديّة</div>
          </div>
          <div class="text-xl text-ink-700/40 group-hover:-translate-x-1 transition">←</div>
        </div>
      </button>
    </div>

    <div class="text-center mt-8 pt-6 border-t border-sand-200">
      <span class="text-sm text-ink-700/70">ليس لديك حساب؟</span>
      <a href="/register" class="text-teal-700 font-semibold text-sm hover:underline">سجّل الآن — 14 يوم مجّاناً</a>
    </div>
  </div>

  {{-- ============== Password Login ============== --}}
  <div x-show="step === 'password'" x-cloak>
    <button @click="step = 'choose'; error = ''" class="text-ink-700/60 hover:text-ink-900 mb-4 text-sm">← رجوع</button>
    <h1 class="font-display font-bold text-3xl mb-2">دخول بكلمة المرور</h1>
    <p class="text-ink-700/70 mb-6 text-sm">ادخل بياناتك للمتابعة</p>

    <form @submit.prevent="loginWithPassword()" class="space-y-3">
      <div>
        <label class="text-sm font-semibold mb-1 block">البريد الإلكتروني أو الجوّال</label>
        <input x-model="loginIdentifier" required
               placeholder="[email protected] أو 5XXXXXXXX"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none">
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">كلمة المرور</label>
        <input x-model="password" type="password" required
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none">
      </div>

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading"
              class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">دخول</span>
        <span x-show="loading">جاري الدخول...</span>
      </button>
    </form>

    <div class="text-center mt-4">
      <a href="/forgot-password" class="text-sm text-teal-700 hover:underline">نسيت كلمة المرور؟</a>
    </div>
  </div>

  {{-- ============== OTP Login Step 1: enter mobile ============== --}}
  <div x-show="step === 'otp'" x-cloak>
    <button @click="step = 'choose'; error = ''" class="text-ink-700/60 hover:text-ink-900 mb-4 text-sm">← رجوع</button>
    <div class="text-center mb-6">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-50 grid place-items-center text-4xl">📱</div>
      <h1 class="font-display font-bold text-3xl mb-2">دخول سريع</h1>
      <p class="text-ink-700/70 text-sm">سنرسل كوداً على الواتساب لدخول آمن بدون كلمة مرور</p>
    </div>

    <form @submit.prevent="sendOtp()" class="space-y-3">
      <div>
        <label class="text-sm font-semibold mb-1 block">رقم جوّالك</label>
        <div class="relative">
          <span class="absolute right-4 top-3 text-ink-700/60 font-mono text-sm">+966</span>
          <input x-model="mobile" required dir="ltr"
                 placeholder="5XXXXXXXX" maxlength="10"
                 @input="mobile = mobile.replace(/[^0-9]/g, '')"
                 class="w-full px-4 py-3 pr-16 rounded-xl border border-sand-200 focus:border-teal-700 outline-none font-mono">
        </div>
      </div>

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading"
              class="w-full bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">📱 إرسال الكود على واتساب</span>
        <span x-show="loading">جاري الإرسال...</span>
      </button>
    </form>
  </div>

  {{-- ============== OTP Login Step 2: enter code ============== --}}
  <div x-show="step === 'otp_verify'" x-cloak>
    <div class="text-center mb-6">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-50 grid place-items-center text-4xl">🔐</div>
      <h1 class="font-display font-bold text-3xl mb-2">أدخل الكود</h1>
      <p class="text-ink-700/70 text-sm">
        أرسلنا كوداً على<br>
        <span class="font-mono font-bold" x-text="mobileMasked"></span>
      </p>
    </div>

    <form @submit.prevent="verifyOtp()" class="space-y-4">
      <input x-model="otpCode" required maxlength="6" minlength="6"
             dir="ltr" placeholder="123456"
             @input="otpCode = otpCode.replace(/[^0-9]/g, '')"
             class="w-full px-6 py-5 rounded-xl border-2 border-sand-200 focus:border-teal-700 outline-none otp-input"
             autocomplete="one-time-code">

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading || otpCode.length !== 6"
              class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">دخول →</span>
        <span x-show="loading">جاري التحقّق...</span>
      </button>
    </form>

    <div class="mt-6 text-center text-sm">
      <p class="text-ink-700/60 mb-2">لم يصلك الكود؟</p>
      <button @click="resendOtp()" :disabled="resendCountdown > 0"
              class="text-teal-700 font-semibold hover:underline disabled:text-gray-400">
        <span x-show="resendCountdown <= 0">إعادة الإرسال</span>
        <span x-show="resendCountdown > 0" x-cloak>
          إعادة الإرسال خلال <span x-text="resendCountdown"></span> ث
        </span>
      </button>
    </div>

    <button @click="step = 'otp'; error = ''" class="w-full text-ink-700/60 hover:text-ink-900 mt-4 text-sm">
      ← تغيير الرقم
    </button>
  </div>

</div>

<script>
function loginApp() {
  return {
    step: 'choose',
    loading: false,
    error: '',
    loginIdentifier: '',
    password: '',
    mobile: '',
    mobileMasked: '',
    otpCode: '',
    resendCountdown: 0,
    countdownInterval: null,

    async loginWithPassword() {
      this.loading = true;
      this.error = '';

      try {
        const isEmail = this.loginIdentifier.includes('@');
        const payload = isEmail
          ? { email: this.loginIdentifier, password: this.password, device_name: 'web' }
          : { mobile: this.loginIdentifier, password: this.password, device_name: 'web' };

        const res = await fetch('/api/auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
          if (data.token) localStorage.setItem('sahab_token', data.token);
          window.location.href = data.redirect || '/app/dashboard';
        } else {
          this.error = data.message || 'البيانات غير صحيحة';
        }
      } catch (e) {
        this.error = 'فشل الاتصال';
      } finally {
        this.loading = false;
      }
    },

    async sendOtp() {
      this.loading = true;
      this.error = '';

      try {
        const res = await fetch('/api/auth/login/otp/start', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ mobile: this.mobile }),
        });
        const data = await res.json();

        if (data.success) {
          this.mobileMasked = data.mobile_masked;
          this.step = 'otp_verify';
          this.startResendCountdown();
        } else {
          this.error = data.message || 'فشل الإرسال';
        }
      } catch (e) {
        this.error = 'فشل الاتصال';
      } finally {
        this.loading = false;
      }
    },

    async verifyOtp() {
      this.loading = true;
      this.error = '';

      try {
        const res = await fetch('/api/auth/login/otp/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            mobile: this.mobile,
            code: this.otpCode,
            device_name: 'web',
          }),
        });
        const data = await res.json();

        if (data.success) {
          if (data.token) localStorage.setItem('sahab_token', data.token);
          window.location.href = data.redirect || '/app/dashboard';
        } else {
          this.error = data.message || data.error || 'الكود غير صحيح';
          if (data.attempts_left) {
            this.error += ` (${data.attempts_left} محاولات متبقّية)`;
          }
        }
      } catch (e) {
        this.error = 'فشل الاتصال';
      } finally {
        this.loading = false;
      }
    },

    async resendOtp() {
      try {
        const res = await fetch('/api/auth/otp/resend', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ mobile: this.mobile, purpose: 'login' }),
        });
        const data = await res.json();
        if (data.success) {
          this.startResendCountdown();
          alert('✓ تمّ الإرسال');
        }
      } catch (e) {}
    },

    startResendCountdown() {
      this.resendCountdown = 60;
      if (this.countdownInterval) clearInterval(this.countdownInterval);
      this.countdownInterval = setInterval(() => {
        this.resendCountdown--;
        if (this.resendCountdown <= 0) clearInterval(this.countdownInterval);
      }, 1000);
    },
  };
}
</script>

</body>
</html>
