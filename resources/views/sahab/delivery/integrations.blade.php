@extends('sahab.layouts.app')

@section('title', 'تكاملات التوصيل')
@section('page_title', 'تكاملات تطبيقات التوصيل')
@section('page_subtitle', 'كل الطلبات من 8 منصّات في كاشير واحد')

@section('content')
<div x-data="deliveryApp()" x-init="init()">

  {{-- Stats banner --}}
  <div class="bg-gradient-to-r from-teal-700 to-teal-900 rounded-2xl p-6 text-white mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
      <div>
        <div class="text-xs opacity-70 mb-1">منصّات نشطة</div>
        <div class="font-mono font-bold text-3xl" x-text="stats.active + ' / 8'"></div>
      </div>
      <div>
        <div class="text-xs opacity-70 mb-1">طلبات اليوم</div>
        <div class="font-mono font-bold text-3xl" x-text="stats.todayOrders"></div>
      </div>
      <div>
        <div class="text-xs opacity-70 mb-1">إيرادات اليوم</div>
        <div class="font-mono font-bold text-3xl" x-text="stats.todayRevenue + ' ر.س'"></div>
      </div>
      <div>
        <div class="text-xs opacity-70 mb-1">العمولات المحسوبة</div>
        <div class="font-mono font-bold text-3xl" x-text="stats.commissions + ' ر.س'"></div>
      </div>
    </div>
  </div>

  {{-- Platforms grid --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <template x-for="platform in platforms" :key="platform.platform">
      <div class="bg-white rounded-2xl p-5 border border-sand-200 hover:shadow-lg transition">

        {{-- Platform header --}}
        <div class="flex items-center gap-3 mb-4">
          <div class="w-14 h-14 rounded-xl grid place-items-center text-white font-bold text-xl"
               :style="`background: ${platform.color}`"
               x-text="platform.icon"></div>
          <div class="flex-1">
            <div class="font-bold text-lg" x-text="platform.name_ar"></div>
            <div class="text-xs text-ink-700/60" x-text="platform.name_en"></div>
          </div>
          <div class="w-3 h-3 rounded-full"
               :class="platform.is_active ? 'bg-green-500' : 'bg-gray-300'"></div>
        </div>

        {{-- Stats --}}
        <div class="bg-sand-100 rounded-xl p-3 mb-4 grid grid-cols-2 gap-2 text-center">
          <div>
            <div class="text-xs text-ink-700/60">طلبات اليوم</div>
            <div class="font-bold text-lg" x-text="platform.orders_today || 0"></div>
          </div>
          <div>
            <div class="text-xs text-ink-700/60">عمولة</div>
            <div class="font-bold text-lg" x-text="platform.commission_rate + '%'"></div>
          </div>
        </div>

        {{-- Last order --}}
        <div class="text-xs text-ink-700/60 mb-4 text-center">
          <template x-if="platform.last_order_at">
            <span>آخر طلب: <span x-text="formatTime(platform.last_order_at)"></span></span>
          </template>
          <template x-if="!platform.last_order_at">
            <span>لا توجد طلبات بعد</span>
          </template>
        </div>

        {{-- Action button --}}
        <template x-if="!platform.is_active">
          <button @click="configure(platform)" class="w-full bg-teal-700 hover:bg-teal-900 text-white py-2.5 rounded-xl font-bold text-sm transition">
            ربط الآن
          </button>
        </template>

        <template x-if="platform.is_active">
          <div class="flex gap-2">
            <button @click="configure(platform)" class="flex-1 bg-sand-100 hover:bg-sand-200 py-2.5 rounded-xl font-semibold text-sm">
              ⚙ إعدادات
            </button>
            <button @click="toggleActive(platform)" class="flex-1 bg-orange-100 hover:bg-orange-200 text-orange-700 py-2.5 rounded-xl font-semibold text-sm">
              إيقاف
            </button>
          </div>
        </template>
      </div>
    </template>
  </div>

  {{-- Webhook URL helper --}}
  <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
    <div class="flex gap-3">
      <div class="text-2xl">💡</div>
      <div class="flex-1 text-sm">
        <div class="font-bold mb-1">كيف اربط منصّة؟</div>
        <div class="text-ink-700/70 mb-2">اضغط "ربط الآن" بجانب المنصّة، انسخ Webhook URL وضعه في حساب التاجر عند المنصّة.</div>
        <div class="text-ink-700/70">للحصول على API keys، تواصل مع المنصّة مباشرة.</div>
      </div>
    </div>
  </div>
</div>

{{-- Config Modal --}}
<div x-data="{}" id="configModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 grid place-items-center p-4">
  <div class="bg-white rounded-3xl max-w-md w-full p-6">
    <h3 class="font-bold text-2xl mb-4">إعدادات المنصّة</h3>
    <form id="configForm">
      <div class="space-y-3 mb-4">
        <div>
          <label class="text-sm font-semibold mb-1 block">API Key</label>
          <input type="text" name="api_key" class="w-full px-4 py-3 rounded-xl border border-sand-200">
        </div>
        <div>
          <label class="text-sm font-semibold mb-1 block">Secret Key</label>
          <input type="text" name="secret" class="w-full px-4 py-3 rounded-xl border border-sand-200">
        </div>
        <div>
          <label class="text-sm font-semibold mb-1 block">العمولة %</label>
          <input type="number" name="commission_rate" step="0.5" class="w-full px-4 py-3 rounded-xl border border-sand-200">
        </div>
        <div>
          <label class="text-sm font-semibold mb-1 block">وقت التحضير (دقائق)</label>
          <input type="number" name="default_prep_time" value="25" class="w-full px-4 py-3 rounded-xl border border-sand-200">
        </div>
        <label class="flex items-center gap-2">
          <input type="checkbox" name="auto_accept">
          <span class="text-sm">قبول تلقائي للطلبات</span>
        </label>
      </div>
      <div class="bg-sand-100 rounded-xl p-3 mb-4 text-sm">
        <div class="font-bold mb-1">Webhook URL:</div>
        <code class="text-xs break-all" id="webhookUrl">—</code>
      </div>
      <div class="flex gap-2">
        <button type="button" onclick="closeConfig()" class="flex-1 bg-sand-200 py-3 rounded-xl font-bold">إلغاء</button>
        <button type="submit" class="flex-1 bg-teal-700 text-white py-3 rounded-xl font-bold">حفظ</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function deliveryApp() {
  return {
    platforms: [],
    stats: { active: 0, todayOrders: 0, todayRevenue: '0', commissions: '0' },

    async init() {
      try {
        const res = await fetch('/api/sahab/delivery/integrations', {
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        // Add icons
        const icons = {
          hungerstation: 'هـ', jahez: 'جا', toshhel: 'تو', mrsool: 'مر',
          thechefz: 'تش', ninja: 'ني', toyou: 'TY', talabat: 'طل',
        };
        this.platforms = data.map(p => ({ ...p, icon: icons[p.platform] || p.platform.substring(0, 2) }));

        this.stats.active = this.platforms.filter(p => p.is_active).length;
        this.stats.todayOrders = this.platforms.reduce((s, p) => s + (p.orders_today || 0), 0);
      } catch (e) {
        console.error(e);
      }
    },

    configure(platform) {
      document.getElementById('webhookUrl').textContent = platform.webhook_url;
      document.getElementById('configModal').classList.remove('hidden');
      window._currentPlatform = platform.platform;
    },

    async toggleActive(platform) {
      try {
        await fetch(`/api/sahab/delivery/integrations/${platform.platform}/configure`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          },
          body: JSON.stringify({ is_active: !platform.is_active }),
        });
        platform.is_active = !platform.is_active;
        this.stats.active += platform.is_active ? 1 : -1;
      } catch (e) {
        alert('فشل التحديث');
      }
    },

    formatTime(ts) {
      const d = new Date(ts);
      return d.toLocaleString('ar-SA');
    },
  };
}

function closeConfig() {
  document.getElementById('configModal').classList.add('hidden');
}

document.getElementById('configForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = new FormData(e.target);
  const platform = window._currentPlatform;

  await fetch(`/api/sahab/delivery/integrations/${platform}/configure`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
    },
    body: JSON.stringify({
      is_active: true,
      credentials: {
        api_key: form.get('api_key'),
        secret: form.get('secret'),
      },
      commission_rate: parseFloat(form.get('commission_rate')) || 0,
      default_prep_time: parseInt(form.get('default_prep_time')) || 25,
      auto_accept: form.get('auto_accept') === 'on',
    }),
  });

  closeConfig();
  location.reload();
});
</script>
@endpush

@endsection
