<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>تمّ الطلب بنجاح — {{ $store->name }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400;500;600;700&family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Tajawal', sans-serif; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 flex items-center justify-center p-4">

<div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8 text-center">
  <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-green-500 grid place-items-center text-white text-5xl animate-bounce">
    ✓
  </div>

  <h1 class="font-bold text-3xl mb-3">تمّ الطلب بنجاح!</h1>
  <p class="text-gray-600 mb-6">شكراً لك. سيتمّ تجهيز طلبك قريباً.</p>

  <div class="bg-gray-50 rounded-2xl p-4 mb-6 text-right">
    <div class="flex justify-between mb-2">
      <span class="text-gray-600 text-sm">رقم الطلب</span>
      <span class="font-mono font-bold">{{ $invoice->invoice_number }}</span>
    </div>
    <div class="flex justify-between mb-2">
      <span class="text-gray-600 text-sm">المبلغ</span>
      <span class="font-mono font-bold text-teal-700">{{ number_format($invoice->total_amount, 2) }} ر.س</span>
    </div>
    @if($invoice->payment_status === 'paid')
      <div class="flex justify-between">
        <span class="text-gray-600 text-sm">حالة الدفع</span>
        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">✓ مدفوع</span>
      </div>
    @endif
  </div>

  <div class="space-y-2">
    @if($store->whatsapp_number)
      <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_number) }}?text={{ urlencode('استفسار عن الطلب رقم ' . $invoice->invoice_number) }}"
         class="block bg-green-500 hover:bg-green-600 text-white py-4 rounded-xl font-bold transition">
        💬 تواصل معنا على واتساب
      </a>
    @endif
    <a href="/store/{{ $store->slug }}" class="block bg-gray-100 hover:bg-gray-200 py-4 rounded-xl font-bold transition">
      ← متابعة التسوّق
    </a>
  </div>
</div>

</body>
</html>
