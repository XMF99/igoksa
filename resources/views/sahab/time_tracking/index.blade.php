@extends('sahab.layouts.app')

@section('title', 'تتبّع الوقت')
@section('page_title', '⏱ تتبّع الوقت')
@section('page_subtitle', 'سجّل ساعات العمل واحسب الفواتير حسب الوقت')

@section('content')
<div class="max-w-6xl mx-auto">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-2xl border border-sand-200 p-5">
      <div class="text-sm text-ink-700/60">إجمالي ساعات اليوم</div>
      <div class="font-mono font-bold text-3xl text-teal-700">{{ number_format($totalHours, 2) }}</div>
    </div>
    <div class="bg-white rounded-2xl border border-sand-200 p-5">
      <div class="text-sm text-ink-700/60">ساعات قابلة للفوترة</div>
      <div class="font-mono font-bold text-3xl text-copper-500">{{ number_format($billableHours, 2) }}</div>
    </div>
    <div class="bg-gradient-to-br from-teal-700 to-teal-900 text-white rounded-2xl p-5">
      <div class="text-sm opacity-80">عمليّات سريعة</div>
      <button class="bg-copper-400 text-ink-900 px-4 py-2 rounded-xl font-bold w-full mt-2">▶ بدء عدّاد جديد</button>
    </div>
  </div>

  @if($entries->isEmpty())
    <div class="bg-white rounded-2xl border border-sand-200 text-center py-16">
      <div class="text-6xl mb-3">⏱</div>
      <p class="font-bold text-ink-700">لا توجد سجلّات وقت</p>
    </div>
  @else
    <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-sand-100">
          <tr>
            <th class="text-right p-3">التاريخ</th>
            <th class="text-right p-3">الموظّف</th>
            <th class="text-right p-3">المهمّة</th>
            <th class="text-right p-3">من</th>
            <th class="text-right p-3">إلى</th>
            <th class="text-right p-3">المدّة</th>
            <th class="text-right p-3">المبلغ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($entries as $e)
            <tr class="border-t border-sand-100">
              <td class="p-3 text-xs">{{ $e->date?->format('Y-m-d') }}</td>
              <td class="p-3">{{ $e->employee?->full_name }}</td>
              <td class="p-3 text-xs">{{ Str::limit($e->task_description, 40) }}</td>
              <td class="p-3 font-mono">{{ $e->start_time }}</td>
              <td class="p-3 font-mono">{{ $e->end_time ?? '—' }}</td>
              <td class="p-3 font-mono">{{ number_format($e->duration_hours, 2) }}س</td>
              <td class="p-3 font-mono">{{ number_format($e->total_amount, 0) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="p-3">{{ $entries->links() }}</div>
    </div>
  @endif
</div>
@endsection
