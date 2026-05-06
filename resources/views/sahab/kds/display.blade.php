<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>شاشة المطبخ — سحاب</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Tajawal', sans-serif; background: #0E1F1A; color: white; }
  .urgent { animation: pulse-red 1.5s ease-in-out infinite; }
  @keyframes pulse-red { 0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.7); } 50% { box-shadow: 0 0 0 12px rgba(239,68,68,0); } }
</style>
</head>
<body x-data="kdsApp()" x-init="init()" class="min-h-screen p-4">

<header class="flex items-center justify-between mb-4 pb-3 border-b border-white/10">
  <div class="flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-yellow-400 to-orange-500 grid place-items-center text-black font-bold">🍳</div>
    <div>
      <h1 class="font-bold text-xl">شاشة المطبخ</h1>
      <p class="text-xs text-white/50">{{ auth()->user()->tenant->name ?? '' }}</p>
    </div>
  </div>
  <div class="flex items-center gap-3 text-sm">
    <span class="text-white/60">طلبات نشطة:</span>
    <span class="font-bold text-2xl text-yellow-400" x-text="orders.length"></span>
    <span class="text-white/40 text-xs" x-text="lastUpdated"></span>
  </div>
</header>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
  <template x-for="order in orders" :key="order.id">
    <div class="bg-white/10 rounded-2xl p-4 border-2"
         :class="{
           'border-red-500 urgent': order.urgency === 'urgent',
           'border-yellow-500': order.urgency === 'warning',
           'border-white/10': order.urgency === 'normal'
         }">

      <div class="flex items-center justify-between mb-3">
        <div>
          <div class="text-xs text-white/50">رقم الطلب</div>
          <div class="font-bold text-lg" x-text="order.invoice_number"></div>
        </div>
        <div class="text-left">
          <div class="font-bold text-3xl"
               :class="{
                 'text-red-400': order.urgency === 'urgent',
                 'text-yellow-400': order.urgency === 'warning',
                 'text-white': order.urgency === 'normal'
               }"
               x-text="order.wait_minutes + ' د'"></div>
        </div>
      </div>

      <div class="flex items-center gap-2 mb-3 text-xs">
        <span class="bg-white/10 px-2 py-1 rounded-full"
              x-text="order.order_type === 'dine_in' ? '🍽 صالة' : (order.order_type === 'takeaway' ? '🛍 سفري' : '🛵 توصيل')"></span>
        <template x-if="order.table_number">
          <span class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-full" x-text="'طاولة ' + order.table_number"></span>
        </template>
        <template x-if="order.source !== 'pos'">
          <span class="bg-orange-500/20 text-orange-400 px-2 py-1 rounded-full" x-text="getPlatformLabel(order.source)"></span>
        </template>
      </div>

      <div class="space-y-2 mb-4 max-h-[300px] overflow-y-auto">
        <template x-for="item in order.items" :key="item.name">
          <div class="bg-white/5 rounded-lg p-3">
            <div class="flex items-start justify-between gap-2">
              <div class="font-semibold" x-text="item.name"></div>
              <div class="bg-yellow-500 text-black w-7 h-7 rounded-full grid place-items-center font-bold text-sm flex-shrink-0"
                   x-text="item.quantity"></div>
            </div>
            <template x-if="item.instructions">
              <div class="text-yellow-400 text-xs mt-1">📝 <span x-text="item.instructions"></span></div>
            </template>
          </div>
        </template>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <button @click="updateStatus(order.id, 'preparing')"
                x-show="order.status === 'pending'"
                class="bg-yellow-500 hover:bg-yellow-600 text-black py-3 rounded-xl font-bold text-sm transition">
          🔥 ابدأ التحضير
        </button>
        <button @click="updateStatus(order.id, 'ready')"
                x-show="order.status === 'preparing'"
                class="bg-green-500 hover:bg-green-600 text-white py-3 rounded-xl font-bold text-sm transition col-span-2">
          ✓ جاهز للتسليم
        </button>
      </div>
    </div>
  </template>

  <template x-if="orders.length === 0">
    <div class="col-span-full text-center py-20 text-white/40">
      <div class="text-7xl mb-4">🍽</div>
      <p class="text-2xl">لا توجد طلبات حاليّاً</p>
      <p class="text-sm mt-2">عند وصول طلب جديد، سيظهر هنا فوراً</p>
    </div>
  </template>
</div>

<audio id="newOrderSound" preload="auto">
  <source src="data:audio/mpeg;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA" type="audio/mpeg">
</audio>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function kdsApp() {
  return {
    orders: [],
    lastUpdated: '',
    lastOrderCount: 0,

    async init() {
      await this.fetchOrders();
      // تحديث كل 10 ثوان
      setInterval(() => this.fetchOrders(), 10000);
    },

    async fetchOrders() {
      try {
        const res = await fetch('/api/sahab/kds/orders', {
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          }
        });
        const newOrders = await res.json();

        // إذا في طلب جديد — اعمل تنبيه صوتي
        if (newOrders.length > this.lastOrderCount) {
          document.getElementById('newOrderSound')?.play().catch(() => {});
        }

        this.orders = newOrders;
        this.lastOrderCount = newOrders.length;
        this.lastUpdated = '· آخر تحديث: ' + new Date().toLocaleTimeString('ar-SA');
      } catch (e) {
        console.error('فشل التحديث', e);
      }
    },

    async updateStatus(invoiceId, status) {
      try {
        await fetch(`/api/sahab/kds/orders/${invoiceId}/status`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ status }),
        });
        await this.fetchOrders();
      } catch (e) {
        alert('فشل التحديث');
      }
    },

    getPlatformLabel(source) {
      return {
        'hungerstation': '🟠 هنقرستيشن',
        'jahez': '🔴 جاهز',
        'toshhel': '🟢 توصيل',
        'mrsool': '🟣 مرسول',
        'thechefz': '⚫ تشيفز',
      }[source] || source;
    },
  };
}
</script>
</body>
</html>
