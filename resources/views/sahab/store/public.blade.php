<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $store->name }} {{ $store->tagline ? '— ' . $store->tagline : '' }}</title>
<meta name="description" content="{{ $store->meta_description ?? $store->description }}">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: '{{ $store->primary_color }}',
        accent: '{{ $store->secondary_color }}',
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
  body { font-family: 'Tajawal', sans-serif; }
  .brand-bg { background: {{ $store->primary_color }}; }
  .brand-text { color: {{ $store->primary_color }}; }
  .accent-bg { background: {{ $store->secondary_color }}; }
  [x-cloak] { display: none !important; }
</style>
</head>
<body class="bg-gray-50" x-data="storeApp()" x-init="init()">

{{-- HEADER --}}
<header class="brand-bg text-white sticky top-0 z-40 shadow-lg">
  <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      @if($store->logo)
        <img src="{{ $store->logo }}" alt="{{ $store->name }}" class="w-12 h-12 rounded-xl bg-white">
      @else
        <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur grid place-items-center text-2xl font-bold">
          {{ mb_substr($store->name, 0, 1) }}
        </div>
      @endif
      <div>
        <h1 class="font-display font-bold text-xl leading-none">{{ $store->name }}</h1>
        @if($store->tagline)
          <p class="text-sm opacity-80 mt-1">{{ $store->tagline }}</p>
        @endif
      </div>
    </div>

    <button @click="cartOpen = true" class="relative bg-white/20 backdrop-blur hover:bg-white/30 transition w-12 h-12 rounded-xl grid place-items-center">
      🛒
      <span x-show="cart.length > 0"
            x-cloak
            class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full grid place-items-center"
            x-text="cart.length"></span>
    </button>
  </div>
</header>

{{-- HERO --}}
@if($store->cover_image)
  <div class="aspect-[3/1] max-h-72 bg-cover bg-center" style="background-image: url('{{ $store->cover_image }}')"></div>
@endif

@if(!$store->isOpenNow() && $store->show_when_closed)
  <div class="bg-yellow-100 border-r-4 border-yellow-500 px-4 py-3 text-yellow-800 text-sm">
    <strong>⚠ المحلّ مغلق حالياً.</strong>
    {{ $store->closed_message ?? 'يمكنك الاطّلاع على القائمة، وسنعود لاستقبال طلباتك خلال أوقات العمل.' }}
  </div>
@endif

{{-- CATEGORIES --}}
<div class="bg-white border-b border-gray-200 sticky top-[80px] z-30">
  <div class="max-w-5xl mx-auto px-4 py-3 overflow-x-auto">
    <div class="flex gap-2">
      <button @click="selectedCat = null"
              :class="selectedCat === null ? 'brand-bg text-white' : 'bg-gray-100 text-gray-700'"
              class="flex-shrink-0 px-5 py-2 rounded-full font-semibold text-sm whitespace-nowrap transition">
        الكلّ
      </button>
      @foreach($categories as $cat)
        <button @click="selectedCat = {{ $cat->id }}"
                :class="selectedCat === {{ $cat->id }} ? 'brand-bg text-white' : 'bg-gray-100 text-gray-700'"
                class="flex-shrink-0 px-5 py-2 rounded-full font-semibold text-sm whitespace-nowrap transition">
          {{ $cat->icon }} {{ $cat->name }}
        </button>
      @endforeach
    </div>
  </div>
</div>

{{-- PRODUCTS --}}
<main class="max-w-5xl mx-auto px-4 py-6">
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    @foreach($products as $product)
      <div x-show="!selectedCat || {{ $product->category_id ?? 'null' }} === selectedCat"
           class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition cursor-pointer"
           @click="openProduct({{ $product->id }})">

        <div class="aspect-square bg-gray-100 relative">
          @if($product->image)
            <img src="{{ $product->image }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
          @else
            <div class="w-full h-full grid place-items-center text-5xl">📦</div>
          @endif
        </div>

        <div class="p-3">
          <h3 class="font-bold text-sm line-clamp-2 h-10">{{ $product->name }}</h3>
          @if($store->show_prices)
            <div class="brand-text font-mono font-bold text-base mt-2">
              {{ number_format($product->sale_price, 2) }} <span class="text-xs opacity-70">ر.س</span>
            </div>
          @endif

          <button @click.stop="quickAdd({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->sale_price }}, '{{ $product->image ?? '' }}')"
                  class="mt-3 w-full brand-bg text-white py-2 rounded-lg font-bold text-sm hover:opacity-90 transition">
            + للسلّة
          </button>
        </div>
      </div>
    @endforeach
  </div>

  @if($products->isEmpty())
    <div class="text-center py-20 text-gray-400">
      <div class="text-7xl mb-4">📦</div>
      <p class="text-xl font-bold">لا توجد منتجات حاليّاً</p>
    </div>
  @endif
