<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\WhatsappStore;
use App\Models\Sahab\StoreTransaction;
use App\Models\Sahab\Invoice;
use App\Services\Sahab\PaymentGatewayService;
use Illuminate\Http\Request;

/**
 * StorePaymentController — إدارة الدفع الإلكتروني للطلبات
 *  للعملاء (public) — Apple Pay / Google Pay / Mada / Visa / STC Pay / Tabby / Tamara
 */
class StorePaymentController extends Controller
{
    public function __construct(protected PaymentGatewayService $payment) {}

    /**
     * بدء الدفع لطلب موجود
     */
    public function initiate(Request $request, string $slug)
    {
        $validated = $request->validate([
            'invoice_id'     => 'required|integer',
            'payment_method' => 'required|in:apple_pay,google_pay,mada,visa,mastercard,stc_pay,tabby,tamara,cash_on_delivery,bank_transfer',
            'customer_name'  => 'required|string|max:120',
            'customer_email' => 'nullable|email',
            'customer_mobile'=> 'required|string|max:20',
        ]);

        $store = WhatsappStore::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $invoice = Invoice::where('id', $validated['invoice_id'])
            ->where('tenant_id', $store->tenant_id)
            ->firstOrFail();

        // حالات خاصّة (لا تحتاج بوّابة دفع)
        if ($validated['payment_method'] === 'cash_on_delivery') {
            $invoice->update([
                'payment_method' => 'cash_on_delivery',
                'status'         => 'confirmed',
                'notes'          => ($invoice->notes ?? '') . "\n💵 الدفع عند الاستلام",
            ]);

            return response()->json([
                'success' => true,
                'method'  => 'cod',
                'message' => 'تمّ تأكيد طلبك. ستدفع عند الاستلام.',
                'redirect_url' => "/store/{$slug}/order/{$invoice->id}/success",
            ]);
        }

        if ($validated['payment_method'] === 'bank_transfer') {
            $invoice->update([
                'payment_method' => 'bank_transfer',
                'status'         => 'pending',
                'notes'          => ($invoice->notes ?? '') . "\n🏦 بانتظار التحويل البنكي",
            ]);

            return response()->json([
                'success'  => true,
                'method'   => 'bank',
                'message'  => 'سيصلك تفاصيل الحساب البنكي على الواتساب',
                'iban'     => 'SA00 0000 0000 0000 0000 0000', // من إعدادات المتجر
            ]);
        }

        // باقي الطرق — بوّابة الدفع
        try {
            $result = $this->payment->initiatePayment($store, $invoice, $validated['payment_method'], [
                'name'   => $validated['customer_name'],
                'email'  => $validated['customer_email'] ?? null,
                'mobile' => $validated['customer_mobile'],
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إكمال الدفع للـ Apple Pay / Google Pay (client-side token)
     */
    public function complete(Request $request, string $slug, string $transactionId)
    {
        $validated = $request->validate([
            'source' => 'required|array',
        ]);

        $transaction = StoreTransaction::where('transaction_id', $transactionId)->firstOrFail();
        $result = $this->payment->completeMoyasarClientPayment($transaction, $validated);

        return response()->json($result);
    }

    /**
     * Callback من بوّابة الدفع
     */
    public function callback(Request $request, string $slug, string $transactionId)
    {
        $payload = $request->all();
        $result = $this->payment->handleCallback($transactionId, $payload);

        $transaction = StoreTransaction::where('transaction_id', $transactionId)->first();
        $store = WhatsappStore::where('slug', $slug)->firstOrFail();

        if ($result['status'] === 'paid') {
            return redirect("/store/{$slug}/order/{$transaction->invoice_id}/success?txn={$transactionId}");
        }

        return redirect("/store/{$slug}/order/{$transaction->invoice_id}/failed?reason=" . urlencode($transaction->failure_reason ?? 'unknown'));
    }

    /**
     * صفحة نجاح الطلب
     */
    public function success(Request $request, string $slug, int $invoiceId)
    {
        $store = WhatsappStore::where('slug', $slug)->firstOrFail();
        $invoice = Invoice::where('id', $invoiceId)
            ->where('tenant_id', $store->tenant_id)
            ->with('items')
            ->firstOrFail();

        return view('sahab.store.payment_success', compact('store', 'invoice'));
    }

    /**
     * صفحة فشل الدفع
     */
    public function failed(Request $request, string $slug, int $invoiceId)
    {
        $store = WhatsappStore::where('slug', $slug)->firstOrFail();
        $invoice = Invoice::where('id', $invoiceId)
            ->where('tenant_id', $store->tenant_id)
            ->firstOrFail();

        $reason = $request->reason ?? 'حدث خطأ أثناء الدفع';

        return view('sahab.store.payment_failed', compact('store', 'invoice', 'reason'));
    }

    /**
     * Webhook من بوّابة الدفع (Moyasar/HyperPay)
     */
    public function webhook(Request $request)
    {
        // التحقّق من التوقيع (يعتمد على كل بوّابة)
        $payload = $request->all();
        $transactionId = $payload['metadata']['transaction_id'] ?? null;

        if (!$transactionId) {
            return response()->json(['error' => 'no transaction id'], 400);
        }

        $result = $this->payment->handleCallback($transactionId, $payload);
        return response()->json($result);
    }
}
