@extends('sahab.layouts.app')

@section('title', 'الوثائق')
@section('page_title', 'الوثائق الرسميّة')
@section('page_subtitle', 'تتبّع وثائق المحلّ والموظّفين مع تنبيهات الانتهاء')

@section('actions')
  <button onclick="openModal('addBusinessDoc')" class="bg-teal-700 hover:bg-teal-900 text-white px-4 py-2.5 rounded-xl font-bold text-sm">
    + وثيقة محلّ
  </button>
  <button onclick="openModal('addEmployeeDoc')" class="bg-copper-500 hover:bg-copper-600 text-white px-4 py-2.5 rounded-xl font-bold text-sm">
    + وثيقة موظّف
  </button>
@endsection

@section('content')
<div x-data="docsApp()" x-init="init()">

  {{-- Tabs --}}
  <div class="flex gap-2 mb-4 border-b border-sand-200">
    <button @click="tab = 'all'"
            :class="tab === 'all' ? 'border-teal-700 text-teal-700' : 'border-transparent text-ink-700/60'"
            class="px-4 py-3 font-semibold border-b-2 transition">
      الكل (<span x-text="counts.all"></span>)
    </button>
    <button @click="tab = 'expired'"
            :class="tab === 'expired' ? 'border-red-500 text-red-500' : 'border-transparent text-ink-700/60'"
            class="px-4 py-3 font-semibold border-b-2 transition">
      ⛔ منتهية (<span x-text="counts.expired"></span>)
    </button>
    <button @click="tab = 'urgent'"
            :class="tab === 'urgent' ? 'border-orange-500 text-orange-500' : 'border-transparent text-ink-700/60'"
            class="px-4 py-3 font-semibold border-b-2 transition">
      🚨 خلال 7 أيام (<span x-text="counts.urgent"></span>)
    </button>
    <button @click="tab = 'soon'"
            :class="tab === 'soon' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-ink-700/60'"
            class="px-4 py-3 font-semibold border-b-2 transition">
      ⚠ خلال 30 يوم (<span x-text="counts.soon"></span>)
    </button>
    <button @click="tab = 'safe'"
            :class="tab === 'safe' ? 'border-green-500 text-green-600' : 'border-transparent text-ink-700/60'"
            class="px-4 py-3 font-semibold border-b-2 transition">
      ✓ آمنة (<span x-text="counts.safe"></span>)
    </button>
  </div>

  {{-- Filters --}}
  <div class="flex gap-3 mb-4">
    <select x-model="typeFilter" class="px-4 py-2 rounded-xl border border-sand-200 bg-white">
      <option value="">كل الأنواع</option>
      <optgroup label="وثائق المحلّ">
        <option value="commercial_registration">السجل التجاري</option>
        <option value="municipal_license">الرخصة البلديّة</option>
        <option value="rent_contract">عقد الإيجار</option>
        <option value="civil_defense">الدفاع المدني</option>
        <option value="zakat_cert">شهادة الزكاة</option>
        <option value="vat_cert">شهادة ض.م</option>
      </optgroup>
      <optgroup label="وثائق الموظّفين">
        <option value="iqama">الإقامة</option>
        <option value="work_permit">رخصة العمل</option>
        <option value="passport">جواز السفر</option>
        <option value="health_insurance">التأمين الصحّي</option>
      </optgroup>
    </select>
    <input type="text" x-model="search" placeholder="🔍 ابحث..." class="flex-1 px-4 py-2 rounded-xl border border-sand-200">
  </div>

  {{-- Documents grid --}}
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <template x-for="doc in filteredDocs" :key="doc.id + '_' + doc.kind">
      <div class="bg-white rounded-2xl border-r-4 p-5"
           :class="{
             'border-red-500 bg-red-50/30': doc.daysLeft < 0,
             'border-orange-500 bg-orange-50/30': doc.daysLeft >= 0 && doc.daysLeft <= 7,
             'border-yellow-500 bg-yellow-50/30': doc.daysLeft > 7 && doc.daysLeft <= 30,
             'border-green-500': doc.daysLeft > 30,
           }">

        <div class="flex items-start justify-between mb-3">
          <div class="flex-1 min-w-0">
            <div class="text-xs text-ink-700/60 mb-1" x-text="doc.kind === 'business' ? '🏢 وثيقة محلّ' : '👤 ' + doc.employee_name"></div>
            <div class="font-bold text-lg" x-text="doc.type_label"></div>
            <div class="text-sm text-ink-700/70 font-mono" x-text="doc.number || '—'"></div>
          </div>
          <button @click="downloadDoc(doc)" class="text-ink-700/40 hover:text-teal-700">
            ⬇
          </button>
        </div>

        <div class="text-sm space-y-1 mb-3">
          <div class="flex justify-between">
            <span class="text-ink-700/60">تاريخ الإصدار:</span>
            <span class="font-mono" x-text="doc.issue_date || '—'"></span>
          </div>
          <div class="flex justify-between">
            <span class="text-ink-700/60">تاريخ الانتهاء:</span>
            <span class="font-mono font-bold" x-text="doc.expiry_date"></span>
          </div>
        </div>

        <div class="text-center py-2 rounded-lg font-bold"
             :class="{
               'bg-red-500 text-white': doc.daysLeft < 0,
               'bg-orange-500 text-white': doc.daysLeft >= 0 && doc.daysLeft <= 7,
               'bg-yellow-500 text-white': doc.daysLeft > 7 && doc.daysLeft <= 30,
               'bg-green-500 text-white': doc.daysLeft > 30,
             }">
          <template x-if="doc.daysLeft < 0">
            <span>⛔ منتهية منذ <span x-text="Math.abs(doc.daysLeft)"></span> يوم</span>
          </template>
          <template x-if="doc.daysLeft >= 0 && doc.daysLeft <= 7">
            <span>🚨 متبقّي <span x-text="doc.daysLeft"></span> يوم — جدّد فوراً!</span>
          </template>
          <template x-if="doc.daysLeft > 7 && doc.daysLeft <= 30">
            <span>⚠ متبقّي <span x-text="doc.daysLeft"></span> يوم</span>
          </template>
          <template x-if="doc.daysLeft > 30">
            <span>✓ متبقّي <span x-text="doc.daysLeft"></span> يوم</span>
          </template>
        </div>

        <div class="flex gap-2 mt-3">
          <button class="flex-1 bg-sand-100 hover:bg-sand-200 py-2 rounded-lg text-sm font-semibold">
            تجديد
          </button>
          <button class="flex-1 bg-sand-100 hover:bg-sand-200 py-2 rounded-lg text-sm font-semibold">
            تعديل
          </button>
        </div>
      </div>
    </template>
  </div>

  <template x-if="filteredDocs.length === 0">
    <div class="text-center py-20 text-ink-700/40">
      <div class="text-7xl mb-4">📄</div>
      <p class="text-xl font-bold mb-2">لا توجد وثائق</p>
      <p class="text-sm">ابدأ بإضافة وثائق المحلّ والموظّفين لتتبّع تواريخ الانتهاء</p>
    </div>
  </template>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
