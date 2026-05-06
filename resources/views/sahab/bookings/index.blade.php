@extends('sahab.layouts.app')

@section('title', 'الحجوزات')
@section('page_title', '📅 إدارة الحجوزات')
@section('page_subtitle', 'حجوزات المواعيد، الطاولات، الغرف، والمعدّات')

@section('content')
<div class="max-w-6xl mx-auto">
  @if($bookings->isEmpty())
    <div class="bg-white rounded-2xl border border-sand-200 text-center py-16">
      <div class="text-6xl mb-3">📅</div>
      <p class="font-bold text-ink-700">لا توجد حجوزات</p>
      <p class="text-sm text-ink-700/60 mt-2">ابدأ باستقبال الحجوزات من العملاء</p>
    </div>
  @else
    <div class="bg-white rounded-2xl border border-sand-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-sand-100">
            <tr>
              <th class="text-right p-3">رقم الحجز</th>
              <th class="text-right p-3">العميل</th>
              <th class="text-right p-3">المورد</th>
              <th class="text-right p-3">الموعد</th>
              <th class="text-right p-3">المدّة</th>
              <th class="text-right p-3">المبلغ</th>
              <th class="text-right p-3">الحالة</th>
            </tr>
          </thead>
          <tbody>
            @foreach($bookings as $b)
              <tr class="border-t border-sand-100">
                <td class="p-3 font-mono">{{ $b->booking_number }}</td>
                <td class="p-3">{{ $b->customer?->name ?? '—' }}</td>
                <td class="p-3">{{ $b->resource?->name }}</td>
                <td class="p-3 text-xs">{{ $b->start_at?->format('Y-m-d H:i') }}</td>
                <td class="p-3">{{ $b->duration_minutes }}د</td>
                <td class="p-3 font-mono">{{ number_format($b->total_amount, 0) }}</td>
                <td class="p-3">
                  <span class="px-2 py-1 rounded text-xs
                    @if($b->status === 'confirmed') bg-green-100 text-green-700
                    @elseif($b->status === 'pending') bg-yellow-100 text-yellow-700
                    @elseif($b->status === 'cancelled') bg-red-100 text-red-700
                    @else bg-gray-100 text-gray-700 @endif">
                    {{ $b->status }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="p-3">{{ $bookings->links() }}</div>
    </div>
  @endif
</div>
@endsection
