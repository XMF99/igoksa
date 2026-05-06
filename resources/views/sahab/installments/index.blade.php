@extends('sahab.layouts.app')

@section('title', 'الأقساط')
@section('page_title', '💳 إدارة الأقساط')
@section('page_subtitle', 'تابع خطط الأقساط للعملاء وتنبيهات الاستحقاق')

@section('content')
<div class="max-w-6xl mx-auto">
  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-sand-200 p-4">
      <div class="text-xs text-ink-700/60">نشطة</div>
      <div class="font-mono font-bold text-2xl text-teal-700">{{ $summary['active'] }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-sand-200 p-4">
      <div class="text-xs text-ink-700/60">مكتملة</div>
      <div class="font-mono font-bold text-2xl text-green-600">{{ $summary['completed'] }}</div>
    </div>
    <div class="bg-red-50 rounded-2xl border border-red-200 p-4">
      <div class="text-xs text-red-700">متأخّرة</div>
      <div class="font-mono font-bold text-2xl text-red-600">{{ $summary['overdue'] }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-sand-200 p-4">
      <div class="text-xs text-ink-700/60">متعثّرة</div>
      <div class="font-mono font-bold text-2xl text-orange-600">{{ $summary['defaulted'] }}</div>
    </div>
    <div class="bg-gradient-to-br from-teal-700 to-teal-900 text-white rounded-2xl p-4">
      <div class="text-xs opacity-80">إجمالي ممولّ</div>
      <div class="font-mono font-bold text-2xl">{{ number_format($summary['total_financed'], 0) }} <span class="text-sm opacity-80">ر.س</span></div>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden">
    @if($installments->isEmpty())
      <div class="text-center py-16 text-ink-700/40">
        <div class="text-6xl mb-3">💳</div>
        <p class="font-bold">لا توجد خطط أقساط</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-sand-100">
            <tr>
              <th class="text-right p-3">رقم الخطّة</th>
              <th class="text-right p-3">العميل</th>
              <th class="text-right p-3">إجمالي</th>
              <th class="text-right p-3">قسط</th>
              <th class="text-right p-3">المدفوع/الكلّ</th>
              <th class="text-right p-3">التالي</th>
              <th class="text-right p-3">الحالة</th>
            </tr>
          </thead>
          <tbody>
            @foreach($installments as $inst)
              <tr class="border-t border-sand-100">
                <td class="p-3 font-mono">{{ $inst->plan_number }}</td>
                <td class="p-3">{{ $inst->customer?->name }}</td>
                <td class="p-3 font-mono">{{ number_format($inst->total_amount, 2) }}</td>
                <td class="p-3 font-mono">{{ number_format($inst->installment_amount, 2) }}</td>
                <td class="p-3">{{ $inst->paid_installments }}/{{ $inst->total_installments }}</td>
                <td class="p-3 text-xs">{{ $inst->next_due_date?->format('Y-m-d') ?? '—' }}</td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded text-xs
                    @if($inst->status === 'active') bg-blue-100 text-blue-700
                    @elseif($inst->status === 'completed') bg-green-100 text-green-700
                    @elseif($inst->status === 'defaulted') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-700 @endif">
                    {{ $inst->status }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="p-3">{{ $installments->links() }}</div>
    @endif
  </div>
</div>
@endsection
