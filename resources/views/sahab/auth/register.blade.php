<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>التسجيل في سحاب — تجربة 14 يوم مجّانيّة</title>
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
<body x-data="registerApp()" class="min-h-screen flex items-center justify-center px-4 py-8">

<div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative overflow-hidden">

  {{-- Logo --}}
  <a href="/" class="flex items-center gap-3 mb-8">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-2xl">س</div>
    <div>
      <div class="font-display font-bold text-2xl">سحاب</div>
      <div class="text-xs text-ink-700/60">منصّة المحلّات السعوديّة</div>
    </div>
  </a>

  {{-- Progress steps --}}
  <div class="flex items-center gap-2 mb-6">
    <div class="flex-1 h-1.5 rounded-full bg-teal-700"></div>
    <div class="flex-1 h-1.5 rounded-full transition" :class="step >= 2 ? 'bg-teal-700' : 'bg-sand-200'"></div>
    <div class="flex-1 h-1.5 rounded-full transition" :class="step >= 3 ? 'bg-teal-700' : 'bg-sand-200'"></div>
  </div>

  {{-- ============== Step 1: Form ============== --}}
  <div x-show="step === 1">
    <h1 class="font-display font-bold text-3xl mb-2">ابدأ تجربتك المجّانيّة</h1>
    <p class="text-ink-700/70 mb-6 text-sm">14 يوم مجّاناً — بدون بطاقة ائتمان</p>

    <form @submit.prevent="submitStep1()" class="space-y-3">
      <div>
        <label class="text-sm font-semibold mb-1 block">اسم المحلّ *</label>
        <input x-model="form.business_name" required maxlength="120"
               placeholder="مطعم البيت السعودي"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none transition">
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">اسمك الكامل *</label>
        <input x-model="form.owner_name" required maxlength="120"
               placeholder="عبدالله محمد"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none transition">
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">رقم جوّالك *</label>
        <div class="relative">
          <span class="absolute right-4 top-3 text-ink-700/60 font-mono text-sm">+966</span>
          <input x-model="form.mobile" required dir="ltr"
                 placeholder="5XXXXXXXX"
                 maxlength="10"
                 @input="form.mobile = form.mobile.replace(/[^0-9]/g, '')"
                 class="w-full px-4 py-3 pr-16 rounded-xl border border-sand-200 focus:border-teal-700 outline-none transition font-mono">
        </div>
        <p class="text-xs text-ink-700/60 mt-1">سنرسل لك كود التحقّق على الواتساب</p>
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">البريد الإلكتروني *</label>
        <input x-model="form.email" type="email" required maxlength="120"
               placeholder="[email protected]"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none transition">
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">الباقة</label>
        <select x-model="form.plan_slug" class="w-full px-4 py-3 rounded-xl border border-sand-200">
          <option value="starter">البداية — 69 ر.س/شهر</option>
          <option value="pro" selected>الاحترافيّة — 159 ر.س/شهر ⭐</option>
          <option value="enterprise">المؤسّسيّة — 199 ر.س/شهر</option>
        </select>
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">نوع نشاطك</label>
        <select x-model="form.industry" class="w-full px-4 py-3 rounded-xl border border-sand-200">
          <option value="restaurant">مطعم</option>
          <option value="cafe">كافيه</option>
          <option value="grocery">بقالة</option>
          <option value="pharmacy">صيدليّة</option>
          <option value="retail">محلّ تجزئة</option>
          <option value="salon">صالون / تجميل</option>
          <option value="fashion">أزياء</option>
          <option value="electronics">إلكترونيّات</option>
          <option value="service">خدمات</option>
          <option value="other">أخرى</option>
        </select>
      </div>

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading"
              class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition mt-4">
        <span x-show="!loading">إرسال كود التحقّق →</span>
        <span x-show="loading">جاري الإرسال...</span>
      </button>
    </form>

    <p class="text-xs text-ink-700/60 text-center mt-4">
      بالتسجيل، توافق على
      <a href="/terms" class="text-teal-700 underline">الشروط والأحكام</a>
      و
      <a href="/privacy" class="text-teal-700 underline">سياسة الخصوصيّة</a>
    </p>

    <div class="text-center mt-6 pt-6 border-t border-sand-200">
      <span class="text-sm text-ink-700/70">لديك حساب؟</span>
      <a href="/login" class="text-teal-700 font-semibold text-sm hover:underline">سجّل دخول ←</a>
    </div>
  </div>

  {{-- ============== Step 2: OTP Verification ============== --}}
  <div x-show="step === 2" x-cloak>
    <div class="text-center mb-6">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-50 grid place-items-center text-4xl">📱</div>
      <h1 class="font-display font-bold text-3xl mb-2">أدخل كود التحقّق</h1>
      <p class="text-ink-700/70 text-sm">
        أرسلنا كوداً مكوّناً من 6 أرقام على الواتساب رقم<br>
        <span class="font-mono font-bold" x-text="mobileMasked"></span>
      </p>
    </div>

    <form @submit.prevent="submitStep2()" class="space-y-4">
      <input x-model="otpCode" required maxlength="6" minlength="6"
             dir="ltr" placeholder="123456"
             @input="otpCode = otpCode.replace(/[^0-9]/g, '')"
             class="w-full px-6 py-5 rounded-xl border-2 border-sand-200 focus:border-teal-700 outline-none otp-input transition"
             autocomplete="one-time-code">

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading || otpCode.length !== 6"
              class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">تأكيد وإنشاء الحساب ←</span>
        <span x-show="loading">جاري التحقّق...</span>
      </button>
    </form>

    <div class="mt-6 text-center text-sm">
      <p class="text-ink-700/60 mb-2">لم يصلك الكود؟</p>
      <button @click="resendOtp()" :disabled="resendCountdown > 0"
              class="text-teal-700 font-semibold hover:underline disabled:text-gray-400 disabled:cursor-not-allowed">
        <span x-show="resendCountdown <= 0">إعادة الإرسال</span>
        <span x-show="resendCountdown > 0" x-cloak>
          إعادة الإرسال خلال <span x-text="resendCountdown"></span> ث
        </span>
      </button>
    </div>

    <button @click="step = 1; error = ''"
            class="w-full text-ink-700/60 hover:text-ink-900 mt-4 text-sm">
      ← تعديل بياناتي
    </button>
  </div>

  {{-- ============== Step 3: Success ============== --}}
  <div x-show="step === 3" x-cloak class="text-center py-8">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-green-500 grid place-items-center text-white text-5xl animate-bounce">
      ✓
    </div>
    <h1 class="font-display font-bold text-3xl mb-3">🎉 مرحباً بك في سحاب!</h1>
    <p class="text-ink-700/70 mb-6">
      تمّ إنشاء حسابك بنجاح.<br>
      <span class="font-bold text-teal-700">14 يوم تجربة مجّانيّة بانتظارك!</span>
    </p>

    <div class="bg-sand-100 rounded-xl p-4 mb-6 text-right">
      <div class="text-xs text-ink-700/60 mb-1">رمز محلّك</div>
      <div class="font-mono font-bold text-xl" x-text="tenantCode"></div>
    </div>

    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 text-right text-sm">
      <p class="text-green-800">📱 أرسلنا لك على الواتساب:</p>
      <ul class="text-green-700 mt-2 space-y-1 mr-4">
        <li>• كلمة مرور مؤقّتة</li>
        <li>• رابط الدخول السريع</li>
      </ul>
    </div>

    <a href="/app/dashboard"
       class="block w-full bg-teal-700 hover:bg-teal-900 text-white py-4 rounded-xl font-bold transition">
      الدخول للوحة التحكّم →
    </a>
  </div>

