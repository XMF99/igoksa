@extends('sahab.layouts.app')

@section('title', 'محرّر المنيو')
@section('page_title', '📋 محرّر المنيو')
@section('page_subtitle', 'صمّم المنيو الإلكتروني الذي يراه عملاؤك')

@section('content')
<div x-data="menuBuilder()" x-init="init()" class="max-w-7xl mx-auto">

  {{-- Top bar --}}
  <div class="bg-white rounded-2xl border border-sand-200 p-4 mb-6 flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <a href="/app/store" class="text-ink-700/60 hover:text-ink-900 text-sm">← إعدادات المتجر</a>
      <span class="text-ink-700/30">|</span>
      <a href="/store/{{ $store->slug }}" target="_blank" class="text-teal-700 font-semibold text-sm hover:underline">
        🔗 معاينة المتجر
      </a>
    </div>
    <div class="flex items-center gap-2">
      <button @click="showImportModal = true" class="bg-sand-100 hover:bg-sand-200 px-4 py-2 rounded-xl text-sm font-bold">
        📥 استيراد من POS
      </button>
      <button @click="showCategoryModal = true; editingCategory = {}" class="bg-teal-700 hover:bg-teal-900 text-white px-4 py-2 rounded-xl text-sm font-bold">
        + فئة جديدة
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    {{-- Left: Categories list --}}
    <aside class="lg:col-span-3">
      <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden sticky top-4">
        <div class="p-4 bg-sand-100 border-b border-sand-200">
          <h3 class="font-bold">📂 الفئات</h3>
          <p class="text-xs text-ink-700/60 mt-1">اسحب لإعادة الترتيب</p>
        </div>

        <div class="p-2">
          <button @click="selectedCategory = null"
                  :class="selectedCategory === null ? 'bg-teal-700 text-white' : 'hover:bg-sand-100'"
                  class="w-full text-right p-3 rounded-lg flex items-center justify-between transition">
            <span class="font-semibold">🌟 كل المنتجات</span>
            <span class="text-xs opacity-70" x-text="items.length"></span>
          </button>

          <template x-for="cat in categories" :key="cat.id">
            <button @click="selectedCategory = cat.id"
                    :class="selectedCategory === cat.id ? 'bg-teal-700 text-white' : 'hover:bg-sand-100'"
                    class="w-full text-right p-3 rounded-lg flex items-center justify-between transition group">
              <div class="flex items-center gap-2">
                <span x-text="cat.icon || '📦'"></span>
                <span class="font-semibold" x-text="cat.name"></span>
              </div>
              <div class="flex items-center gap-1">
                <span class="text-xs opacity-70" x-text="cat.items_count || 0"></span>
                <button @click.stop="editCategory(cat)"
                        :class="selectedCategory === cat.id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                        class="p-1 rounded hover:bg-white/20 transition">
                  ✎
                </button>
              </div>
            </button>
          </template>
        </div>
      </div>
    </aside>

    {{-- Center: Items grid --}}
    <main class="lg:col-span-6">
      <div class="bg-white rounded-2xl border border-sand-200 p-4 mb-4">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="font-bold text-lg" x-text="selectedCategory === null ? 'كل المنتجات' : (categories.find(c => c.id === selectedCategory)?.name || '')"></h3>
            <p class="text-sm text-ink-700/60" x-text="`${filteredItems.length} منتج`"></p>
          </div>
          <input x-model="searchQuery" placeholder="🔍 ابحث..." class="px-3 py-2 rounded-lg border border-sand-200 text-sm">
        </div>

        <div x-show="filteredItems.length === 0" class="text-center py-16 text-ink-700/40">
          <div class="text-6xl mb-3">📋</div>
          <p class="font-bold">لا توجد منتجات في هذه الفئة</p>
          <p class="text-sm mt-2">أضف منتجات من القائمة على اليسار</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <template x-for="item in filteredItems" :key="item.id">
            <div class="border border-sand-200 rounded-xl p-3 hover:border-teal-700 hover:shadow-md transition cursor-pointer relative group"
                 @click="editItem(item)">

              {{-- Badges --}}
              <div class="absolute top-2 left-2 flex flex-wrap gap-1 z-10">
                <span x-show="item.is_featured" class="bg-copper-400 text-ink-900 text-[10px] px-2 py-0.5 rounded-full font-bold">⭐</span>
                <span x-show="item.is_bestseller" class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">🔥</span>
                <span x-show="item.is_new" class="bg-green-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">جديد</span>
              </div>

              <div class="flex gap-3">
                <div class="w-20 h-20 rounded-lg bg-sand-100 overflow-hidden flex-shrink-0">
                  <template x-if="item.display_image || item.product?.image">
                    <img :src="item.display_image || item.product?.image" class="w-full h-full object-cover">
                  </template>
                  <template x-if="!item.display_image && !item.product?.image">
                    <div class="w-full h-full grid place-items-center text-3xl">🍽</div>
                  </template>
                </div>

                <div class="flex-1 min-w-0">
                  <h4 class="font-bold text-sm truncate" x-text="item.display_name || item.product?.name"></h4>
                  <div class="flex items-baseline gap-2 mt-1">
                    <span class="font-mono font-bold text-teal-700" x-text="(item.display_price || item.product?.sale_price || 0) + ' ر.س'"></span>
                    <span x-show="item.compare_at_price"
                          class="text-xs line-through text-ink-700/40"
                          x-text="item.compare_at_price + ' ر.س'"></span>
                  </div>
                  <div class="flex items-center gap-2 mt-2 text-xs text-ink-700/60">
                    <span x-show="item.is_visible" class="text-green-600">✓ مرئي</span>
                    <span x-show="!item.is_visible" class="text-gray-400">○ مخفي</span>
                    <span x-show="!item.is_available" class="text-orange-500">⚠ غير متوفّر</span>
                  </div>
                </div>

                <button @click.stop="confirmRemove(item)" class="opacity-0 group-hover:opacity-100 text-red-500 hover:bg-red-50 p-1 rounded transition">
                  🗑
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </main>

    {{-- Right: Available products --}}
    <aside class="lg:col-span-3">
      <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden sticky top-4">
        <div class="p-4 bg-sand-100 border-b border-sand-200">
          <h3 class="font-bold">🛍 منتجاتك (POS)</h3>
          <p class="text-xs text-ink-700/60 mt-1">اضغط لإضافة للمنيو</p>
        </div>
        <div class="p-2 max-h-96 overflow-y-auto">
          <template x-if="availableProducts.length === 0">
            <p class="text-sm text-ink-700/60 text-center p-4">كل المنتجات في المنيو</p>
          </template>
          <template x-for="prod in availableProducts" :key="prod.id">
            <button @click="addProductToMenu(prod)"
                    class="w-full text-right p-2 rounded-lg hover:bg-sand-100 flex items-center gap-2 transition">
              <div class="w-10 h-10 rounded-lg bg-sand-100 grid place-items-center flex-shrink-0">
                <template x-if="prod.image"><img :src="prod.image" class="w-full h-full object-cover rounded-lg"></template>
                <template x-if="!prod.image"><span>📦</span></template>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold text-xs truncate" x-text="prod.name"></div>
                <div class="font-mono text-xs text-teal-700" x-text="prod.sale_price + ' ر.س'"></div>
              </div>
              <span class="text-teal-700 text-xl">+</span>
            </button>
          </template>
        </div>
      </div>
    </aside>
  </div>

  {{-- Category Modal --}}
  <div x-show="showCategoryModal" x-cloak
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 grid place-items-center p-4"
       @click.self="showCategoryModal = false">
    <div class="bg-white rounded-2xl max-w-md w-full p-6">
      <h3 class="font-bold text-xl mb-4" x-text="editingCategory.id ? 'تعديل فئة' : 'فئة جديدة'"></h3>
      <div class="space-y-3">
        <div>
          <label class="text-sm font-semibold mb-1 block">الاسم *</label>
          <input x-model="editingCategory.name" placeholder="مثل: مقبّلات"
                 class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-semibold mb-1 block">رمز Emoji</label>
            <input x-model="editingCategory.icon" placeholder="🍽" maxlength="4"
                   class="w-full px-4 py-2.5 rounded-xl border border-sand-200 text-center text-xl">
          </div>
          <div>
            <label class="text-sm font-semibold mb-1 block">اللون</label>
            <input type="color" x-model="editingCategory.color"
                   class="w-full h-12 rounded-xl border border-sand-200 cursor-pointer">
          </div>
        </div>
        <label class="flex items-center gap-2">
          <input type="checkbox" x-model="editingCategory.is_featured" class="w-4 h-4">
          <span class="text-sm">فئة مميّزة</span>
        </label>
      </div>
      <div class="flex gap-2 mt-6">
        <button @click="showCategoryModal = false" class="flex-1 bg-sand-100 hover:bg-sand-200 py-3 rounded-xl font-bold">إلغاء</button>
        <button @click="saveCategory()" class="flex-1 bg-teal-700 hover:bg-teal-900 text-white py-3 rounded-xl font-bold">حفظ</button>
      </div>
    </div>
  </div>

  {{-- Item Edit Modal --}}
  <div x-show="showItemModal" x-cloak
       class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 grid place-items-center p-4"
       @click.self="showItemModal = false">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto">
      <h3 class="font-bold text-xl mb-4">تخصيص المنتج للمنيو</h3>
      <div class="space-y-3" x-show="editingItem.id">
        <div>
          <label class="text-sm font-semibold mb-1 block">اسم العرض (اختياري)</label>
          <input x-model="editingItem.display_name" :placeholder="editingItem.product?.name"
                 class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
        </div>
        <div>
          <label class="text-sm font-semibold mb-1 block">الوصف</label>
          <textarea x-model="editingItem.display_description" rows="2"
                    placeholder="وصف جذّاب للعميل"
                    class="w-full px-4 py-2.5 rounded-xl border border-sand-200 resize-none"></textarea>
        </div>
        <div>
          <label class="text-sm font-semibold mb-1 block">الفئة</label>
          <select x-model="editingItem.category_id" class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
            <option :value="null">— بدون فئة —</option>
            <template x-for="cat in categories" :key="cat.id">
              <option :value="cat.id" x-text="cat.name"></option>
            </template>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-semibold mb-1 block">السعر للمتجر</label>
            <input x-model.number="editingItem.display_price" type="number" step="0.01"
                   :placeholder="editingItem.product?.sale_price"
                   class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
          </div>
          <div>
            <label class="text-sm font-semibold mb-1 block">السعر قبل الخصم</label>
            <input x-model.number="editingItem.compare_at_price" type="number" step="0.01"
                   class="w-full px-4 py-2.5 rounded-xl border border-sand-200">
          </div>
        </div>

        <div class="border-t border-sand-200 pt-3">
          <h4 class="font-bold text-sm mb-2">الشارات</h4>
          <div class="grid grid-cols-2 gap-2">
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_featured" class="w-4 h-4">
              <span class="text-sm">⭐ مميّز</span>
            </label>
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_bestseller" class="w-4 h-4">
              <span class="text-sm">🔥 الأكثر مبيعاً</span>
            </label>
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_new" class="w-4 h-4">
              <span class="text-sm">🆕 جديد</span>
            </label>
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_spicy" class="w-4 h-4">
              <span class="text-sm">🌶 حارّ</span>
            </label>
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_vegan" class="w-4 h-4">
              <span class="text-sm">🌱 نباتي</span>
            </label>
            <label class="flex items-center gap-2 bg-sand-100 p-2 rounded-lg">
              <input type="checkbox" x-model="editingItem.is_glutenfree" class="w-4 h-4">
              <span class="text-sm">🌾 بدون جلوتين</span>
            </label>
          </div>
        </div>

        <div class="border-t border-sand-200 pt-3">
          <div class="flex items-center justify-between gap-4">
            <label class="flex items-center gap-2">
              <input type="checkbox" x-model="editingItem.is_visible" class="w-4 h-4">
              <span class="text-sm font-semibold">عرض في المنيو</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" x-model="editingItem.is_available" class="w-4 h-4">
              <span class="text-sm font-semibold">متوفّر للطلب</span>
            </label>
          </div>
        </div>
      </div>
      <div class="flex gap-2 mt-6">
        <button @click="showItemModal = false" class="flex-1 bg-sand-100 hover:bg-sand-200 py-3 rounded-xl font-bold">إلغاء</button>
        <button @click="saveItem()" class="flex-1 bg-teal-700 hover:bg-teal-900 text-white py-3 rounded-xl font-bold">حفظ</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function menuBuilder() {
  return {
    store: @json($store),
    categories: @json($categories),
    items: @json($items),
    availableProducts: @json($availableProducts),
    selectedCategory: null,
    searchQuery: '',
    showCategoryModal: false,
    showItemModal: false,
    showImportModal: false,
    editingCategory: {},
    editingItem: {},

    init() {},

    get filteredItems() {
      let list = this.items;
      if (this.selectedCategory !== null) {
        list = list.filter(i => i.category_id === this.selectedCategory);
      }
      if (this.searchQuery) {
        const q = this.searchQuery.toLowerCase();
        list = list.filter(i =>
          (i.display_name || i.product?.name || '').toLowerCase().includes(q)
        );
      }
      return list;
    },

    editCategory(cat) {
      this.editingCategory = { ...cat };
      this.showCategoryModal = true;
    },

    async saveCategory() {
      if (!this.editingCategory.name) {
        alert('اكتب اسم الفئة');
        return;
      }
      const url = this.editingCategory.id
        ? `/api/sahab/store/menu/categories/${this.editingCategory.id}`
        : '/api/sahab/store/menu/categories';

      const res = await fetch(url, {
        method: this.editingCategory.id ? 'PUT' : 'POST',
        headers: this.headers(),
        body: JSON.stringify(this.editingCategory),
      });
      const data = await res.json();
      if (data.success) {
        if (!this.editingCategory.id) {
          this.categories.push(data.category);
        } else {
          const idx = this.categories.findIndex(c => c.id === data.category.id);
          if (idx !== -1) this.categories[idx] = data.category;
        }
        this.showCategoryModal = false;
        this.editingCategory = {};
      }
    },

    async addProductToMenu(prod) {
      const res = await fetch('/api/sahab/store/menu/items', {
        method: 'POST',
        headers: this.headers(),
        body: JSON.stringify({
          product_id: prod.id,
          category_id: this.selectedCategory,
        }),
      });
      const data = await res.json();
      if (data.success) {
        this.items.push(data.item);
        this.availableProducts = this.availableProducts.filter(p => p.id !== prod.id);
      }
    },

    editItem(item) {
      this.editingItem = { ...item };
      this.showItemModal = true;
    },

    async saveItem() {
      const res = await fetch(`/api/sahab/store/menu/items/${this.editingItem.id}`, {
        method: 'PUT',
        headers: this.headers(),
        body: JSON.stringify(this.editingItem),
      });
      const data = await res.json();
      if (data.success) {
        const idx = this.items.findIndex(i => i.id === this.editingItem.id);
        if (idx !== -1) this.items[idx] = { ...this.items[idx], ...this.editingItem };
        this.showItemModal = false;
      }
    },

    async confirmRemove(item) {
      if (!confirm('هل أنت متأكّد من إزالة هذا المنتج من المنيو؟')) return;
      const res = await fetch(`/api/sahab/store/menu/items/${item.id}`, {
        method: 'DELETE',
        headers: this.headers(),
      });
      const data = await res.json();
      if (data.success) {
        this.items = this.items.filter(i => i.id !== item.id);
        this.availableProducts.push(item.product);
      }
    },

    headers() {
      return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
      };
    },
  };
}
</script>
@endpush

@endsection
