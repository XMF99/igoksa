@extends('sahab.layouts.app')

@section('title', 'أوامر الشغل')
@section('page_title', '📋 أوامر الشغل')
@section('page_subtitle', 'تابع طلبات الخدمة والصيانة من البداية للتسليم')

@section('content')
<div class="max-w-6xl mx-auto">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-yellow-50 rounded-2xl border border-yellow-200 p-4">
      <div class="text-xs text-yellow-700">قيد الانتظار</div>
      <div class="font-mono font-bold text-3xl text-yellow-700">{{ $summary['pending'] }}</div>
    </div>
    <div class="bg-blue-50 rounded-2xl border border-blue-200 p-4">
      <div class="text-xs text-blue-700">قيد التنفيذ</div>
      <div class="font-mono font-bold text-3xl text-blue-700">{{ $summary['in_progress'] }}</div>
    </div>
    <div class="bg-green-50 rounded-2xl border border-green-200 p-4">
      <div class="text-xs text-green-700">مكتمل اليوم</div>
      <div class="font-mono font-bold text-3xl text-green-700">{{ $summary['completed_today'] }}</div>
    </div>
    <div class="bg-red-50 rounded-2xl border border-red-200 p-4">
      <div class="text-xs text-red-700">عاجل</div>
      <div class="font-mono font-bold text-3xl text-red-700">{{ $summary['urgent'] }}</div>
    </div>
  </div>

  @if($orders->isEmpty())
    <div class="bg-white rounded-2xl border border-sand-200 text-center py-16 text-ink-700/40">
      <div class="text-6xl mb-3">📋</div>
      <p class="font-bold">لا توجد أوامر شغل</p>
    </div>
  @else
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      @foreach($orders as $order)
        <div class="bg-white rounded-2xl border border-sand-200 p-5 hover:shadow-md transition">
          <div class="flex items-start justify-between mb-3">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono text-ink-700/60">{{ $order->order_number }}</span>
                @if($order->priority === 'urgent')
                  <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-bold">عاجل</span>
                @endif
              </div>
              <h3 class="font-bold">{{ $order->title }}</h3>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-bold
              @if($order->status === 'pending') bg-yellow-100 text-yellow-700
              @elseif($order->status === 'in_progress') bg-blue-100 text-blue-700
              @elseif($order->status === 'completed') bg-green-100 text-green-700
              @else bg-gray-100 text-gray-700 @endif">
              {{ $order->status }}
            </span>
          </div>
          <div class="text-sm text-ink-700/70 space-y-1">
            <div>👤 {{ $order->customer?->name ?? '—' }}</div>
            <div>👷 {{ $order->assignedTo?->full_name ?? 'غير معيّن' }}</div>
            @if($order->scheduled_at)
              <div>📅 {{ $order->scheduled_at->format('Y-m-d H:i') }}</div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
  @endif
</div>
@endsection
