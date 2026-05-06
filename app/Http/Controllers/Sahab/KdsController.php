<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Invoice;
use Illuminate\Http\Request;

/**
 * KdsController — شاشة المطبخ (Kitchen Display System)
 *  يعرض الطلبات الحالية ويسمح بتغيير حالتها
 */
class KdsController extends Controller
{
    /**
     * الطلبات النشطة (الجديدة + قيد التحضير)
     */
    public function orders(Request $request)
    {
        $orders = Invoice::with('items', 'customer')
            ->whereIn('status', ['pending', 'preparing'])
            ->orderBy('invoice_date')
            ->get()
            ->map(function ($invoice) {
                $waitMinutes = (int) now()->diffInMinutes($invoice->invoice_date, true);
                $urgency = $waitMinutes > 20 ? 'urgent' : ($waitMinutes > 10 ? 'warning' : 'normal');

                return [
                    'id'            => $invoice->id,
                    'invoice_number'=> $invoice->invoice_number,
                    'order_type'    => $invoice->order_type,
                    'table_number'  => $invoice->table_number,
                    'source'        => $invoice->source,
                    'status'        => $invoice->status,
                    'wait_minutes'  => $waitMinutes,
                    'urgency'       => $urgency,
                    'created_at'    => $invoice->invoice_date,
                    'items'         => $invoice->items->map(fn($i) => [
                        'name'         => $i->product_name,
                        'quantity'     => $i->quantity,
                        'modifiers'    => $i->modifiers,
                        'instructions' => $i->special_instructions,
                    ]),
                ];
            });

        return response()->json($orders);
    }

    /**
     * تحديث حالة طلب
     */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'status' => 'required|in:preparing,ready,completed',
        ]);

        $invoice->update(['status' => $validated['status']]);

        // إذا الطلب جاهز ومن منصّة توصيل — أبلغ المنصّة
        if ($validated['status'] === 'ready' && $invoice->source !== 'pos') {
            $externalOrder = \App\Models\Sahab\ExternalOrder::where('invoice_id', $invoice->id)->first();
            if ($externalOrder) {
                app(\App\Services\Sahab\DeliveryAggregatorService::class)
                    ->updateStatus($externalOrder, 'ready');
            }
        }

        return response()->json(['success' => true, 'invoice' => $invoice]);
    }
}
