@extends('sahab.layouts.app')

@section('title', 'الكاشير')
@section('page_title', 'نقطة البيع')
@section('page_subtitle', 'فتح وردية: ' . ($shift->opened_at?->format('H:i') ?? '—'))

@section('content')

<div x-data="posApp()" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  {{-- ============== Left: Products ============== --}}
  <div class="lg:col-span-2 bg-white rounded-2xl border border-sand-200 p-5">

    {{-- Search + Categories --}}
    <div class="flex gap-3 mb-4">
      <input type="text" x-model="search" placeholder="🔍 ابحث عن منتج أو كود باركود..."
             class="flex-1 px-4 py-3 rounded-xl border border-sand-200 focus:border-teal-700 focus:outline-none">
      <button @click="scanBarcode()" class="px-4 py-3 bg-sand-200 rounded-xl hover:bg-sand-300 transition" title="مسح باركود">
        📷
      </button>
    </div>

    <div class="flex gap-2 overflow-x-auto pb-2 mb-4">
      <button @click="selectedCat = null"
              :class="selectedCat === null ? 'bg-teal-700 text-white' : 'bg-sand-200 text-ink-700'"
              class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition">
        الكل
      </button>
      @foreach($categories as $cat)
        <button @click="selectedCat = {{ $cat->id }}"
                :class="selectedCat === {{ $cat->id }} ? 'bg-teal-700 text-white' : 'bg-sand-200 text-ink-700'"
                class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold transition whitespace-nowrap">
          {{ $cat->icon }} {{ $cat->name }}
        </button>
      @endforeach
    </div>

    {{-- Products grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[600px] overflow-y-auto">
      @foreach($products as $product)
        <button @click="addToCart({{ $product->id }})"
                x-show="(!selectedCat || {{ $product->category_id }} === selectedCat) && (!search || '{{ $product->name }}'.includes(search))"
                class="bg-sand-100 rounded-xl p-3 text-right hover:bg-sand-200 hover:shadow-md transition border border-transparent hover:border-teal-700/30">
          <div class="aspect-square mb-2 bg-white rounded-lg grid place-items-center text-3xl">
            @if($product->image)
              <img src="{{ $product->image }}" class="w-full h-full object-cover rounded-lg">
            @else
              📦
            @endif
          </div>
          <div class="font-semibold text-sm mb-1 truncate">{{ $product->name }}</div>
          <div class="font-mono font-bold text-teal-700 text-sm">{{ number_format($product->sale_price, 2) }} <span class="text-xs">ر.س</span></div>
          @if($product->track_inventory && $product->current_stock <= 5)
            <div class="text-xs text-orange-600 mt-1">⚠ متبقّي {{ $product->current_stock }}</div>
          @endif
        </button>
      @endforeach
    </div>
  </div>

  {{-- ============== Right: Cart ============== --}}
  <div class="bg-white rounded-2xl border border-sand-200 p-5 sticky top-6 self-start">

    <div class="flex items-center justify-between mb-4">
      <h2 class="font-display text-lg font-bold">السلّة</h2>
      <button @click="clearCart()" x-show="cart.length > 0" class="text-red-500 text-sm hover:underline">
        🗑 إفراغ
      </button>
    </div>

    {{-- Customer --}}
    <div class="mb-3">
      <input type="text" x-model="customerSearch" @input="searchCustomer()"
             placeholder="🔍 بحث عميل (اختياري)"
             class="w-full px-3 py-2 rounded-lg border border-sand-200 text-sm">
    </div>

    {{-- Order type --}}
    <div class="grid grid-cols-3 gap-2 mb-4">
      <button @click="orderType = 'dine_in'"
              :class="orderType === 'dine_in' ? 'bg-teal-700 text-white' : 'bg-sand-200'"
              class="py-2 rounded-lg text-sm font-semibold transition">🍽 صالة</button>
      <button @click="orderType = 'takeaway'"
              :class="orderType === 'takeaway' ? 'bg-teal-700 text-white' : 'bg-sand-200'"
              class="py-2 rounded-lg text-sm font-semibold transition">🛍 سفري</button>
      <button @click="orderType = 'delivery'"
              :class="orderType === 'delivery' ? 'bg-teal-700 text-white' : 'bg-sand-200'"
              class="py-2 rounded-lg text-sm font-semibold transition">🛵 توصيل</button>
    </div>

    {{-- Items --}}
    <div class="border-t border-sand-200 pt-3 max-h-[300px] overflow-y-auto">
      <template x-if="cart.length === 0">
        <div class="text-center py-12 text-ink-700/50">
          <div class="text-4xl mb-2">🛒</div>
          <p class="text-sm">السلّة فارغة</p>
          <p class="text-xs mt-1">اضغط على منتج لإضافته</p>
        </div>
      </template>

      <template x-for="(item, idx) in cart" :key="idx">
        <div class="flex items-center gap-3 py-2 border-b border-sand-200 last:border-0">
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm truncate" x-text="item.name"></div>
            <div class="text-xs text-ink-700/60">
              <span x-text="item.unit_price.toFixed(2)"></span> ر.س × <span x-text="item.quantity"></span>
            </div>
          </div>
          <div class="flex items-center gap-1 bg-sand-200 rounded-lg px-1">
            <button @click="changeQty(idx, -1)" class="w-7 h-7 grid place-items-center text-ink-700 hover:text-teal-700">−</button>
            <span class="w-6 text-center font-mono font-bold" x-text="item.quantity"></span>
            <button @click="changeQty(idx, +1)" class="w-7 h-7 grid place-items-center text-ink-700 hover:text-teal-700">+</button>
          </div>
          <div class="font-mono font-bold text-teal-700 text-sm w-16 text-left">
            <span x-text="(item.unit_price * item.quantity).toFixed(2)"></span>
          </div>
        </div>
      </template>
    </div>

    {{-- Totals --}}
    <div class="border-t border-sand-200 pt-3 mt-3 space-y-1 text-sm">
      <div class="flex justify-between text-ink-700/70">
        <span>المجموع الفرعي</span>
        <span class="font-mono" x-text="subtotal().toFixed(2) + ' ر.س'"></span>
      </div>
      <div class="flex justify-between text-ink-700/70">
        <span>ض.م 15%</span>
        <span class="font-mono" x-text="vat().toFixed(2) + ' ر.س'"></span>
      </div>
      <div class="flex justify-between text-ink-700/70" x-show="discount > 0">
        <span>الخصم</span>
        <span class="font-mono text-red-500">- <span x-text="discount.toFixed(2)"></span> ر.س</span>
      </div>
      <div class="flex justify-between text-lg font-bold border-t border-sand-200 pt-2">
        <span>الإجمالي</span>
        <span class="font-mono text-teal-700" x-text="total().toFixed(2) + ' ر.س'"></span>
      </div>
    </div>

    {{-- Payment buttons --}}
    <div class="grid grid-cols-2 gap-2 mt-4">
      <button @click="checkout('cash')" :disabled="cart.length === 0"
              class="bg-teal-700 hover:bg-teal-900 disabled:bg-gray-300 text-white py-3 rounded-xl font-bold transition">
        💵 نقدي
      </button>
      <button @click="checkout('mada')" :disabled="cart.length === 0"
              class="bg-copper-500 hover:bg-copper-600 disabled:bg-gray-300 text-white py-3 rounded-xl font-bold transition">
        💳 مدى
      </button>
      <button @click="checkout('apple_pay')" :disabled="cart.length === 0"
              class="bg-black hover:bg-gray-800 disabled:bg-gray-300 text-white py-3 rounded-xl font-bold transition">
         Pay
      </button>
      <button @click="checkout('stc_pay')" :disabled="cart.length === 0"
              class="bg-purple-600 hover:bg-purple-700 disabled:bg-gray-300 text-white py-3 rounded-xl font-bold transition">
        STC Pay
      </button>
    </div>
  </div>

</div>

{{-- ============== Receipt Modal ============== --}}
<div x-show="showReceipt" x-cloak
     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 grid place-items-center p-4"
     @click.self="closeReceipt()">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
    <div class="text-center mb-4">
      <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-green-500 grid place-items-center text-white text-3xl">✓</div>
      <h3 class="font-display text-2xl font-bold">تمّ بنجاح!</h3>
      <p class="text-ink-700/60 text-sm">رقم الفاتورة: <span x-text="lastInvoice?.invoice_number"></span></p>
    </div>

    <div class="bg-sand-100 rounded-xl p-4 font-mono text-sm space-y-1">
      <div class="text-center font-bold mb-2">{{ auth()->user()->tenant->name }}</div>
      <div class="text-center text-xs text-ink-700/60 mb-2">الرقم الضريبي: {{ auth()->user()->tenant->vat_number }}</div>
      <div class="border-t border-dashed border-ink-700/30 my-2"></div>
      <template x-for="item in lastInvoice?.items || []">
        <div class="flex justify-between">
          <span x-text="item.product_name + ' ×' + item.quantity"></span>
          <span x-text="item.total.toFixed(2)"></span>
        </div>
      </template>
      <div class="border-t border-dashed border-ink-700/30 my-2"></div>
      <div class="flex justify-between"><span>الفرعي:</span><span x-text="lastInvoice?.subtotal.toFixed(2)"></span></div>
      <div class="flex justify-between"><span>ض.م:</span><span x-text="lastInvoice?.vat_amount.toFixed(2)"></span></div>
      <div class="flex justify-between font-bold text-base"><span>الإجمالي:</span><span x-text="lastInvoice?.total_amount.toFixed(2) + ' ر.س'"></span></div>
    </div>

    <div class="grid grid-cols-3 gap-2 mt-4">
      <button @click="printReceipt()" class="bg-teal-700 text-white py-2 rounded-lg font-semibold text-sm">🖨 طباعة</button>
      <button @click="sendWhatsApp()" class="bg-green-600 text-white py-2 rounded-lg font-semibold text-sm">💬 واتساب</button>
      <button @click="closeReceipt()" class="bg-sand-200 text-ink-700 py-2 rounded-lg font-semibold text-sm">إغلاق</button>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function posApp() {
  return {
    cart: [],
    selectedCat: null,
    search: '',
    customerSearch: '',
    customerId: null,
    orderType: 'dine_in',
    discount: 0,
    showReceipt: false,
    lastInvoice: null,

    products: @json($products->keyBy('id')),

    addToCart(productId) {
      const product = this.products[productId];
      if (!product) return;

      const existing = this.cart.find(i => i.product_id === productId);
      if (existing) {
        existing.quantity++;
      } else {
        this.cart.push({
          product_id: productId,
          name: product.name,
          unit_price: parseFloat(product.sale_price),
          quantity: 1,
        });
      }
    },

    changeQty(idx, delta) {
      this.cart[idx].quantity += delta;
      if (this.cart[idx].quantity <= 0) this.cart.splice(idx, 1);
    },

    clearCart() {
      if (confirm('متأكّد من إفراغ السلّة؟')) this.cart = [];
    },

    subtotal() {
      // We assume vat_included = true, so we extract VAT
      const total = this.cart.reduce((s, i) => s + (i.unit_price * i.quantity), 0);
      return total / 1.15;
    },

    vat() {
      return this.subtotal() * 0.15;
    },

    total() {
      return this.cart.reduce((s, i) => s + (i.unit_price * i.quantity), 0) - this.discount;
    },

    async checkout(method) {
      if (this.cart.length === 0) return;

      const total = this.total();
      const payload = {
        items: this.cart.map(i => ({
          product_id: i.product_id,
          quantity: i.quantity,
          unit_price: i.unit_price,
        })),
        customer_id: this.customerId,
        order_type: this.orderType,
        discount_amount: this.discount,
        payments: [{ method, amount: total }],
      };

      try {
        const res = await fetch('/api/sahab/pos/invoices', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.success) {
          this.lastInvoice = data.invoice;
          this.showReceipt = true;
        } else {
          alert('فشل: ' + (data.error || 'خطأ غير معروف'));
        }
      } catch (e) {
        alert('خطأ في الاتصال');
      }
    },

    closeReceipt() {
      this.showReceipt = false;
      this.cart = [];
      this.customerId = null;
      this.customerSearch = '';
      this.discount = 0;
    },

    printReceipt() {
      window.print();
    },

    sendWhatsApp() {
      if (!this.customerSearch) {
        alert('أضف عميلاً أوّلاً لإرسال الإيصال');
        return;
      }
      // In production, would call API to send via Unifonic
      alert('سيُرسل الإيصال على واتساب');
    },

    scanBarcode() {
      const code = prompt('امسح الباركود أو اكتبه:');
      if (code) {
        // Search products
        for (const id in this.products) {
          if (this.products[id].barcode === code || this.products[id].sku === code) {
            this.addToCart(parseInt(id));
            return;
          }
        }
        alert('المنتج غير موجود');
      }
    },

    searchCustomer() {
      // Simple debounce - in production would call API
    },
  };
}
</script>
@endpush

@endsection
