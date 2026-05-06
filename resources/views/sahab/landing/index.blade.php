{{-- ============================================================= --}}
{{--  Sahab Landing Page (Blade version with database-driven plans)  --}}
{{-- ============================================================= --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>سَحاب — كاشير ذكي للمحلّات السعوديّة</title>

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
</head>
<body class="bg-sand-100 text-ink-900 font-sans">

{{-- Nav --}}
<nav class="sticky top-0 bg-sand-100/90 backdrop-blur-md border-b border-ink-900/10 z-50">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center gap-6">
    <a href="/" class="flex items-center gap-2">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-xl">س</div>
      <span class="font-display font-bold text-2xl">سحاب</span>
    </a>
    <div class="hidden md:flex gap-7 mr-auto text-sm font-semibold text-ink-700">
      <a href="#features" class="hover:text-teal-700">الميزات</a>
      <a href="#delivery" class="hover:text-teal-700">التكاملات</a>
      <a href="#pricing" class="hover:text-teal-700">الباقات</a>
      <a href="/login" class="hover:text-teal-700">دخول</a>
    </div>
    <a href="/register" class="bg-teal-700 hover:bg-teal-900 text-white px-5 py-2.5 rounded-full font-bold text-sm transition shadow-lg shadow-teal-900/20">
      ابدأ مجاناً
    </a>
  </div>
</nav>

{{-- Hero --}}
<section class="py-20 px-6 text-center relative overflow-hidden">
  <span class="inline-block bg-copper-400/15 text-copper-500 px-4 py-1.5 rounded-full text-xs font-bold mb-6">
    ⭐ تجربة مجّانيّة 14 يوم — بدون بطاقة
  </span>
  <h1 class="font-display text-4xl md:text-6xl font-bold leading-tight mb-6 max-w-4xl mx-auto">
    كاشير ذكي،<br>
    ربط مع <span class="text-teal-700 relative">
      كل تطبيقات التوصيل
      <span class="absolute bottom-0 right-0 left-0 h-2 bg-copper-400/50 -z-10"></span>
    </span><br>
    في نظام واحد
  </h1>
  <p class="text-lg text-ink-700/80 max-w-2xl mx-auto mb-10 leading-relaxed">
    نظام نقطة بيع سعودي متكامل — يربط مع هنقرستيشن وجاهز وتوصيل ومرسول، مع محاسبة كاملة وذكاء اصطناعي وفواتير زاتكا.
  </p>
  <div class="flex gap-3 justify-center flex-wrap">
    <a href="/register" class="bg-teal-700 hover:bg-teal-900 text-white px-7 py-3.5 rounded-full font-bold transition shadow-lg shadow-teal-900/30 hover:-translate-y-0.5">
      ابدأ تجربتك المجّانيّة ←
    </a>
    <a href="#features" class="bg-white hover:border-teal-700 hover:text-teal-700 text-ink-900 px-7 py-3.5 rounded-full font-bold transition border border-ink-900/10">
      شاهد الميزات
    </a>
  </div>
  <div class="mt-10 flex justify-center gap-6 flex-wrap text-sm text-ink-700/70">
    <span>✓ بدون عمولة على المبيعات</span>
    <span>✓ متوافق مع زاتكا</span>
    <span>✓ يعمل بدون إنترنت</span>
    <span>✓ دعم عربي 24/7</span>
  </div>
</section>

{{-- Features --}}
<section id="features" class="py-20 px-6 bg-white">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-14">
      <div class="text-xs text-copper-500 font-bold uppercase tracking-wider mb-3">كل ما تحتاجه</div>
      <h2 class="font-display text-4xl font-bold mb-4">منصّة واحدة بدل 10 أنظمة</h2>
      <p class="text-ink-700/70 max-w-xl mx-auto">كل شي يحتاجه محلّك في مكان واحد — بدون اشتراكات منفصلة.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      @foreach([
        ['🛒', 'كاشير سريع', 'بيع بضغطة زرّ. يعمل على الجوّال والآيباد والكمبيوتر. حتى لو انقطع الإنترنت يستمر العمل.'],
        ['🚀', 'ربط مع تطبيقات التوصيل', 'هنقرستيشن، جاهز، توصيل، مرسول — كل الطلبات تجي لكاشير واحد.'],
        ['📊', 'محاسبة تلقائيّة', 'القيود المحاسبيّة تتسجّل تلقائياً مع كل فاتورة. ضريبة، أرباح، مصروفات.'],
        ['📸', 'قراءة فواتير ذكيّة', 'صوّر فاتورة المورد بكاميرتك، الذكاء الاصطناعي يستخرج البيانات.'],
        ['🤖', 'مساعد ذكي', 'اسأل بالعربي: "كم بعت اليوم؟" ويجاوبك بدقّة من بيانات محلّك.'],
        ['🧾', 'متوافق مع زاتكا', 'فواتير ضريبيّة بـ QR Code. جاهز للمرحلتين الأولى والثانية.'],
        ['👥', 'إدارة الموظّفين', 'حضور بـ GPS، توثيق الإقامة والتأمين، تنبيهات قبل انتهاء الوثائق.'],
        ['🎁', 'برنامج ولاء', '3 فئات (برونزي، فضّي، ذهبي)، نقاط لكل عمليّة، Apple Wallet.'],
        ['🚨', 'كاشف احتيال', 'إشعار فوري لو فيه إلغاءات أو خصومات شاذّة من أي موظّف.'],
      ] as $i => $f)
        <div class="bg-sand-100 rounded-2xl p-6 hover:shadow-xl hover:-translate-y-1 transition border border-transparent hover:border-teal-700/20">
          <div class="w-13 h-13 mb-4 grid place-items-center text-3xl bg-{{ $i % 3 == 1 ? 'copper-400/15' : 'teal-700/10' }} rounded-xl">{{ $f[0] }}</div>
          <h3 class="font-display text-xl font-bold mb-2">{{ $f[1] }}</h3>
          <p class="text-sm text-ink-700/70 leading-relaxed">{{ $f[2] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- Delivery apps --}}
<section id="delivery" class="py-20 px-6 bg-sand-200">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-14">
      <div class="text-xs text-copper-500 font-bold uppercase tracking-wider mb-3">تكاملات جاهزة</div>
      <h2 class="font-display text-4xl font-bold mb-4">اربط محلّك بكل تطبيقات التوصيل</h2>
      <p class="text-ink-700/70 max-w-xl mx-auto">الطلبات تجي من كل المنصّات لكاشير واحد. اقبل، حضّر، سلّم.</p>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-4 lg:grid-cols-8 gap-4">
      @foreach([
        ['#FF6900', 'هـ', 'هنقرستيشن'],
        ['#A02123', 'جا', 'جاهز'],
        ['#00A859', 'تو', 'توصيل'],
        ['#3F2A75', 'مر', 'مرسول'],
        ['#000000', 'تش', 'تشيفز'],
        ['#FF3B30', 'ني', 'نينجا'],
        ['#FFCC00', 'تي', 'تو يو'],
        ['#FF5A00', 'طل', 'طلبات'],
      ] as $p)
        <div class="bg-white rounded-2xl p-5 text-center hover:scale-105 transition border border-ink-900/5">
          <div class="w-14 h-14 mx-auto mb-3 rounded-xl grid place-items-center text-white font-display font-bold text-xl"
               style="background: {{ $p[0] }}; @if($p[0]==='#FFCC00') color: #0E1F1A; @endif">{{ $p[1] }}</div>
          <div class="font-bold text-sm">{{ $p[2] }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- WhatsApp Store showcase --}}
<section id="whatsapp-store" class="py-20 px-6 bg-gradient-to-br from-green-600 to-teal-700 text-white relative overflow-hidden">
  <div class="absolute -top-20 -right-20 text-[300px] opacity-5 select-none">📱</div>

  <div class="max-w-6xl mx-auto relative">
    <div class="grid md:grid-cols-2 gap-12 items-center">
      <div>
        <span class="inline-block bg-copper-400 text-ink-900 px-4 py-1 rounded-full text-xs font-bold mb-4">
          🌟 حصري للباقة المؤسّسيّة
        </span>
        <h2 class="font-display text-4xl md:text-5xl font-bold mb-5 leading-tight">
          متجرك الإلكتروني<br>عبر <span class="text-copper-400">الواتساب</span>
        </h2>
        <p class="text-lg opacity-90 leading-relaxed mb-6">
          كل تاجر عنده متجر إلكتروني خاص بشكل احترافي. العميل يدخل، يختار، يضيف للسلّة، ويرسل الطلب لك مباشرةً على الواتساب — بدون أي تطبيق إضافي.
        </p>

        <div class="space-y-3 mb-8">
          @foreach([
            ['🔗', 'رابط مخصّص', 'sahab.sa/store/your-name'],
            ['🎨', '6 ثيمات احترافيّة', 'اختر التصميم اللي يعكس هويّتك'],
            ['🛒', 'سلّة ذكيّة', 'كوبونات، أسعار، توصيل، استلام'],
            ['📱', 'PWA — يعمل كتطبيق', 'العميل يضيفه على شاشة جوّاله'],
            ['📊', 'إحصائيّات كاملة', 'زيارات، طلبات، إيرادات'],
          ] as $f)
            <div class="flex gap-3">
              <span class="text-2xl">{{ $f[0] }}</span>
              <div>
                <div class="font-bold">{{ $f[1] }}</div>
                <div class="text-sm opacity-80">{{ $f[2] }}</div>
              </div>
            </div>
          @endforeach
        </div>

        <a href="#pricing" class="inline-flex bg-copper-400 hover:bg-copper-500 text-ink-900 px-7 py-3.5 rounded-full font-bold transition shadow-2xl">
          احصل عليه مع الباقة المؤسّسيّة ←
        </a>
      </div>

      {{-- Mockup --}}
      <div class="relative">
        <div class="bg-ink-900 rounded-[40px] p-3 shadow-2xl max-w-sm mx-auto">
          <div class="bg-white rounded-[32px] overflow-hidden">
            <div class="bg-teal-700 text-white p-4 flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-copper-400 grid place-items-center text-ink-900 font-bold">م</div>
              <div>
                <div class="font-bold text-sm">مطعم البيت السعودي</div>
                <div class="text-xs opacity-80">أشهى الأكلات بأيدي ماهرة</div>
              </div>
            </div>
            <div class="p-4 grid grid-cols-2 gap-2 bg-gray-50">
              <div class="bg-white rounded-xl p-3 shadow-sm">
                <div class="aspect-square bg-orange-200 rounded-lg mb-2 grid place-items-center text-3xl">🍛</div>
                <div class="text-xs font-bold">كبسة لحم</div>
                <div class="text-xs text-teal-700 font-mono mt-1">45 ر.س</div>
              </div>
              <div class="bg-white rounded-xl p-3 shadow-sm">
                <div class="aspect-square bg-yellow-200 rounded-lg mb-2 grid place-items-center text-3xl">🍗</div>
                <div class="text-xs font-bold">مندي دجاج</div>
                <div class="text-xs text-teal-700 font-mono mt-1">38 ر.س</div>
              </div>
              <div class="bg-white rounded-xl p-3 shadow-sm">
                <div class="aspect-square bg-green-200 rounded-lg mb-2 grid place-items-center text-3xl">🥗</div>
                <div class="text-xs font-bold">سلطة</div>
                <div class="text-xs text-teal-700 font-mono mt-1">18 ر.س</div>
              </div>
              <div class="bg-white rounded-xl p-3 shadow-sm">
                <div class="aspect-square bg-blue-200 rounded-lg mb-2 grid place-items-center text-3xl">🥤</div>
                <div class="text-xs font-bold">عصير</div>
                <div class="text-xs text-teal-700 font-mono mt-1">12 ر.س</div>
              </div>
            </div>
            <div class="p-3 bg-green-600 text-white text-center text-sm font-bold flex items-center justify-center gap-2">
              <span>📱 إرسال الطلب على واتساب</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- Pricing --}}