function docsApp() {
  return {
    docs: [],
    tab: 'all',
    typeFilter: '',
    search: '',
    counts: { all: 0, expired: 0, urgent: 0, soon: 0, safe: 0 },

    async init() {
      try {
        const res = await fetch('/api/sahab/hr/documents/expiring?days=365', {
          headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();

        // دمج وثائق الموظّفين والمحلّ
        const empDocs = (data.employee_documents || []).map(d => ({
          ...d,
          kind: 'employee',
          employee_name: d.employee?.full_name,
          type_label: this.getTypeLabel(d.document_type, 'employee'),
          number: d.document_number,
          issue_date: d.issue_date,
          expiry_date: d.expiry_date,
          daysLeft: this.daysUntil(d.expiry_date),
        }));

        const bizDocs = (data.business_documents || []).map(d => ({
          ...d,
          kind: 'business',
          type_label: this.getTypeLabel(d.document_type, 'business'),
          number: d.document_number,
          issue_date: d.issue_date,
          expiry_date: d.expiry_date,
          daysLeft: this.daysUntil(d.expiry_date),
        }));

        this.docs = [...empDocs, ...bizDocs].sort((a, b) => a.daysLeft - b.daysLeft);
        this.calculateCounts();
      } catch (e) {
        console.error(e);
      }
    },

    get filteredDocs() {
      let docs = this.docs;
      if (this.typeFilter) docs = docs.filter(d => d.document_type === this.typeFilter);
      if (this.search) docs = docs.filter(d =>
        (d.type_label || '').includes(this.search) ||
        (d.employee_name || '').includes(this.search) ||
        (d.number || '').includes(this.search)
      );

      switch (this.tab) {
        case 'expired': return docs.filter(d => d.daysLeft < 0);
        case 'urgent':  return docs.filter(d => d.daysLeft >= 0 && d.daysLeft <= 7);
        case 'soon':    return docs.filter(d => d.daysLeft > 7 && d.daysLeft <= 30);
        case 'safe':    return docs.filter(d => d.daysLeft > 30);
        default:        return docs;
      }
    },

    calculateCounts() {
      this.counts.all = this.docs.length;
      this.counts.expired = this.docs.filter(d => d.daysLeft < 0).length;
      this.counts.urgent = this.docs.filter(d => d.daysLeft >= 0 && d.daysLeft <= 7).length;
      this.counts.soon = this.docs.filter(d => d.daysLeft > 7 && d.daysLeft <= 30).length;
      this.counts.safe = this.docs.filter(d => d.daysLeft > 30).length;
    },

    daysUntil(dateStr) {
      if (!dateStr) return 999;
      const d = new Date(dateStr);
      const now = new Date();
      return Math.floor((d - now) / (1000 * 60 * 60 * 24));
    },

    getTypeLabel(type, kind) {
      const labels = {
        employee: {
          iqama: 'الإقامة',
          work_permit: 'رخصة العمل',
          passport: 'جواز السفر',
          health_insurance: 'التأمين الصحّي',
          driver_license: 'رخصة القيادة',
          gosi: 'التأمينات الاجتماعيّة',
          medical_check: 'الفحص الطبي',
        },
        business: {
          commercial_registration: 'السجل التجاري',
          municipal_license: 'الرخصة البلديّة',
          rent_contract: 'عقد الإيجار',
          civil_defense: 'شهادة الدفاع المدني',
          zakat_cert: 'شهادة الزكاة',
          vat_cert: 'شهادة ض.م',
          health_cert: 'الشهادة الصحّيّة',
        }
      };
      return labels[kind][type] || type;
    },

    downloadDoc(doc) {
      if (doc.file_path) window.open('/storage/' + doc.file_path);
    },
  };
}

function openModal(type) {
  alert('سيُفتح نموذج إضافة ' + type);
}
</script>
@endpush

@endsection
