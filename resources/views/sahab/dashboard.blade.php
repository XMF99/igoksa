@extends('sahab.layouts.app')

@section('title', 'الرئيسيّة')
@section('page_title', 'مرحباً، ' . explode(' ', auth()->user()->name)[0])
@section('page_subtitle', \Carbon\Carbon::now()->locale('ar')->translatedFormat('l، j F Y'))

@section('content')

{{-- ============== KPIs ============== --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

  <div class="bg-gradient-to-br from-teal-700 to-teal-900 rounded-2xl p-5 text-white relative overflow-hidden">
    <div class="absolute -top-4 -left-4 text-6xl opacity-10">📊</div>
    <div class="text-xs opacity-80 mb-1">مبيعات اليوم</div>
    <div class="font-mono font-bold text-3xl">{{ number_format($today_sales, 0) }} <span class="text-sm opacity-70">ر.س</span></div>
    @if($sales_growth != 0)
      <div class="text-xs mt-2 {{ $sales_growth > 0 ? 'text-green-300' : 'text-red-300' }}">
        {{ $sales_growth > 0 ? '↑' : '↓' }} {{ abs(round($sales_growth, 1)) }}% عن أمس
      </div>
    @endif
  </div>

  <div class="bg-white rounded-2xl p-5 border border-sand-200">
    <div class="text-xs text-ink-700/60 mb-1">عدد الفواتير</div>
    <div class="font-mono font-bold text-3xl">{{ number_format($today_invoices) }}</div>
    <div class="text-xs text-ink-700/60 mt-2">متوسّط: {{ number_format($today_avg, 0) }} ر.س</div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-sand-200">
    <div class="text-xs text-ink-700/60 mb-1">طلبات التوصيل</div>
    <div class="font-mono font-bold text-3xl">{{ number_format($external_orders) }}</div>
    <div class="text-xs text-copper-500 mt-2">من تطبيقات التوصيل</div>
  </div>

  <div class="bg-white rounded-2xl p-5 border border-sand-200">
    <div class="text-xs text-ink-700/60 mb-1">تنبيهات تحتاج اهتمام</div>
    <div class="font-mono font-bold text-3xl">{{ $expiring_docs + $expiring_employee_docs + $low_stock + $suspicious }}</div>
    <div class="text-xs text-ink-700/60 mt-2">وثائق + مخزون + أنشطة</div>
  </div>
</div>

{{-- ============== Quick actions ============== --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
  <a href="{{ route('pos') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">🛒</div>
    <div class="text-sm font-semibold">فتح الكاشير</div>
  </a>
  <a href="{{ route('delivery') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">🚀</div>
    <div class="text-sm font-semibold">التوصيل</div>
  </a>
  <a href="{{ route('ocr') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">📸</div>
    <div class="text-sm font-semibold">صوّر فاتورة</div>
  </a>
  <a href="{{ route('ai') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">✨</div>
    <div class="text-sm font-semibold">المساعد</div>
  </a>
  <a href="{{ route('reports') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">📈</div>
    <div class="text-sm font-semibold">التقارير</div>
  </a>
  <a href="{{ route('employees') }}" class="bg-white rounded-xl p-4 border border-sand-200 hover:border-teal-700 hover:shadow-md transition text-center">
    <div class="text-3xl mb-1">👥</div>
    <div class="text-sm font-semibold">الموظّفون</div>
  </a>
</div>

{{-- ============== Two columns ============== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  {{-- Top products --}}
  <div class="lg:col-span-2 bg-white rounded-2xl border border-sand-200 p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-lg font-bold">الأكثر مبيعاً اليوم</h2>
      <a href="{{ route('reports.sales') }}" class="text-sm text-teal-700 hover:underline">عرض التفاصيل ←</a>
    </div>

    @if($top_products->isEmpty())
      <div class="text-center py-12 text-ink-700/50">
        <div class="text-5xl mb-2">📋</div>
        <p>لا توجد مبيعات اليوم بعد</p>
      </div>
    @else
      <div class="space-y-3">
        @foreach($top_products as $i => $product)
          <div class="flex items-center gap-4">
            <div class="w-9 h-9 rounded-lg bg-sand-200 grid place-items-center font-display font-bold text-sm">
              {{ $i + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold truncate">{{ $product->product_name }}</div>
              <div class="text-xs text-ink-700/60">{{ $product->qty }} قطعة</div>
            </div>
            <div class="font-mono font-bold text-teal-700">{{ number_format($product->revenue, 0) }} ر.س</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Alerts --}}
  <div class="bg-white rounded-2xl border border-sand-200 p-6">
    <h2 class="font-display text-lg font-bold mb-4">تنبيهات تحتاج اهتمامك</h2>

    <div class="space-y-3">
      @if($expiring_docs > 0)
        <a href="{{ route('documents') }}" class="block p-3 rounded-lg bg-yellow-50 border-r-4 border-yellow-400 hover:bg-yellow-100 transition">
          <div class="flex items-center gap-2">
            <span class="text-yellow-600">📄</span>
            <span class="font-semibold text-sm">وثائق المحلّ</span>
          </div>
          <div class="text-xs text-ink-700/70 mt-1">{{ $expiring_docs }} وثيقة قاربت على الانتهاء</div>
        </a>
      @endif

      @if($expiring_employee_docs > 0)
        <a href="{{ route('documents') }}" class="block p-3 rounded-lg bg-yellow-50 border-r-4 border-yellow-400 hover:bg-yellow-100 transition">
          <div class="flex items-center gap-2">
            <span class="text-yellow-600">👤</span>
            <span class="font-semibold text-sm">وثائق الموظّفين</span>
          </div>
          <div class="text-xs text-ink-700/70 mt-1">{{ $expiring_employee_docs }} إقامة/تأمين قاربت على الانتهاء</div>
        </a>
      @endif

      @if($low_stock > 0)
        <a href="{{ route('inventory') }}" class="block p-3 rounded-lg bg-orange-50 border-r-4 border-orange-400 hover:bg-orange-100 transition">
          <div class="flex items-center gap-2">
            <span class="text-orange-600">📦</span>
            <span class="font-semibold text-sm">مخزون منخفض</span>
          </div>
          <div class="text-xs text-ink-700/70 mt-1">{{ $low_stock }} منتج تحت الحدّ الأدنى</div>
        </a>
      @endif

      @if($suspicious > 0)
        <a href="#" class="block p-3 rounded-lg bg-red-50 border-r-4 border-red-400 hover:bg-red-100 transition">
          <div class="flex items-center gap-2">
            <span class="text-red-600">🚨</span>
            <span class="font-semibold text-sm">أنشطة مشبوهة</span>
          </div>
          <div class="text-xs text-ink-700/70 mt-1">{{ $suspicious }} حالة تحتاج مراجعة</div>
        </a>
      @endif

      @if($expiring_docs == 0 && $expiring_employee_docs == 0 && $low_stock == 0 && $suspicious == 0)
        <div class="text-center py-6 text-ink-700/50">
          <div class="text-4xl mb-2">✓</div>
          <p class="text-sm">كل شي تمام! لا توجد تنبيهات</p>
        </div>
      @endif
    </div>

    {{-- Trial banner --}}
    @if($tenant->isOnTrial())
      <div class="mt-6 p-4 rounded-lg bg-gradient-to-br from-copper-400 to-copper-500 text-ink-900">
        <div class="font-display font-bold text-lg mb-1">⭐ تجربة مجّانيّة</div>
        <div class="text-sm mb-3">متبقّي {{ $tenant->trialDaysLeft() }} يوم — لا تخسر بياناتك!</div>
        <a href="{{ route('subscription') }}" class="block bg-ink-900 text-white text-center py-2 rounded-lg font-semibold text-sm hover:bg-ink-700 transition">
          فعّل الاشتراك الآن
        </a>
      </div>
    @endif
  </div>
</div>

@endsection