</main>

{{-- CART DRAWER --}}
<div x-show="cartOpen" x-cloak
     class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50"
     @click.self="cartOpen = false">

  <div class="absolute left-0 top-0 bottom-0 w-full max-w-md bg-white flex flex-col"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="-translate-x-full"
       x-transition:enter-end="translate-x-0">

    <header class="brand-bg text-white p-4 flex items-center justify-between">
      <h2 class="font-display font-bold text-xl">سلّتك</h2>
      <button @click="cartOpen = false" class="w-9 h-9 rounded-lg bg-white/20 hover:bg-white/30">×</button>
    </header>

    <div class="flex-1 overflow-y-auto p-4">
      <template x-if="cart.length === 0">
        <div class="text-center py-20 text-gray-400">
          <div class="text-6xl mb-4">🛒</div>
          <p class="font-bold">السلّة فارغة</p>
          <p class="text-sm mt-2">أضف منتجاتك المفضّلة</p>
        </div>
      </template>

      <template x-for="(item, idx) in cart" :key="idx">
        <div class="flex gap-3 py-3 border-b border-gray-100 last:border-0">
          <div class="w-16 h-16 bg-gray-100 rounded-xl overflow-hidden flex-shrink-0">
            <template x-if="item.image"><img :src="item.image" class="w-full h-full object-cover"></template>
            <template x-if="!item.image"><div class="w-full h-full grid place-items-center text-2xl">📦</div></template>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold text-sm truncate" x-text="item.name"></div>
            @if($store->show_prices)
              <div class="brand-text font-mono font-bold mt-1" x-text="(item.unit_price * item.quantity).toFixed(2) + ' ر.س'"></div>
            @endif
            <div class="flex items-center gap-2 mt-2 bg-gray-100 rounded-lg p-1 w-fit">
              <button @click="changeQty(idx, -1)" class="w-7 h-7 grid place-items-center font-bold">−</button>
              <span class="w-7 text-center font-mono font-bold" x-text="item.quantity"></span>
              <button @click="changeQty(idx, 1)" class="w-7 h-7 grid place-items-center font-bold">+</button>
            </div>
          </div>
        </div>
      </template>
    </div>

    @if($store->show_prices)
      <div class="border-t border-gray-200 p-4 space-y-1 text-sm">
        <div class="flex justify-between text-gray-600">
          <span>المجموع</span>
          <span class="font-mono" x-text="subtotal().toFixed(2) + ' ر.س'"></span>
        </div>
        @if($store->delivery_fee > 0)
          <div class="flex justify-between text-gray-600" x-show="orderType === 'delivery'">
            <span>التوصيل</span>
            <span class="font-mono">{{ number_format($store->delivery_fee, 2) }} ر.س</span>
          </div>
        @endif
        <div class="flex justify-between font-bold text-lg pt-2 border-t border-gray-200">
          <span>الإجمالي</span>
          <span class="font-mono brand-text" x-text="total().toFixed(2) + ' ر.س'"></span>
        </div>
      </div>
    @endif

    {{-- Customer info --}}
    <template x-if="cart.length > 0">
      <div class="border-t border-gray-200 p-4 space-y-2">
        <input type="text" x-model="customer.name" placeholder="الاسم *" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">
        <input type="tel" x-model="customer.phone" placeholder="جوّالك *" class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm">

        @if($store->enable_delivery && $store->enable_pickup)
          <div class="grid grid-cols-2 gap-2">
            <button @click="orderType = 'delivery'"
                    :class="orderType === 'delivery' ? 'brand-bg text-white' : 'bg-gray-100'"
                    class="py-2 rounded-lg font-bold text-sm">🛵 توصيل</button>
            <button @click="orderType = 'pickup'"
                    :class="orderType === 'pickup' ? 'brand-bg text-white' : 'bg-gray-100'"
                    class="py-2 rounded-lg font-bold text-sm">🏪 استلام</button>
          </div>
        @endif

        <textarea x-show="orderType === 'delivery'" x-model="customer.address"
                  placeholder="العنوان (الحيّ، الشارع، رقم المبنى...)"
                  rows="2"
                  class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm resize-none"></textarea>

        <textarea x-model="customer.notes" placeholder="ملاحظات (اختياري)"
                  rows="2"
                  class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm resize-none"></textarea>

        @if($store->enable_online_payment ?? false)
          {{-- Payment method selector --}}
          <div class="border-t border-gray-200 pt-3 mt-3">
            <p class="text-sm font-bold mb-2">طريقة الدفع</p>
            <div class="grid grid-cols-2 gap-2">
              @if($store->enable_apple_pay ?? false)
                <button @click="paymentMethod = 'apple_pay'"
                        :class="paymentMethod === 'apple_pay' ? 'border-black bg-black text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm flex items-center justify-center gap-1">
                   Pay
                </button>
              @endif
              @if($store->enable_google_pay ?? false)
                <button @click="paymentMethod = 'google_pay'"
                        :class="paymentMethod === 'google_pay' ? 'border-blue-500 bg-blue-500 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  G Pay
                </button>
              @endif
              @if($store->enable_mada ?? true)
                <button @click="paymentMethod = 'mada'"
                        :class="paymentMethod === 'mada' ? 'border-teal-700 bg-teal-700 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  💳 مدى
                </button>
              @endif
              @if($store->enable_visa_master ?? true)
                <button @click="paymentMethod = 'visa'"
                        :class="paymentMethod === 'visa' ? 'border-blue-700 bg-blue-700 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  Visa/Master
                </button>
              @endif
              @if($store->enable_stc_pay ?? false)
                <button @click="paymentMethod = 'stc_pay'"
                        :class="paymentMethod === 'stc_pay' ? 'border-purple-700 bg-purple-700 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  STC Pay
                </button>
              @endif
              @if($store->enable_tabby ?? false)
                <button @click="paymentMethod = 'tabby'"
                        :class="paymentMethod === 'tabby' ? 'border-green-600 bg-green-600 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  ادفع لاحقاً (Tabby)
                </button>
              @endif
              @if($store->enable_tamara ?? false)
                <button @click="paymentMethod = 'tamara'"
                        :class="paymentMethod === 'tamara' ? 'border-pink-600 bg-pink-600 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm">
                  4 أقساط (Tamara)
                </button>
              @endif
              @if($store->enable_cash_on_delivery ?? true)
                <button @click="paymentMethod = 'cash_on_delivery'"
                        :class="paymentMethod === 'cash_on_delivery' ? 'border-orange-500 bg-orange-500 text-white' : 'border-gray-200'"
                        class="border-2 p-3 rounded-xl font-bold text-sm col-span-2">
                  💵 الدفع عند الاستلام
                </button>
              @endif
            </div>
          </div>
        @endif

        <button @click="checkout()"
                :disabled="loading || !customer.name || !customer.phone"
                class="w-full brand-bg text-white py-4 rounded-xl font-bold disabled:opacity-50 transition mt-3">
          <template x-if="!loading">
            <span x-text="paymentMethod && paymentMethod !== 'cash_on_delivery' ? '💳 إكمال الدفع' : '📱 إرسال الطلب على واتساب'"></span>
          </template>
          <template x-if="loading">
            <span>جاري المعالجة...</span>
          </template>
        </button>
      </div>
    </template>
  </div>
