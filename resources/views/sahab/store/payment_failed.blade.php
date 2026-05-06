<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>فشل الدفع — {{ $store->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>body { font-family: 'Tajawal', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center p-4">

<div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8 text-center">
  <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-red-500 grid place-items-center text-white text-5xl">
    ✗
  </div>

  <h1 class="font-bold text-3xl mb-3">لم يتمّ الدفع</h1>
  <p class="text-gray-600 mb-2">عذراً، حدث خطأ أثناء معالجة الدفع.</p>
  <p class="text-sm text-red-600 mb-6">{{ $reason }}</p>

  <div class="bg-gray-50 rounded-2xl p-4 mb-6 text-right">
    <div class="flex justify-between mb-2">
      <span class="text-gray-600 text-sm">رقم الطلب</span>
      <span class="font-mono font-bold">{{ $invoice->invoice_number }}</span>
    </div>
    <div class="flex justify-between">
      <span class="text-gray-600 text-sm">المبلغ المطلوب</span>
      <span class="font-mono font-bold">{{ number_format($invoice->total_amount, 2) }} ر.س</span>
    </div>
  </div>

  <div class="space-y-2">
    <a href="/store/{{ $store->slug }}/checkout?retry={{ $invoice->id }}"
       class="block bg-teal-700 hover:bg-teal-900 text-white py-4 rounded-xl font-bold transition">
      🔄 حاول الدفع مرّة أخرى
    </a>
    @if($store->whatsapp_number)
      <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_number) }}?text={{ urlencode('احتاج مساعدة في الدفع للطلب ' . $invoice->invoice_number) }}"
         class="block bg-green-500 hover:bg-green-600 text-white py-4 rounded-xl font-bold transition">
        💬 تواصل للمساعدة
      </a>
    @endif
    <a href="/store/{{ $store->slug }}" class="block bg-gray-100 hover:bg-gray-200 py-4 rounded-xl font-bold">
      ← العودة للمتجر
    </a>
  </div>
</div>

</body>
</html>
