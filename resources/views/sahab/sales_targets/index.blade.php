@extends('sahab.layouts.app')

@section('title', 'المبيعات المستهدفة')
@section('page_title', '🎯 المبيعات المستهدفة والعمولات')
@section('page_subtitle', 'حدّد أهدافاً لفريقك واحسب العمولات تلقائياً')

@section('content')
<div class="max-w-6xl mx-auto">

  {{-- Summary cards --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-sand-200 p-5">
      <div class="text-sm text-ink-700/60 mb-1">أهداف نشطة</div>
      <div class="font-mono font-bold text-3xl text-teal-700">{{ $summary['active'] }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-sand-200 p-5">
      <div class="text-sm text-ink-700/60 mb-1">أهداف محقّقة</div>
      <div class="font-mono font-bold text-3xl text-green-600">{{ $summary['achieved'] }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-sand-200 p-5">
      <div class="text-sm text-ink-700/60 mb-1">العمولات المستحقّة</div>
      <div class="font-mono font-bold text-2xl text-copper-500">
        {{ number_format($summary['commission_due'], 0) }} <span class="text-sm">ر.س</span>
      </div>
    </div>
    <div class="bg-gradient-to-br from-teal-700 to-teal-900 text-white rounded-2xl p-5">
      <div class="text-sm opacity-80 mb-1">إجراءات</div>
      <button onclick="alert('قيد التطوير')"
              class="bg-copper-400 text-ink-900 px-4 py-2 rounded-xl font-bold w-full">+ هدف جديد</button>
    </div>
  </div>

  {{-- Targets list --}}
  <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden">
    <div class="p-5 border-b border-sand-200 flex items-center justify-between">
      <h3 class="font-bold text-lg">قائمة الأهداف</h3>
    </div>

    @if($targets->isEmpty())
      <div class="text-center py-16 text-ink-700/40">
        <div class="text-6xl mb-3">🎯</div>
        <p class="font-bold">لا توجد أهداف بعد</p>
        <p class="text-sm mt-1">أنشئ هدفاً لتحفيز فريقك</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-sand-100">
            <tr>
              <th class="text-right p-3 font-bold">الاسم</th>
              <th class="text-right p-3 font-bold">الموظّف</th>
              <th class="text-right p-3 font-bold">الفترة</th>
              <th class="text-right p-3 font-bold">الهدف</th>
              <th class="text-right p-3 font-bold">المنجز</th>
              <th class="text-right p-3 font-bold">التقدّم</th>
              <th class="text-right p-3 font-bold">العمولة</th>
              <th class="text-right p-3 font-bold">الحالة</th>
            </tr>
          </thead>
          <tbody>
            @foreach($targets as $t)
              <tr class="border-t border-sand-100 hover:bg-sand-100/40">
                <td class="p-3 font-semibold">{{ $t->name }}</td>
                <td class="p-3">{{ $t->employee?->full_name ?? 'الكلّ' }}</td>
                <td class="p-3">{{ $t->start_date?->format('m/d') }} → {{ $t->end_date?->format('m/d') }}</td>
                <td class="p-3 font-mono">{{ number_format($t->target_amount, 0) }}</td>
                <td class="p-3 font-mono">{{ number_format($t->achieved_amount, 0) }}</td>
                <td class="p-3">
                  <div class="bg-sand-100 rounded-full h-2 w-24 overflow-hidden">
                    <div class="bg-gradient-to-l from-teal-700 to-copper-400 h-full"
                         style="width: {{ min(100, $t->progressPercent()) }}%"></div>
                  </div>
                  <span class="text-xs">{{ round($t->progressPercent()) }}%</span>
                </td>
                <td class="p-3 font-mono text-copper-500">{{ number_format($t->commission_amount, 0) }}</td>
                <td class="p-3">
                  @switch($t->status)
                    @case('active')    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">نشط</span> @break
                    @case('achieved')  <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">✓ محقّق</span> @break
                    @case('failed')    <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs">لم يتحقّق</span> @break
                    @default <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">{{ $t->status }}</span>
                  @endswitch
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="p-3">{{ $targets->links() }}</div>
    @endif
  </div>
</div>
@endsection
