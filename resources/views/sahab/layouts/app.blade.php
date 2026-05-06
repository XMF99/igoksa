{{-- ============================================================= --}}
{{--  Sahab Main Layout — لوحة التحكّم الرئيسيّة                       --}}
{{--  استخدم: @extends('sahab.layouts.app')                          --}}
{{-- ============================================================= --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>@yield('title', 'سحاب') · {{ auth()->user()->tenant->name ?? 'سحاب' }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        teal: { 600: '#0F6E56', 700: '#0A5142', 900: '#083E33' },
        copper: { 400: '#D8A66A', 500: '#B07B3F', 600: '#92642F' },
        sand: { 100: '#FBF8EF', 200: '#F4EEDF', 300: '#EDE3CB' },
        ink: { 700: '#2C3F38', 900: '#0E1F1A' },
      },
      fontFamily: {
        sans: ['Tajawal', 'sans-serif'],
        display: ['Reem Kufi', 'serif'],
        mono: ['IBM Plex Sans Arabic', 'sans-serif'],
      },
    },
  },
}
</script>

<style>
  body { background: #FBF8EF; font-family: 'Tajawal', sans-serif; }
  .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 14px; border-radius: 10px; color: #2C3F38; font-weight: 500; transition: all 0.15s; }
  .nav-item:hover { background: rgba(15,110,86,0.08); color: #0A5142; }
  .nav-item.active { background: #0A5142; color: white; }
  .nav-item .icon { font-size: 20px; width: 22px; text-align: center; }
</style>

@stack('head')
</head>
<body class="text-ink-900 min-h-screen">

<div class="flex min-h-screen">

  {{-- Sidebar --}}
  <aside class="w-64 bg-white border-l border-sand-200 hidden lg:flex flex-col">
    <div class="p-5 border-b border-sand-200">
      <a href="/" class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold text-2xl">س</div>
        <div>
          <div class="font-display font-bold text-xl">سحاب</div>
          <div class="text-xs text-ink-700/60 truncate max-w-[140px]">{{ auth()->user()->tenant->name ?? '' }}</div>
        </div>
      </a>
    </div>

    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
      <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <span class="icon">📊</span>
        <span>الرئيسيّة</span>
      </a>
      <a href="{{ route('pos') }}" class="nav-item {{ request()->routeIs('pos') ? 'active' : '' }}">
        <span class="icon">🛒</span>
        <span>الكاشير</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">المبيعات</div>
      <a href="{{ route('delivery') }}" class="nav-item {{ request()->routeIs('delivery*') ? 'active' : '' }}">
        <span class="icon">🚀</span>
        <span>تكاملات التوصيل</span>
      </a>
      <a href="{{ route('kds') }}" class="nav-item {{ request()->routeIs('kds') ? 'active' : '' }}">
        <span class="icon">🍳</span>
        <span>شاشة المطبخ</span>
      </a>
      <a href="{{ route('customers') }}" class="nav-item {{ request()->routeIs('customers') ? 'active' : '' }}">
        <span class="icon">👤</span>
        <span>العملاء</span>
      </a>

      @if(auth()->user()->tenant?->canAccessFeature('whatsapp_store'))
        <a href="{{ route('store.settings') }}" class="nav-item {{ request()->routeIs('store.settings') ? 'active' : '' }}">
          <span class="icon">🛍</span>
          <span>متجر الواتساب</span>
          <span class="text-xs bg-copper-400 text-ink-900 px-1.5 py-0.5 rounded-full font-bold mr-auto">جديد</span>
        </a>
        <a href="{{ route('store.menu') }}" class="nav-item {{ request()->routeIs('store.menu') ? 'active' : '' }}" style="padding-right: 32px; font-size: 13px;">
          <span class="icon">📋</span>
          <span>محرّر المنيو</span>
        </a>
        <a href="{{ route('themes.marketplace') }}" class="nav-item {{ request()->routeIs('themes.*') ? 'active' : '' }}" style="padding-right: 32px; font-size: 13px;">
          <span class="icon">🎨</span>
          <span>متجر الثيمات</span>
        </a>
      @endif

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">المخزون</div>
      <a href="{{ route('products') }}" class="nav-item {{ request()->routeIs('products') ? 'active' : '' }}">
        <span class="icon">📦</span>
        <span>المنتجات</span>
      </a>
      <a href="{{ route('purchases') }}" class="nav-item {{ request()->routeIs('purchases') ? 'active' : '' }}">
        <span class="icon">🛍</span>
        <span>المشتريات</span>
      </a>
      <a href="{{ route('suppliers') }}" class="nav-item {{ request()->routeIs('suppliers') ? 'active' : '' }}">
        <span class="icon">🏭</span>
        <span>الموردون</span>
      </a>
      <a href="{{ route('ocr') }}" class="nav-item {{ request()->routeIs('ocr') ? 'active' : '' }}">
        <span class="icon">📸</span>
        <span>قراءة الفواتير (OCR)</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">الموارد البشريّة</div>
      <a href="{{ route('employees') }}" class="nav-item {{ request()->routeIs('employees') ? 'active' : '' }}">
        <span class="icon">👥</span>
        <span>الموظّفون</span>
      </a>
      <a href="{{ route('attendance') }}" class="nav-item {{ request()->routeIs('attendance') ? 'active' : '' }}">
        <span class="icon">🕐</span>
        <span>الحضور والانصراف</span>
      </a>
      <a href="{{ route('leaves') }}" class="nav-item {{ request()->routeIs('leaves') ? 'active' : '' }}">
        <span class="icon">📅</span>
        <span>الإجازات</span>
      </a>
      <a href="{{ route('documents') }}" class="nav-item {{ request()->routeIs('documents') ? 'active' : '' }}">
        <span class="icon">📄</span>
        <span>الوثائق</span>
      </a>
      <a href="{{ route('payroll') }}" class="nav-item {{ request()->routeIs('payroll') ? 'active' : '' }}">
        <span class="icon">💰</span>
        <span>الرواتب</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">التشغيل</div>
      <a href="{{ route('work_orders') }}" class="nav-item {{ request()->routeIs('work_orders') ? 'active' : '' }}">
        <span class="icon">🔧</span>
        <span>أوامر الشغل</span>
      </a>
      <a href="{{ route('bookings') }}" class="nav-item {{ request()->routeIs('bookings') ? 'active' : '' }}">
        <span class="icon">📅</span>
        <span>الحجوزات</span>
      </a>
      <a href="{{ route('time_tracking') }}" class="nav-item {{ request()->routeIs('time_tracking') ? 'active' : '' }}">
        <span class="icon">⏱</span>
        <span>تتبّع الوقت</span>
      </a>
      <a href="{{ route('rentals') }}" class="nav-item {{ request()->routeIs('rentals') ? 'active' : '' }}">
        <span class="icon">🏢</span>
        <span>الإيجارات</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">العملاء المتقدّمة</div>
      <a href="{{ route('memberships') }}" class="nav-item {{ request()->routeIs('memberships') ? 'active' : '' }}">
        <span class="icon">⭐</span>
        <span>الاشتراكات والعضويّات</span>
      </a>
      <a href="{{ route('installments') }}" class="nav-item {{ request()->routeIs('installments') ? 'active' : '' }}">
        <span class="icon">📊</span>
        <span>الأقساط</span>
      </a>
      <a href="{{ route('sales_targets') }}" class="nav-item {{ request()->routeIs('sales_targets') ? 'active' : '' }}">
        <span class="icon">🎯</span>
        <span>المبيعات المستهدفة</span>
      </a>
      <a href="{{ route('offers') }}" class="nav-item {{ request()->routeIs('offers') ? 'active' : '' }}">
        <span class="icon">🎁</span>
        <span>العروض والخصومات</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">المحاسبة</div>
      <a href="{{ route('accounting.chart') }}" class="nav-item">
        <span class="icon">📊</span>
        <span>دليل الحسابات</span>
      </a>
      <a href="{{ route('accounting.journal') }}" class="nav-item">
        <span class="icon">📓</span>
        <span>القيود اليوميّة</span>
      </a>
      <a href="{{ route('accounting.checks') }}" class="nav-item">
        <span class="icon">📜</span>
        <span>الشيكات</span>
      </a>
      <a href="{{ route('accounting.assets') }}" class="nav-item">
        <span class="icon">🏢</span>
        <span>الأصول الثابتة</span>
      </a>

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">المبيعات المتقدّمة</div>
      @if(auth()->user()->tenant?->canAccessFeature('sales_targets'))
        <a href="/app/sales-targets" class="nav-item {{ request()->is('app/sales-targets*') ? 'active' : '' }}">
          <span class="icon">🎯</span>
          <span>المبيعات المستهدفة</span>
        </a>
      @endif
      @if(auth()->user()->tenant?->canAccessFeature('installments'))
        <a href="/app/installments" class="nav-item {{ request()->is('app/installments*') ? 'active' : '' }}">
          <span class="icon">💳</span>
          <span>الأقساط</span>
        </a>
      @endif
      @if(auth()->user()->tenant?->canAccessFeature('memberships'))
        <a href="/app/memberships" class="nav-item {{ request()->is('app/memberships*') ? 'active' : '' }}">
          <span class="icon">⭐</span>
          <span>الاشتراكات والعضويّات</span>
        </a>
      @endif

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">التشغيل</div>
      @if(auth()->user()->tenant?->canAccessFeature('work_orders'))
        <a href="/app/work-orders" class="nav-item {{ request()->is('app/work-orders*') ? 'active' : '' }}">
          <span class="icon">📋</span>
          <span>أوامر الشغل</span>
        </a>
      @endif
      @if(auth()->user()->tenant?->canAccessFeature('bookings'))
        <a href="/app/bookings" class="nav-item {{ request()->is('app/bookings*') ? 'active' : '' }}">
          <span class="icon">📅</span>
          <span>الحجوزات</span>
        </a>
      @endif
      @if(auth()->user()->tenant?->canAccessFeature('time_tracking'))
        <a href="/app/time-tracking" class="nav-item {{ request()->is('app/time-tracking*') ? 'active' : '' }}">
          <span class="icon">⏱</span>
          <span>تتبّع الوقت</span>
        </a>
      @endif
      @if(auth()->user()->tenant?->canAccessFeature('rental_units'))
        <a href="/app/rentals" class="nav-item {{ request()->is('app/rentals*') ? 'active' : '' }}">
          <span class="icon">🏘</span>
          <span>الإيجارات والوحدات</span>
        </a>
      @endif

      <div class="pt-4 pb-1 px-2 text-xs font-semibold text-ink-700/50 uppercase tracking-wider">التقارير والأدوات</div>
      @if(auth()->user()->tenant?->canAccessFeature('smart_reports'))
        <a href="{{ route('reports') }}" class="nav-item {{ request()->routeIs('reports*') ? 'active' : '' }}">
          <span class="icon">📈</span>
          <span>التقارير الذكيّة</span>
        </a>
      @else
        <a href="{{ route('subscription') }}" class="nav-item opacity-60 hover:opacity-100" title="متاح في الباقة الاحترافيّة">
          <span class="icon">🔒</span>
          <span>التقارير الذكيّة</span>
          <span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-full mr-auto">ترقية</span>
        </a>
      @endif
      <a href="{{ route('ai') }}" class="nav-item {{ request()->routeIs('ai') ? 'active' : '' }}">
        <span class="icon">✨</span>
        <span>المساعد الذكي</span>
      </a>
      <a href="{{ route('settings') }}" class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
        <span class="icon">⚙</span>
        <span>الإعدادات</span>
      </a>
    </nav>

    {{-- User card --}}
    <div class="p-3 border-t border-sand-200">
      <a href="{{ route('subscription') }}" class="block bg-sand-200/60 rounded-xl p-3 hover:bg-sand-200 transition">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-copper-500">💎</span>
          <span class="font-semibold text-sm">{{ auth()->user()->tenant->plan->name_ar ?? 'تجربة مجّانيّة' }}</span>
        </div>
        @if(auth()->user()->tenant->isOnTrial())
          <div class="text-xs text-ink-700/60">
            متبقّي {{ auth()->user()->tenant->trialDaysLeft() }} يوم في التجربة
          </div>
        @endif
      </a>
    </div>
  </aside>

  {{-- Main content --}}
  <main class="flex-1 flex flex-col min-w-0">

    {{-- Top bar --}}
    <header class="bg-white border-b border-sand-200 px-6 py-4 flex items-center justify-between gap-4">
      <div>
        <h1 class="font-display text-2xl font-bold">@yield('page_title', 'لوحة التحكّم')</h1>
        @hasSection('page_subtitle')
          <p class="text-ink-700/60 text-sm">@yield('page_subtitle')</p>
        @endif
      </div>

      <div class="flex items-center gap-3">
        @hasSection('actions')
          @yield('actions')
        @endif

        <button class="w-10 h-10 rounded-xl bg-sand-200 hover:bg-sand-300 grid place-items-center transition" title="الإشعارات">
          🔔
        </button>

        <div class="flex items-center gap-3 border-r border-sand-200 pr-3">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-copper-400 to-copper-500 grid place-items-center text-ink-900 font-display font-bold">
            {{ mb_substr(auth()->user()->name, 0, 1) }}
          </div>
          <div class="hidden md:block">
            <div class="text-sm font-semibold">{{ auth()->user()->name }}</div>
            <div class="text-xs text-ink-700/60">{{ auth()->user()->roles->first()?->name ?? 'مستخدم' }}</div>
          </div>
        </div>
      </div>
    </header>

    {{-- Content --}}
    <div class="flex-1 p-6 overflow-y-auto">
      @if(session('success'))
        <div class="bg-green-50 border-r-4 border-green-500 text-green-800 p-4 rounded-lg mb-4">
          {{ session('success') }}
        </div>
      @endif

      @if(session('error'))
        <div class="bg-red-50 border-r-4 border-red-500 text-red-800 p-4 rounded-lg mb-4">
          {{ session('error') }}
        </div>
      @endif

      @yield('content')
    </div>
  </main>
</div>

@stack('scripts')
</body>
</html>
