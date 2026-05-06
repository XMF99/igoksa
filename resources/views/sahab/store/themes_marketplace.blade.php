@extends('sahab.layouts.app')

@section('title', 'متجر الثيمات')
@section('page_title', '🎨 متجر الثيمات')
@section('page_subtitle', 'اختر الثيم المناسب لمتجرك أو اشتر تصاميم احترافيّة')

@section('content')
<div x-data="themeStore()" class="max-w-7xl mx-auto">

  {{-- Categories filter --}}
  <div class="bg-white rounded-2xl border border-sand-200 p-4 mb-6">
    <div class="flex gap-2 overflow-x-auto pb-1">
      @foreach($categories as $key => $label)
        <a href="?category={{ $key }}"
           class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-bold whitespace-nowrap transition
                  {{ ($_GET['category'] ?? 'all') === $key ? 'bg-teal-700 text-white' : 'bg-sand-100 hover:bg-sand-200' }}">
          {{ $label }}
        </a>
      @endforeach
    </div>
  </div>

  {{-- Themes grid --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($themes as $theme)
      @php
        $isOwned = in_array($theme->id, $ownedThemeIds);
        $isActive = $activeThemeId === $theme->id;
      @endphp

      <div class="bg-white rounded-2xl border-2 transition relative overflow-hidden
                  {{ $isActive ? 'border-teal-700 ring-4 ring-teal-700/20' : 'border-sand-200 hover:border-teal-700/50 hover:shadow-lg' }}">

        {{-- Badges --}}
        <div class="absolute top-3 right-3 flex flex-col gap-1 z-10">
          @if($isActive)
            <span class="bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full">✓ نشط الآن</span>
          @endif
          @if($theme->is_new)
            <span class="bg-copper-500 text-ink-900 text-xs font-bold px-3 py-1 rounded-full">جديد</span>
          @endif
          @if($theme->is_featured)
            <span class="bg-yellow-400 text-ink-900 text-xs font-bold px-3 py-1 rounded-full">⭐ مميّز</span>
          @endif
        </div>

        {{-- Preview --}}
        <div class="aspect-video bg-gradient-to-br from-sand-100 to-sand-200 relative overflow-hidden">
          @if($theme->preview_image)
            <img src="{{ $theme->preview_image }}" alt="{{ $theme->name_ar }}" class="w-full h-full object-cover">
          @else
            <div class="w-full h-full grid place-items-center text-7xl opacity-30">🎨</div>
          @endif
        </div>

        {{-- Info --}}
        <div class="p-5">
          <h3 class="font-display font-bold text-xl mb-1">{{ $theme->name_ar }}</h3>
          <p class="text-sm text-ink-700/70 mb-3 line-clamp-2 h-10">
            {{ $theme->description_ar ?? 'تصميم احترافيّ لمتجرك' }}
          </p>

          <div class="flex flex-wrap gap-1 mb-4">
            @if($theme->supports_dark_mode)
              <span class="text-[10px] bg-sand-100 px-2 py-1 rounded-full">🌙 وضع داكن</span>
            @endif
            @if($theme->supports_animations)
              <span class="text-[10px] bg-sand-100 px-2 py-1 rounded-full">✨ حركات</span>
            @endif
            @if($theme->supports_rtl)
              <span class="text-[10px] bg-sand-100 px-2 py-1 rounded-full">عربي RTL</span>
            @endif
          </div>

          {{-- Pricing --}}
          <div class="flex items-baseline justify-between mb-4">
            @if($theme->isFree())
              <div>
                <span class="text-2xl font-bold text-green-600">مجّاني</span>
              </div>
            @elseif($theme->pricing_type === 'subscription')
              <div>
                <span class="font-mono font-bold text-2xl text-teal-700">{{ number_format($theme->subscription_monthly, 0) }}</span>
                <span class="text-sm text-ink-700/60">ر.س/شهر</span>
              </div>
            @else
              <div>
                <span class="font-mono font-bold text-2xl text-teal-700">{{ number_format($theme->price, 0) }}</span>
                <span class="text-sm text-ink-700/60">ر.س</span>
                <div class="text-xs text-ink-700/60">دفعة واحدة</div>
              </div>
            @endif

            @if($theme->rating > 0)
              <div class="text-sm">
                <span>⭐</span>
                <span class="font-bold">{{ number_format($theme->rating, 1) }}</span>
                <span class="text-ink-700/60">({{ $theme->rating_count }})</span>
              </div>
            @endif
          </div>

          {{-- Actions --}}
          <div class="flex gap-2">
            @if($theme->demo_url)
              <a href="{{ $theme->demo_url }}" target="_blank"
                 class="flex-1 text-center bg-sand-100 hover:bg-sand-200 py-2.5 rounded-xl text-sm font-bold transition">
                👁 معاينة
              </a>
            @endif

            @if($isOwned)
              @if($isActive)
                <button disabled class="flex-1 bg-green-100 text-green-700 py-2.5 rounded-xl text-sm font-bold">
                  ✓ مفعّل
                </button>
              @else
                <button onclick="activateTheme({{ $theme->id }})"
                        class="flex-1 bg-teal-700 hover:bg-teal-900 text-white py-2.5 rounded-xl text-sm font-bold transition">
                  تفعيل
                </button>
              @endif
            @else
              <button onclick='openPurchaseModal(@json($theme))'
                      class="flex-1 bg-copper-500 hover:bg-copper-400 text-ink-900 py-2.5 rounded-xl text-sm font-bold transition">
                @if($theme->isFree())
                  + إضافة مجّاناً
                @else
                  🛒 شراء
                @endif
              </button>
            @endif
          </div>
        </div>
      </div>
    @empty
      <div class="col-span-full text-center py-20">
        <div class="text-7xl mb-4 opacity-30">🎨</div>
        <p class="font-bold text-xl mb-2">لا توجد ثيمات في هذه الفئة</p>
        <p class="text-sm text-ink-700/60">جرّب فئة أخرى</p>
      </div>
    @endforelse
  </div>

  <div class="mt-6">{{ $themes->links() }}</div>

  {{-- Purchase Modal --}}
  <div x-show="showPurchaseModal" x-cloak
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 grid place-items-center p-4"
       @click.self="showPurchaseModal = false">
    <div class="bg-white rounded-2xl max-w-md w-full p-6">
      <h3 class="font-bold text-2xl mb-2" x-text="selectedTheme?.name_ar"></h3>
      <p class="text-sm text-ink-700/70 mb-4" x-text="selectedTheme?.description_ar"></p>

      <div class="bg-sand-100 rounded-xl p-4 mb-4">
        <div class="flex items-baseline justify-between">
          <span class="text-sm font-semibold">المبلغ المطلوب</span>
          <div class="text-left">
            <template x-if="selectedTheme?.pricing_type === 'free'">
              <span class="font-bold text-green-600 text-xl">مجّاني</span>
            </template>
            <template x-if="selectedTheme?.pricing_type === 'subscription'">
              <div>
                <span class="font-mono font-bold text-2xl" x-text="selectedTheme?.subscription_monthly"></span>
                <span class="text-sm">ر.س/شهر</span>
              </div>
            </template>
            <template x-if="selectedTheme?.pricing_type === 'one_time'">
              <div>
                <span class="font-mono font-bold text-2xl" x-text="selectedTheme?.price"></span>
                <span class="text-sm">ر.س</span>
              </div>
            </template>
          </div>
        </div>
      </div>

      <template x-if="selectedTheme?.pricing_type !== 'free'">
        <div class="space-y-2 mb-4">
          <p class="text-sm font-semibold mb-2">طريقة الدفع</p>
          <div class="grid grid-cols-2 gap-2">
            <button @click="paymentMethod = 'apple_pay'"
                    :class="paymentMethod === 'apple_pay' ? 'border-teal-700 bg-teal-50' : 'border-sand-200'"
                    class="border-2 p-3 rounded-xl text-center">
               Pay
            </button>
            <button @click="paymentMethod = 'mada'"
                    :class="paymentMethod === 'mada' ? 'border-teal-700 bg-teal-50' : 'border-sand-200'"
                    class="border-2 p-3 rounded-xl text-center font-bold text-sm">
              💳 مدى
            </button>
            <button @click="paymentMethod = 'visa'"
                    :class="paymentMethod === 'visa' ? 'border-teal-700 bg-teal-50' : 'border-sand-200'"
                    class="border-2 p-3 rounded-xl text-center font-bold text-sm">
              VISA
            </button>
            <button @click="paymentMethod = 'stc_pay'"
                    :class="paymentMethod === 'stc_pay' ? 'border-teal-700 bg-teal-50' : 'border-sand-200'"
                    class="border-2 p-3 rounded-xl text-center font-bold text-sm">
              STC Pay
            </button>
          </div>
        </div>
      </template>

      <div class="flex gap-2">
        <button @click="showPurchaseModal = false" class="flex-1 bg-sand-100 hover:bg-sand-200 py-3 rounded-xl font-bold">
          إلغاء
        </button>
        <button @click="purchaseTheme()" :disabled="loading"
                class="flex-1 bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-3 rounded-xl font-bold">
          <span x-show="!loading" x-text="selectedTheme?.pricing_type === 'free' ? '+ إضافة' : '🛒 إكمال الشراء'"></span>
          <span x-show="loading">جاري المعالجة...</span>
        </button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
let app;
function themeStore() {
  return {
    showPurchaseModal: false,
    selectedTheme: null,
    paymentMethod: 'apple_pay',
    loading: false,

    init() { app = this; },

    async purchaseTheme() {
      this.loading = true;
      try {
        const res = await fetch(`/api/sahab/themes/${this.selectedTheme.id}/purchase`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          },
          body: JSON.stringify({
            payment_method: this.selectedTheme.pricing_type === 'free' ? null : this.paymentMethod,
          }),
        });
        const data = await res.json();
        if (data.success) {
          if (data.redirect_url) {
            window.location.href = data.redirect_url;
          } else {
            alert(data.message || 'تمّ بنجاح');
            location.reload();
          }
        } else {
          alert(data.error || 'فشل الشراء');
        }
      } catch (e) {
        alert('فشل الاتصال');
      } finally {
        this.loading = false;
      }
    },
  };
}

function openPurchaseModal(theme) {
  app.selectedTheme = theme;
  app.showPurchaseModal = true;
}

async function activateTheme(themeId) {
  if (!confirm('تفعيل هذا الثيم في متجرك؟')) return;
  try {
    const res = await fetch(`/api/sahab/themes/${themeId}/activate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      },
    });
    const data = await res.json();
    if (data.success) {
      alert('✓ تمّ تفعيل الثيم');
      location.reload();
    } else {
      alert(data.error || 'فشل التفعيل');
    }
  } catch (e) {
    alert('فشل الاتصال');
  }
}
</script>
@endpush

@endsection
