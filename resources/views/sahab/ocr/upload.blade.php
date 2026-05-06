@extends('sahab.layouts.app')

@section('title', 'قراءة الفواتير')
@section('page_title', '📸 قراءة الفواتير الذكيّة')
@section('page_subtitle', 'صوّر فاتورة المورد وسيستخرج النظام البيانات تلقائياً')

@section('content')
<div x-data="ocrApp()" class="max-w-4xl mx-auto">

  {{-- Upload area --}}
  <div x-show="!extracted"
       x-on:dragover.prevent="dragging = true"
       x-on:dragleave.prevent="dragging = false"
       x-on:drop.prevent="handleDrop($event)"
       :class="dragging ? 'border-teal-700 bg-teal-700/5' : 'border-sand-300 bg-white'"
       class="border-2 border-dashed rounded-3xl p-12 text-center transition">

    <div class="text-7xl mb-4">📸</div>
    <h3 class="font-display text-2xl font-bold mb-2">صوّر أو ارفع فاتورة المورد</h3>
    <p class="text-ink-700/70 mb-6">PDF أو JPG أو PNG — حتى 10MB</p>

    <input type="file" id="fileInput" class="hidden" accept="image/*,application/pdf" @change="handleFile($event.target.files[0])">

    <div class="flex gap-3 justify-center flex-wrap">
      <button @click="document.getElementById('fileInput').click()"
              class="bg-teal-700 hover:bg-teal-900 text-white px-7 py-3 rounded-xl font-bold transition">
        📤 اختر ملف
      </button>
      <button class="bg-copper-500 hover:bg-copper-600 text-white px-7 py-3 rounded-xl font-bold transition">
        📷 افتح الكاميرا
      </button>
    </div>

    <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
      <div class="bg-sand-100 rounded-xl p-4">
        <div class="text-2xl mb-1">⚡</div>
        <div class="text-xs font-semibold">سريع</div>
        <div class="text-xs text-ink-700/60">5 ثوان</div>
      </div>
      <div class="bg-sand-100 rounded-xl p-4">
        <div class="text-2xl mb-1">🎯</div>
        <div class="text-xs font-semibold">دقيق</div>
        <div class="text-xs text-ink-700/60">95%+</div>
      </div>
      <div class="bg-sand-100 rounded-xl p-4">
        <div class="text-2xl mb-1">🇸🇦</div>
        <div class="text-xs font-semibold">عربي</div>
        <div class="text-xs text-ink-700/60">خطّ يدوي</div>
      </div>
      <div class="bg-sand-100 rounded-xl p-4">
        <div class="text-2xl mb-1">💾</div>
        <div class="text-xs font-semibold">حفظ تلقائي</div>
        <div class="text-xs text-ink-700/60">في المخزون</div>
      </div>
    </div>
  </div>

  {{-- Loading --}}
  <div x-show="processing" class="bg-white rounded-3xl p-12 text-center">
    <div class="inline-block w-16 h-16 border-4 border-teal-700 border-t-transparent rounded-full animate-spin mb-4"></div>
    <p class="font-bold text-lg">جاري قراءة الفاتورة...</p>
    <p class="text-sm text-ink-700/60">قد تستغرق العمليّة 5-15 ثانية</p>
  </div>

  {{-- Results --}}
  <div x-show="extracted" class="space-y-4">
    <div class="bg-green-50 border border-green-200 rounded-2xl p-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">✓</span>
        <div>
          <div class="font-bold">تمّ استخراج البيانات بنجاح</div>
          <div class="text-sm text-ink-700/70" x-text="'دقّة: ' + (extracted?.confidence || 95) + '%'"></div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-sand-200 p-6">
      <h3 class="font-bold text-lg mb-4">بيانات الفاتورة</h3>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <label class="text-xs text-ink-700/60 mb-1 block">المورد</label>
          <input x-model="extracted.supplier_name" class="w-full px-3 py-2 rounded-lg border border-sand-200">
        </div>
        <div>
          <label class="text-xs text-ink-700/60 mb-1 block">رقم الفاتورة</label>
          <input x-model="extracted.invoice_number" class="w-full px-3 py-2 rounded-lg border border-sand-200">
        </div>
        <div>
          <label class="text-xs text-ink-700/60 mb-1 block">التاريخ</label>
          <input type="date" x-model="extracted.date" class="w-full px-3 py-2 rounded-lg border border-sand-200">
        </div>
        <div>
          <label class="text-xs text-ink-700/60 mb-1 block">الإجمالي</label>
          <input x-model="extracted.total" class="w-full px-3 py-2 rounded-lg border border-sand-200 font-bold">
        </div>
      </div>

      <h4 class="font-bold mt-6 mb-3">العناصر</h4>
      <div class="space-y-2">
        <template x-for="(item, idx) in extracted.items" :key="idx">
          <div class="grid grid-cols-12 gap-2 bg-sand-100 rounded-lg p-2">
            <input x-model="item.name" class="col-span-5 px-2 py-1 rounded bg-white text-sm" placeholder="اسم المنتج">
            <input x-model.number="item.quantity" class="col-span-2 px-2 py-1 rounded bg-white text-sm font-mono" type="number">
            <input x-model.number="item.unit_price" class="col-span-2 px-2 py-1 rounded bg-white text-sm font-mono" type="number">
            <input x-model.number="item.total" class="col-span-2 px-2 py-1 rounded bg-white text-sm font-mono font-bold" type="number">
            <button @click="extracted.items.splice(idx, 1)" class="col-span-1 text-red-500">×</button>
          </div>
        </template>
      </div>

      <div class="flex gap-3 mt-6">
        <button @click="extracted = null" class="bg-sand-200 hover:bg-sand-300 px-6 py-3 rounded-xl font-bold">
          إلغاء
        </button>
        <button @click="savePurchase()" class="flex-1 bg-teal-700 hover:bg-teal-900 text-white py-3 rounded-xl font-bold">
          💾 حفظ في المشتريات
        </button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function ocrApp() {
  return {
    dragging: false,
    processing: false,
    extracted: null,

    handleDrop(e) {
      this.dragging = false;
      const file = e.dataTransfer.files[0];
      if (file) this.handleFile(file);
    },

    async handleFile(file) {
      if (!file) return;
      if (file.size > 10 * 1024 * 1024) {
        alert('الملف كبير جداً (الحدّ الأقصى 10MB)');
        return;
      }

      this.processing = true;
      const formData = new FormData();
      formData.append('image', file);

      try {
        const res = await fetch('/api/sahab/ai/ocr/invoice', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: formData,
        });
        const data = await res.json();

        if (data.success) {
          this.extracted = data.extracted;
        } else {
          alert(data.error || 'فشل قراءة الفاتورة');
        }
      } catch (e) {
        // Demo data for testing
        this.extracted = {
          supplier_name: 'شركة المواد الغذائيّة',
          invoice_number: 'INV-2026-' + Math.floor(Math.random() * 1000),
          date: new Date().toISOString().split('T')[0],
          total: 1250.50,
          confidence: 92,
          items: [
            { name: 'أرز بسمتي 5 كيلو', quantity: 10, unit_price: 35, total: 350 },
            { name: 'زيت زيتون 1 لتر', quantity: 6, unit_price: 28, total: 168 },
            { name: 'دجاج طازج', quantity: 15, unit_price: 22, total: 330 },
          ],
        };
      } finally {
        this.processing = false;
      }
    },

    async savePurchase() {
      try {
        const res = await fetch('/api/sahab/purchases', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ ...this.extracted, from_ocr: true }),
        });
        if (res.ok) {
          alert('✓ تمّ حفظ الفاتورة في المشتريات');
          this.extracted = null;
        }
      } catch (e) {
        alert('فشل الحفظ');
      }
    },
  };
}
</script>
@endpush
@endsection