<section id="pricing" class="py-20 px-6">
  <div class="max-w-6xl mx-auto">
    <div class="text-center mb-14">
      <div class="text-xs text-copper-500 font-bold uppercase tracking-wider mb-3">الأسعار</div>
      <h2 class="font-display text-4xl font-bold mb-4">3 باقات — اختر الأنسب لمحلّك</h2>
      <p class="text-ink-700/70 max-w-xl mx-auto">كل الباقات تشمل: تحديثات + دعم فني + استضافة + نسخ احتياطيّة يوميّة.</p>
    </div>

    <div class="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
      @foreach($plans as $plan)
        <div class="rounded-3xl p-8 relative transition hover:-translate-y-1
                   {{ $plan->is_popular
                        ? 'bg-gradient-to-b from-teal-700 to-teal-900 text-white scale-105 shadow-2xl shadow-teal-900/30'
                        : 'bg-white border border-ink-900/10 hover:shadow-xl' }}">

          @if($plan->is_popular)
            <div class="absolute -top-3 right-6 bg-copper-500 text-ink-900 px-3 py-1 rounded-full text-xs font-bold">
              🔥 الأكثر شعبيّة
            </div>
          @endif

          <h3 class="font-display text-2xl font-bold mb-1">{{ $plan->name_ar }}</h3>
          <p class="text-sm {{ $plan->is_popular ? 'text-white/70' : 'text-ink-700/70' }} mb-6">
            {{ $plan->description_ar }}
          </p>

          <div class="flex items-baseline gap-2 mb-6">
            <span class="font-display text-5xl font-bold">{{ number_format($plan->price_monthly, 0) }}</span>
            <span class="{{ $plan->is_popular ? 'text-white/70' : 'text-ink-700/60' }}">ر.س</span>
            <span class="text-sm {{ $plan->is_popular ? 'text-white/60' : 'text-ink-700/60' }} mr-auto">/شهرياً</span>
          </div>

          <a href="/register?plan={{ $plan->slug }}"
                  class="block text-center w-full py-3.5 rounded-xl font-bold mb-6 transition
                         {{ $plan->is_popular
                            ? 'bg-copper-500 text-ink-900 hover:bg-copper-400'
                            : 'bg-teal-700 text-white hover:bg-teal-900' }}">
            جرّب 14 يوم مجاناً
          </a>

          <div class="space-y-2 text-sm border-t {{ $plan->is_popular ? 'border-white/15' : 'border-ink-900/10' }} pt-5">
            @foreach($plan->features as $feature)
              <div class="flex gap-2">
                <span class="{{ $plan->is_popular ? 'text-copper-400' : 'text-green-600' }}">✓</span>
                <span>{{ $featureLabels[$feature] ?? $feature }}</span>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

<footer class="bg-ink-900 text-white/70 py-12 px-6">
  <div class="max-w-6xl mx-auto text-center">
    <div class="flex items-center gap-2 justify-center mb-4">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-xl">س</div>
      <span class="font-display font-bold text-2xl text-white">سحاب</span>
    </div>
    <p class="text-sm">© {{ date('Y') }} منصّة سحاب · جميع الحقوق محفوظة</p>
  </div>
</footer>


</body>
</html>
