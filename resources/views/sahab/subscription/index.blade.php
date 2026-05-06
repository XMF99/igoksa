@extends('sahab.layouts.app')

@section('title', 'الاشتراك')
@section('page_title', '💎 اشتراكك')
@section('page_subtitle', 'إدارة الباقة والترقية')

@section('content')
@php
    $tenant = auth()->user()->tenant;
    $currentPlan = $tenant?->plan;
    $allPlans = \App\Models\Sahab\Plan::where('is_active', true)->orderBy('display_order')->get();

    $featureLabels = [
        'pos' => 'كاشير سريع', 'zatca_phase1' => 'فاتورة زاتكا (المرحلة الأولى)',
        'zatca_phase2' => 'فاتورة زاتكا (المرحلة الثانية)', 'thermal_print' => 'طباعة حراريّة',
        'inventory' => 'إدارة المخزون', 'customers' => 'إدارة العملاء',
        'multi_outlet' => 'فروع متعدّدة', 'delivery_apps_integration' => 'ربط 8 تطبيقات توصيل',
        'kds' => 'شاشة المطبخ', 'ai_assistant' => 'مساعد ذكي AI', 'ocr_invoices' => 'قراءة فواتير',
        'loyalty_program' => 'نظام الولاء', 'gps_attendance' => 'حضور GPS',
        'document_tracking' => 'متابعة الوثائق', 'fraud_detection' => 'كاشف احتيال',
        'smart_reports' => 'تقارير ذكيّة ⭐', 'smart_reports_whatsapp' => 'تقارير على واتساب',
        'whatsapp_store' => '🛍 متجر واتساب ⭐', 'custom_domain' => 'دومين مخصّص',
        'apple_wallet' => 'Apple Wallet', 'surprise_tests' => 'اختبار مفاجئ',
        'predictive_analytics' => 'توقّعات ذكيّة', 'whatsapp_support' => 'دعم على واتساب',
        '24x7_support' => 'دعم 24/7', 'dedicated_manager' => 'مدير حساب مخصّص',
        'onboarding' => 'جلسة تأسيس', 'erp_full' => 'ERP كامل', 'accounting_full' => 'محاسبة كاملة',
        'hrm_full' => 'موارد بشريّة كاملة', 'crm' => 'CRM', 'api_access' => 'وصول API',
        'all' => '✓ كل الميزات', 'monthly_reports' => 'تقارير شهريّة PDF',
        'custom_integrations' => 'تكاملات مخصّصة', 'geofencing' => 'Geofencing',
    ];
@endphp

<div class="max-w-5xl mx-auto">

  {{-- الاشتراك الحالي --}}
  @if($currentPlan)
    <div class="bg-gradient-to-br from-teal-700 to-teal-900 rounded-3xl p-8 text-white mb-8">
      <div class="flex items-start justify-between flex-wrap gap-4">
        <div>
          <div class="text-sm opacity-80 mb-1">باقتك الحاليّة</div>
          <h2 class="font-display text-4xl font-bold">{{ $currentPlan->name_ar }}</h2>
          <p class="opacity-80 mt-2">{{ $currentPlan->description_ar }}</p>
        </div>
        <div class="text-left">
          <div class="text-sm opacity-80 mb-1">الفاتورة الشهريّة</div>
          <div class="font-mono font-bold text-4xl">{{ number_format($currentPlan->price_monthly, 0) }} <span class="text-lg opacity-70">ر.س</span></div>
        </div>
      </div>

      @if($tenant->isOnTrial())
        <div class="mt-6 bg-copper-400/20 backdrop-blur rounded-xl p-4 flex items-center gap-3">
          <span class="text-3xl">⏰</span>
          <div class="flex-1">
            <div class="font-bold">أنت في فترة التجربة المجّانيّة</div>
            <div class="text-sm opacity-80">متبقّي {{ $tenant->trialDaysLeft() }} يوم — فعّل الاشتراك قبل انتهائها</div>
          </div>
          <button class="bg-copper-400 text-ink-900 px-5 py-2.5 rounded-xl font-bold whitespace-nowrap">
            فعّل الآن
          </button>
        </div>
      @endif
    </div>
  @endif

  <h2 class="font-display text-2xl font-bold mb-6">{{ $currentPlan ? 'هل تريد الترقية؟' : 'اختر باقتك' }}</h2>

  <div class="grid md:grid-cols-3 gap-6">
    @foreach($allPlans as $plan)
      @php
        $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
        $isUpgrade = $currentPlan && $plan->price_monthly > $currentPlan->price_monthly;
      @endphp

      <div class="rounded-3xl p-6 relative transition
                  {{ $plan->is_popular ? 'bg-gradient-to-b from-teal-700 to-teal-900 text-white shadow-2xl scale-105' : 'bg-white border border-sand-200' }}
                  {{ $isCurrent ? 'ring-4 ring-green-500' : '' }}">

        @if($isCurrent)
          <div class="absolute -top-3 left-6 bg-green-500 text-white px-3 py-1 rounded-full text-xs font-bold">
            ✓ باقتك الحاليّة
          </div>
        @elseif($plan->is_popular)
          <div class="absolute -top-3 right-6 bg-copper-500 text-ink-900 px-3 py-1 rounded-full text-xs font-bold">
            🔥 الأكثر شعبيّة
          </div>
        @endif

        <h3 class="font-display text-2xl font-bold mb-1">{{ $plan->name_ar }}</h3>
        <p class="text-sm {{ $plan->is_popular ? 'text-white/70' : 'text-ink-700/70' }} mb-4">
          {{ $plan->description_ar }}
        </p>

        <div class="mb-6">
          <div class="flex items-baseline gap-2">
            <span class="font-display text-5xl font-bold">{{ number_format($plan->price_monthly, 0) }}</span>
            <span class="{{ $plan->is_popular ? 'text-white/70' : 'text-ink-700/60' }}">ر.س / شهر</span>
          </div>
        </div>

        @if($isCurrent)
          <button disabled class="w-full py-3.5 rounded-xl font-bold mb-6 bg-white/20 text-white/60 cursor-not-allowed">
            باقتك الحاليّة
          </button>
        @else
          <button onclick="changePlan('{{ $plan->slug }}')"
                  class="w-full py-3.5 rounded-xl font-bold mb-6 transition
                         {{ $plan->is_popular ? 'bg-copper-500 text-ink-900 hover:bg-copper-400' : 'bg-teal-700 text-white hover:bg-teal-900' }}">
            @if($isUpgrade) ⬆ الترقية الآن @else التغيير لهذه الباقة @endif
          </button>
        @endif

        <div class="space-y-2 text-sm border-t {{ $plan->is_popular ? 'border-white/15' : 'border-sand-200' }} pt-5">
          @foreach($plan->features as $feature)
            <div class="flex gap-2">
              <span class="{{ $plan->is_popular ? 'text-copper-400' : 'text-green-600' }} flex-shrink-0">✓</span>
              <span>{{ $featureLabels[$feature] ?? $feature }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endforeach
  </div>
</div>

<script>
async function changePlan(slug) {
  if (!confirm('متأكّد من تغيير الباقة؟')) return;
  const res = await fetch('/app/subscription/change-plan', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
    },
    body: JSON.stringify({ plan_slug: slug, billing_cycle: 'monthly' }),
  });
  const data = await res.json();
  if (data.success) {
    alert('✓ تمّ تغيير الباقة');
    location.reload();
  }
}
</script>
@endsection
