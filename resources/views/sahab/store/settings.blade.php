@extends('sahab.layouts.app')

@section('title', 'متجر الواتساب')
@section('page_title', '🛍 متجر الواتساب الخاص بك')
@section('page_subtitle', 'دع عملاءك يطلبون مباشرةً عبر الواتساب من رابط واحد')

@section('content')
<div x-data="storeAdmin()" x-init="init()" class="max-w-4xl">

  {{-- Status banner --}}
  <div class="rounded-2xl p-5 mb-6 flex items-center justify-between"
       :class="store.is_published ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'">
    <div>
      <div class="flex items-center gap-2 mb-1">
        <span class="w-3 h-3 rounded-full" :class="store.is_published ? 'bg-green-500 animate-pulse' : 'bg-yellow-500'"></span>
        <span class="font-bold" x-text="store.is_published ? '✓ المتجر منشور ومتاح للعملاء' : '⚠ المتجر غير منشور بعد'"></span>
      </div>
      <div class="text-sm text-ink-700/70" x-show="store.is_published">
        رابط المتجر: <a :href="storeUrl" target="_blank" class="brand-text font-mono text-teal-700 underline" x-text="storeUrl"></a>
      </div>
    </div>
    <button @click="togglePublish()"
            :class="store.is_published ? 'bg-orange-500 hover:bg-orange-600' : 'bg-green-600 hover:bg-green-700'"
            class="text-white px-5 py-2.5 rounded-xl font-bold transition">
      <span x-text="store.is_published ? 'إيقاف' : 'نشر المتجر'"></span>
    </button>
  </div>

  {{-- Tabs --}}
  <div class="flex gap-2 mb-6 border-b border-sand-200">
    <button @click="tab = 'general'"
            :class="tab === 'general' ? 'border-teal-700 text-teal-700' : 'border-transparent'"
            class="px-4 py-3 font-semibold border-b-2">معلومات عامّة</button>
    <button @click="tab = 'design'"
            :class="tab === 'design' ? 'border-teal-700 text-teal-700' : 'border-transparent'"
            class="px-4 py-3 font-semibold border-b-2">التصميم</button>
    <button @click="tab = 'orders'"
            :class="tab === 'orders' ? 'border-teal-700 text-teal-700' : 'border-transparent'"
            class="px-4 py-3 font-semibold border-b-2">إعدادات الطلب</button>
    <button @click="tab = 'analytics'"
            :class="tab === 'analytics' ? 'border-teal-700 text-teal-700' : 'border-transparent'"
            class="px-4 py-3 font-semibold border-b-2">📊 الإحصائيّات</button>
  </div>

  {{-- TAB: General --}}
  <div x-show="tab === 'general'" class="space-y-4">
    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">معلومات المتجر</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold mb-1">اسم المتجر *</label>
          <input x-model="store.name" class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">رقم الواتساب *</label>
          <input x-model="store.whatsapp_number" placeholder="9665XXXXXXXX" class="w-full px-4 py-2.5 rounded-xl border border-sand-200 font-mono">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold mb-1">عبارة جذّابة</label>
          <input x-model="store.tagline" placeholder="أشهى الأكلات السعوديّة بأيدي ماهرة" class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-semibold mb-1">وصف المتجر</label>
          <textarea x-model="store.description" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-sand-200 resize-none"></textarea>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">الرابط المخصّص</h3>
      <div class="bg-sand-100 rounded-xl p-4">
        <div class="text-sm text-ink-700/70 mb-2">رابط متجرك:</div>
        <code class="text-base font-mono text-teal-700 break-all" x-text="storeUrl"></code>
        <button @click="copyUrl()" class="mt-3 bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
          📋 نسخ الرابط
        </button>
      </div>
    </div>
  </div>

  {{-- TAB: Design --}}
  <div x-show="tab === 'design'" class="space-y-4">
    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">الثيم</h3>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <template x-for="t in themes">
          <button @click="store.theme = t.id"
                  :class="store.theme === t.id ? 'border-teal-700 ring-2 ring-teal-700/30' : 'border-sand-200'"
                  class="border-2 rounded-2xl p-4 text-right hover:border-teal-700 transition">
            <div class="text-3xl mb-2" x-text="t.icon"></div>
            <div class="font-bold" x-text="t.name"></div>
            <div class="text-xs text-ink-700/60" x-text="t.desc"></div>
          </button>
        </template>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">الألوان</h3>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold mb-1">اللون الأساسي</label>
          <div class="flex gap-2">
            <input type="color" x-model="store.primary_color" class="w-14 h-12 rounded-xl border border-sand-200 cursor-pointer">
            <input x-model="store.primary_color" class="flex-1 px-4 py-2.5 rounded-xl border border-sand-200 font-mono">
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">اللون المساعد</label>
          <div class="flex gap-2">
            <input type="color" x-model="store.secondary_color" class="w-14 h-12 rounded-xl border border-sand-200 cursor-pointer">
            <input x-model="store.secondary_color" class="flex-1 px-4 py-2.5 rounded-xl border border-sand-200 font-mono">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- TAB: Orders --}}
  <div x-show="tab === 'orders'" class="space-y-4">
    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">إعدادات الطلب</h3>
      <div class="space-y-4">
        <label class="flex items-center justify-between p-3 bg-sand-100 rounded-xl">
          <span class="font-semibold">السماح بالتوصيل 🛵</span>
          <input type="checkbox" x-model="store.enable_delivery" class="w-5 h-5">
        </label>
        <label class="flex items-center justify-between p-3 bg-sand-100 rounded-xl">
          <span class="font-semibold">السماح بالاستلام 🏪</span>
          <input type="checkbox" x-model="store.enable_pickup" class="w-5 h-5">
        </label>
        <label class="flex items-center justify-between p-3 bg-sand-100 rounded-xl">
          <span class="font-semibold">إظهار الأسعار</span>
          <input type="checkbox" x-model="store.show_prices" class="w-5 h-5">
        </label>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div>
          <label class="block text-sm font-semibold mb-1">الحدّ الأدنى للطلب</label>
          <div class="relative">
            <input type="number" x-model="store.min_order_amount" class="w-full px-4 py-2.5 rounded-xl border border-sand-200 pr-12">
            <span class="absolute left-4 top-2.5 text-ink-700/60">ر.س</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">رسوم التوصيل</label>
          <div class="relative">
            <input type="number" x-model="store.delivery_fee" class="w-full px-4 py-2.5 rounded-xl border border-sand-200 pr-12">
            <span class="absolute left-4 top-2.5 text-ink-700/60">ر.س</span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-semibold mb-1">توصيل مجّاني فوق</label>
          <div class="relative">
            <input type="number" x-model="store.free_delivery_above" class="w-full px-4 py-2.5 rounded-xl border border-sand-200 pr-12">
            <span class="absolute left-4 top-2.5 text-ink-700/60">ر.س</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- TAB: Analytics --}}
  <div x-show="tab === 'analytics'" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white rounded-2xl border border-sand-200 p-6">
        <div class="text-sm text-ink-700/60 mb-1">إجمالي الزيارات</div>
        <div class="font-mono font-bold text-3xl" x-text="store.total_visits || 0"></div>
      </div>
      <div class="bg-white rounded-2xl border border-sand-200 p-6">
        <div class="text-sm text-ink-700/60 mb-1">إجمالي الطلبات</div>
        <div class="font-mono font-bold text-3xl text-teal-700" x-text="store.total_orders || 0"></div>
      </div>
      <div class="bg-white rounded-2xl border border-sand-200 p-6">
        <div class="text-sm text-ink-700/60 mb-1">إجمالي الإيرادات</div>
        <div class="font-mono font-bold text-3xl text-copper-500">
          <span x-text="(store.total_revenue || 0).toFixed(0)"></span>
          <span class="text-sm">ر.س</span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">آخر 30 يوم</h3>
      <p class="text-sm text-ink-700/60">سيتمّ عرض رسم بياني للزيارات والطلبات هنا</p>
    </div>
  </div>

  {{-- Save button --}}
  <div class="sticky bottom-0 bg-sand-100/95 backdrop-blur py-4 mt-6 -mx-6 px-6 border-t border-sand-200" x-show="tab !== 'analytics'">
    <button @click="save()" :disabled="saving"
            class="bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white px-8 py-3 rounded-xl font-bold transition">
      <span x-show="!saving">💾 حفظ التغييرات</span>
      <span x-show="saving">جاري الحفظ...</span>
    </button>
    <span x-show="savedMessage" x-cloak class="text-green-600 font-semibold mr-3" x-text="savedMessage"></span>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function storeAdmin() {
  return {
    tab: 'general',
    saving: false,
    savedMessage: '',
    store: @json($store),
    themes: [
      { id: 'modern', icon: '🎨', name: 'عصري', desc: 'تصميم حديث وجذّاب' },
      { id: 'elegant', icon: '✨', name: 'أنيق', desc: 'فخامة وبساطة' },
      { id: 'minimal', icon: '⚪', name: 'بسيط', desc: 'بساطة قصوى' },
      { id: 'classic', icon: '📜', name: 'كلاسيكي', desc: 'تقليدي وموثوق' },
      { id: 'food', icon: '🍽', name: 'مطاعم', desc: 'مخصّص للأكل' },
      { id: 'fashion', icon: '👗', name: 'أزياء', desc: 'مخصّص للملابس' },
    ],

    init() {},

    get storeUrl() {
      return `${window.location.origin}/store/${this.store.slug || 'your-store'}`;
    },

    copyUrl() {
      navigator.clipboard.writeText(this.storeUrl);
      this.savedMessage = '✓ تمّ النسخ';
      setTimeout(() => this.savedMessage = '', 2000);
    },

    async save() {
      this.saving = true;
      try {
        const res = await fetch('/api/sahab/store/update', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
          },
          body: JSON.stringify(this.store),
        });
        const data = await res.json();
        if (data.success) {
          this.store = { ...this.store, ...data.store };
          this.savedMessage = '✓ تمّ الحفظ بنجاح';
          setTimeout(() => this.savedMessage = '', 3000);
        }
      } catch (e) {
        alert('فشل الحفظ');
      } finally {
        this.saving = false;
      }
    },

    async togglePublish() {
      try {
        const res = await fetch('/api/sahab/store/publish', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ publish: !this.store.is_published }),
        });
        const data = await res.json();
        if (data.success) {
          this.store.is_published = data.is_published;
        }
      } catch (e) {
        alert('فشل التحديث');
      }
    },
  };
}
</script>
@endpush

@endsection
