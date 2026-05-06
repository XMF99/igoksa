<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>إعادة تعيين كلمة المرور — سحاب</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: { colors: { teal: { 600: '#0F6E56', 700: '#0A5142', 900: '#083E33' }, copper: { 400: '#D8A66A', 500: '#B07B3F' }, sand: { 100: '#FBF8EF', 200: '#F4EEDF' }, ink: { 700: '#2C3F38', 900: '#0E1F1A' } }, fontFamily: { sans: ['Tajawal', 'sans-serif'], display: ['Reem Kufi', 'serif'] } } } }
</script>
<style>
  body { font-family: 'Tajawal', sans-serif; background: #FBF8EF; }
  [x-cloak] { display: none !important; }
  .otp-input { font-family: monospace; font-size: 24px; letter-spacing: 0.5em; text-align: center; }
</style>
</head>
<body x-data="forgotApp()" class="min-h-screen flex items-center justify-center px-4 py-8">

<div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8">

  <a href="/" class="flex items-center gap-3 mb-8">
    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-2xl">س</div>
    <div class="font-display font-bold text-2xl">سحاب</div>
  </a>

  {{-- Step 1: Enter mobile --}}
  <div x-show="step === 1">
    <h1 class="font-display font-bold text-3xl mb-2">نسيت كلمة المرور؟</h1>
    <p class="text-ink-700/70 mb-6 text-sm">أدخل رقم جوّالك وسنرسل لك كوداً لإعادة التعيين</p>

    <form @submit.prevent="sendCode()" class="space-y-3">
      <div>
        <label class="text-sm font-semibold mb-1 block">رقم الجوّال</label>
        <div class="relative">
          <span class="absolute right-4 top-3 text-ink-700/60 font-mono text-sm">+966</span>
          <input x-model="mobile" required dir="ltr" placeholder="5XXXXXXXX" maxlength="10"
                 @input="mobile = mobile.replace(/[^0-9]/g, '')"
                 class="w-full px-4 py-3 pr-16 rounded-xl border border-sand-200 focus:border-teal-700 outline-none font-mono">
        </div>
      </div>

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading" class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">📱 إرسال كود إعادة التعيين</span>
        <span x-show="loading">جاري الإرسال...</span>
      </button>
    </form>

    <div class="text-center mt-6 pt-6 border-t border-sand-200">
      <a href="/login" class="text-sm text-teal-700 hover:underline">← رجوع لتسجيل الدخول</a>
    </div>
  </div>

  {{-- Step 2: Enter code + new password --}}
  <div x-show="step === 2" x-cloak>
    <div class="text-center mb-6">
      <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-green-50 grid place-items-center text-4xl">🔐</div>
      <h1 class="font-display font-bold text-3xl mb-2">أدخل الكود وكلمة المرور الجديدة</h1>
      <p class="text-ink-700/70 text-sm">تحقّق من واتساب رقم <span class="font-mono font-bold" x-text="mobileMasked"></span></p>
    </div>

    <form @submit.prevent="resetPassword()" class="space-y-4">
      <div>
        <label class="text-sm font-semibold mb-1 block">كود التحقّق</label>
        <input x-model="code" required maxlength="6" minlength="6" dir="ltr" placeholder="123456"
               @input="code = code.replace(/[^0-9]/g, '')"
               class="w-full px-6 py-4 rounded-xl border-2 border-sand-200 focus:border-teal-700 outline-none otp-input">
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">كلمة المرور الجديدة *</label>
        <input x-model="newPassword" type="password" required minlength="8"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none">
        <p class="text-xs text-ink-700/60 mt-1">8 أحرف على الأقل</p>
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">تأكيد كلمة المرور *</label>
        <input x-model="newPasswordConfirm" type="password" required minlength="8"
               class="w-full px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 outline-none">
      </div>

      <div x-show="error" x-cloak class="bg-red-50 border-r-4 border-red-500 text-red-700 p-3 rounded-lg text-sm" x-text="error"></div>

      <button type="submit" :disabled="loading || code.length !== 6 || newPassword !== newPasswordConfirm"
              class="w-full bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-4 rounded-xl font-bold transition">
        <span x-show="!loading">تحديث كلمة المرور →</span>
        <span x-show="loading">جاري التحديث...</span>
      </button>
    </form>

    <button @click="step = 1; error = ''" class="w-full text-ink-700/60 hover:text-ink-900 mt-4 text-sm">
      ← تغيير الرقم
    </button>
  </div>

  {{-- Step 3: Success --}}
  <div x-show="step === 3" x-cloak class="text-center py-8">
    <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-green-500 grid place-items-center text-white text-5xl animate-bounce">✓</div>
    <h1 class="font-display font-bold text-3xl mb-3">تمّ بنجاح!</h1>
    <p class="text-ink-700/70 mb-6">تمّ تحديث كلمة المرور. سجّل دخول من جديد.</p>
    <a href="/login" class="block w-full bg-teal-700 hover:bg-teal-900 text-white py-4 rounded-xl font-bold transition">
      تسجيل الدخول →
    </a>
  </div>

</div>

<script>
function forgotApp() {
  return {
    step: 1,
    loading: false,
    error: '',
    mobile: '',
    mobileMasked: '',
    code: '',
    newPassword: '',
    newPasswordConfirm: '',

    async sendCode() {
      this.loading = true; this.error = '';
      try {
        const res = await fetch('/api/auth/password/forgot', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: JSON.stringify({ mobile: this.mobile }),
        });
        const data = await res.json();
        if (data.success) {
          this.mobileMasked = data.mobile_masked || ('+966 5****' + this.mobile.slice(-2));
          this.step = 2;
        } else {
          this.error = data.message || 'فشل الإرسال';
        }
      } catch (e) { this.error = 'فشل الاتصال'; }
      finally { this.loading = false; }
    },

    async resetPassword() {
      if (this.newPassword !== this.newPasswordConfirm) {
        this.error = 'كلمتا المرور غير متطابقتين';
        return;
      }
      this.loading = true; this.error = '';
      try {
        const res = await fetch('/api/auth/password/verify', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
          body: JSON.stringify({
            mobile: this.mobile,
            code: this.code,
            new_password: this.newPassword,
            new_password_confirmation: this.newPasswordConfirm,
          }),
        });
        const data = await res.json();
        if (data.success) {
          this.step = 3;
        } else {
          this.error = data.message || data.error || 'فشل التحديث';
        }
      } catch (e) { this.error = 'فشل الاتصال'; }
      finally { this.loading = false; }
    },
  };
}
</script>

</body>
</html>