</div>

<script>
function registerApp() {
  const urlParams = new URLSearchParams(window.location.search);
  return {
    step: 1,
    loading: false,
    error: '',
    form: {
      business_name: '',
      owner_name: '',
      mobile: '',
      email: '',
      plan_slug: urlParams.get('plan') || 'pro',
      industry: 'restaurant',
      city: '',
    },
    registrationToken: '',
    mobileMasked: '',
    otpCode: '',
    tenantCode: '',
    resendCountdown: 60,
    countdownInterval: null,

    async submitStep1() {
      this.loading = true;
      this.error = '';

      try {
        const res = await fetch('/api/auth/register/start', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(this.form),
        });
        const data = await res.json();

        if (data.success) {
          this.registrationToken = data.registration_token;
          this.mobileMasked = data.mobile_masked;
          this.step = 2;
          this.startResendCountdown();
        } else {
          this.error = data.message || data.error || 'فشل التسجيل';
        }
      } catch (e) {
        this.error = 'فشل الاتصال — تأكّد من الإنترنت وحاول مرّة أخرى';
      } finally {
        this.loading = false;
      }
    },

    async submitStep2() {
      this.loading = true;
      this.error = '';

      try {
        const res = await fetch('/api/auth/register/verify', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({
            registration_token: this.registrationToken,
            code: this.otpCode,
          }),
        });
        const data = await res.json();

        if (data.success) {
          this.tenantCode = data.tenant?.code || '';
          // حفظ الـ token للدخول الفوري
          if (data.token) {
            localStorage.setItem('sahab_token', data.token);
          }
          this.step = 3;
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
          body: JSON.stringify({
            mobile: this.form.mobile,
            purpose: 'register',
          }),
        });
        const data = await res.json();
        if (data.success) {
          this.startResendCountdown();
          alert('✓ تمّ إعادة إرسال الكود');
        } else {
          this.error = data.error || 'فشل الإرسال';
        }
      } catch (e) {
        this.error = 'فشل الاتصال';
      }
    },

    startResendCountdown() {
      this.resendCountdown = 60;
      if (this.countdownInterval) clearInterval(this.countdownInterval);
      this.countdownInterval = setInterval(() => {
        this.resendCountdown--;
        if (this.resendCountdown <= 0) {
          clearInterval(this.countdownInterval);
        }
      }, 1000);
    },
  };
}
</script>

</body>
</html>