</div>

{{-- FOOTER --}}
<footer class="bg-gray-100 mt-12 py-6 text-center text-gray-600 text-sm">
  <p>{{ $store->name }} · جميع الحقوق محفوظة</p>
  <p class="mt-2 text-xs opacity-70">تشغيل بـ <strong class="brand-text">منصّة سحاب 🌥</strong></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function storeApp() {
  return {
    cart: JSON.parse(localStorage.getItem('store_cart_{{ $store->slug }}') || '[]'),
    cartOpen: false,
    selectedCat: null,
    orderType: '{{ $store->enable_delivery ? "delivery" : "pickup" }}',
    customer: { name: '', phone: '', address: '', notes: '' },
    loading: false,
    paymentMethod: '{{ $store->enable_online_payment ? "apple_pay" : "" }}',

    init() {
      // Save cart to localStorage on changes
      this.$watch('cart', (val) => {
        localStorage.setItem('store_cart_{{ $store->slug }}', JSON.stringify(val));
      }, { deep: true });
    },

    quickAdd(id, name, price, image) {
      const existing = this.cart.find(i => i.product_id === id);
      if (existing) {
        existing.quantity++;
      } else {
        this.cart.push({
          product_id: id,
          name: name,
          unit_price: parseFloat(price),
          quantity: 1,
          image: image || null,
          notes: '',
        });
      }
      // Show toast
      this.showToast(`✓ ${name} أُضيف للسلّة`);
    },

    changeQty(idx, delta) {
      this.cart[idx].quantity += delta;
      if (this.cart[idx].quantity <= 0) this.cart.splice(idx, 1);
    },

    subtotal() {
      return this.cart.reduce((s, i) => s + (i.unit_price * i.quantity), 0);
    },

    total() {
      let t = this.subtotal();
      if (this.orderType === 'delivery') t += {{ $store->delivery_fee ?? 0 }};
      return t;
    },

    async checkout() {
      if (!this.customer.name || !this.customer.phone) {
        alert('عبّي اسمك وجوّالك');
        return;
      }
      if (this.orderType === 'delivery' && !this.customer.address) {
        alert('اكتب عنوان التوصيل');
        return;
      }

      this.loading = true;

      try {
        // إضافة العناصر للسلّة في السيرفر
        for (const item of this.cart) {
          await fetch('/store/{{ $store->slug }}/cart/add', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
              product_id: item.product_id,
              quantity: item.quantity,
            }),
          });
        }

        // إنشاء الطلب
        const res = await fetch('/store/{{ $store->slug }}/checkout', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          credentials: 'same-origin',
          body: JSON.stringify({
            customer_name: this.customer.name,
            customer_phone: this.customer.phone,
            order_type: this.orderType,
            delivery_address: this.customer.address,
            notes: this.customer.notes,
          }),
        });

        const data = await res.json();
        if (!data.success) {
          alert(data.error || 'حدث خطأ');
          return;
        }

        const invoiceId = data.invoice_id;

        // إذا الدفع الإلكتروني مفعّل وطريقة دفع مختارة (غير واتساب) — انتقل للدفع
        if (this.paymentMethod && this.paymentMethod !== 'whatsapp_only' && {{ $store->enable_online_payment ? 'true' : 'false' }}) {
          await this.processOnlinePayment(invoiceId);
        } else {
          // الطريقة التقليديّة — فتح الواتساب
          this.cart = [];
          window.location.href = data.whatsapp_url;
        }
      } catch (e) {
        alert('فشل الاتصال — حاول مرّة أخرى');
      } finally {
        this.loading = false;
      }
    },

    async processOnlinePayment(invoiceId) {
      const res = await fetch(`/store/{{ $store->slug }}/payment/initiate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          invoice_id: invoiceId,
          payment_method: this.paymentMethod,
          customer_name: this.customer.name,
          customer_email: this.customer.email || '',
          customer_mobile: this.customer.phone,
        }),
      });
      const data = await res.json();

      if (!data.success) {
        alert(data.error || 'فشل بدء الدفع');
        return;
      }

      // الدفع عند الاستلام — مباشرة لصفحة النجاح
      if (data.method === 'cod' || data.method === 'bank') {
        this.cart = [];
        window.location.href = data.redirect_url || `/store/{{ $store->slug }}/order/${invoiceId}/success`;
        return;
      }

      // Apple Pay / Google Pay — client side
      if (data.method === 'client_side') {
        await this.handleClientSidePayment(data, invoiceId);
        return;
      }

      // Redirect (Tabby, Tamara, Mada, Visa عبر صفحة Moyasar)
      if (data.redirect_url) {
        this.cart = [];
        window.location.href = data.redirect_url;
      }
    },

    async handleClientSidePayment(data, invoiceId) {
      // Apple Pay (Web)
      if (this.paymentMethod === 'apple_pay' && window.ApplePaySession) {
        const config = data.apple_pay_config;
        const session = new ApplePaySession(3, {
          countryCode: config.country_code,
          currencyCode: data.currency,
          supportedNetworks: config.supported_networks,
          merchantCapabilities: config.merchant_capabilities,
          total: {
            label: config.merchant_name,
            amount: (data.amount / 100).toFixed(2),
          },
        });

        session.onvalidatemerchant = (event) => {
          // يحتاج server-side validation مع Apple
          fetch('/store/{{ $store->slug }}/payment/apple-pay/validate', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ validation_url: event.validationURL }),
          }).then(r => r.json()).then(merchantSession => {
            session.completeMerchantValidation(merchantSession);
          });
        };

        session.onpaymentauthorized = async (event) => {
          const result = await fetch(`/store/{{ $store->slug }}/payment/complete/${data.transaction_id}`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({
              source: {
                type: 'applepay',
                token: btoa(JSON.stringify(event.payment.token)),
              },
            }),
          });
          const json = await result.json();
          if (json.success) {
            session.completePayment(ApplePaySession.STATUS_SUCCESS);
            this.cart = [];
            window.location.href = `/store/{{ $store->slug }}/order/${invoiceId}/success`;
          } else {
            session.completePayment(ApplePaySession.STATUS_FAILURE);
            alert(json.error || 'فشل الدفع');
          }
        };

        session.begin();
        return;
      }

      // Google Pay — fallback لو نتاج Moyasar.js مش متاح
      alert('Google Pay قادم قريباً عبر Moyasar.js. الآن استخدم مدى أو Visa.');
    },

    showToast(msg) {
      const toast = document.createElement('div');
      toast.className = 'fixed bottom-6 right-6 bg-green-600 text-white px-5 py-3 rounded-xl shadow-lg z-50 font-semibold';
      toast.textContent = msg;
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 2500);
    },

    openProduct(id) {
      // يُفتح في صفحة منفصلة (اختياري)
      window.location.href = `/store/{{ $store->slug }}/product/${id}`;
    },
  };
}
</script>
</body>
</html>
