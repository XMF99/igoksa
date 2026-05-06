<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Invoice;
use App\Models\Sahab\InvoiceItem;
use App\Models\Sahab\Payment;
use App\Models\Sahab\Product;
use App\Models\Sahab\Customer;
use App\Models\Sahab\Shift;
use App\Models\Sahab\LoyaltyTransaction;
use App\Services\Sahab\ZatcaService;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PosController — قلب نقطة البيع
 * --------------------------------------------------------------------
 *  يُدير:
 *    - فتح وإغلاق الورديّات
 *    - إنشاء الفواتير (مع زاتكا QR)
 *    - استرجاع/إلغاء الفواتير
 *    - تطبيق الخصومات والولاء
 *    - إرسال الإيصال على واتساب
 * --------------------------------------------------------------------
 */
class PosController extends Controller
{
    public function __construct(
        protected ZatcaService $zatca,
        protected WhatsAppService $whatsapp,
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * الصفحة الرئيسيّة للكاشير (Web/Livewire)
     */
    public function index(Request $request)
    {
        $shift = $this->getOrCreateShift($request);
        $products = Product::active()->orderBy('sort_order')->limit(200)->get();
        $categories = \App\Models\Sahab\Category::where('is_active', 1)->orderBy('sort_order')->get();

        return view('sahab.pos.index', compact('shift', 'products', 'categories'));
    }

    /**
     * إنشاء فاتورة جديدة (POS API)
     */
    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'items'                       => 'required|array|min:1',
            'items.*.product_id'          => 'required|exists:sahab_products,id',
            'items.*.quantity'            => 'required|numeric|min:0.001',
            'items.*.unit_price'          => 'required|numeric|min:0',
            'items.*.modifiers'           => 'nullable|array',
            'items.*.special_instructions'=> 'nullable|string',
            'customer_id'                 => 'nullable|exists:sahab_customers,id',
            'order_type'                  => 'required|in:dine_in,takeaway,delivery',
            'table_number'                => 'nullable|string',
            'discount_amount'             => 'nullable|numeric|min:0',
            'service_charge'              => 'nullable|numeric|min:0',
            'delivery_fee'                => 'nullable|numeric|min:0',
            'payments'                    => 'required|array|min:1',
            'payments.*.method'           => 'required|in:cash,mada,visa,mastercard,apple_pay,stc_pay,urpay,tabby,tamara,bank_transfer,wallet_balance,mixed',
            'payments.*.amount'           => 'required|numeric|min:0',
            'payments.*.reference'        => 'nullable|string',
            'redeem_loyalty_points'       => 'nullable|integer|min:0',
            'notes'                       => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $shift = $this->getOrCreateShift($request);
            $tenant = auth()->user()->tenant;

            // 1) حساب المبالغ
            $subtotal = 0;
            $vatTotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty   = $item['quantity'];
                $price = $item['unit_price'];
                $itemTotal = $qty * $price;

                // المُعدِّلات
                $modifierTotal = 0;
                if (!empty($item['modifiers'])) {
                    foreach ($item['modifiers'] as $mod) {
                        $modifierTotal += ($mod['extra_price'] ?? 0) * $qty;
                    }
                }
                $itemTotal += $modifierTotal;

                // الضريبة (مدمجة أو إضافيّة)
                $itemVat = $product->vat_included
                    ? $itemTotal - ($itemTotal / (1 + ($product->vat_rate / 100)))
                    : $itemTotal * ($product->vat_rate / 100);

                $subtotal += $itemTotal - $itemVat;
                $vatTotal += $itemVat;

                $itemsData[] = [
                    'product_id'           => $product->id,
                    'product_name'         => $product->name,
                    'product_sku'          => $product->sku,
                    'quantity'             => $qty,
                    'unit_price'           => $price,
                    'discount_amount'      => 0,
                    'vat_amount'           => round($itemVat, 2),
                    'subtotal'             => round($itemTotal - $itemVat, 2),
                    'total'                => round($itemTotal, 2),
                    'modifiers'            => $item['modifiers'] ?? null,
                    'special_instructions' => $item['special_instructions'] ?? null,
                ];

                // خصم المخزون
                if ($product->track_inventory) {
                    $product->decrement('current_stock', $qty);
                }
            }

            // الخصم العام
            $discount = $validated['discount_amount'] ?? 0;
            $service  = $validated['service_charge'] ?? 0;
            $delivery = $validated['delivery_fee'] ?? 0;

            // خصم نقاط الولاء
            $loyaltyRedeemed = 0;
            if (!empty($validated['redeem_loyalty_points']) && $validated['customer_id']) {
                $customer = Customer::find($validated['customer_id']);
                $loyaltySettings = $tenant->loyaltySettings;
                if ($customer && $loyaltySettings && $customer->loyalty_points >= $validated['redeem_loyalty_points']) {
                    $loyaltyRedeemed = $validated['redeem_loyalty_points'] * $loyaltySettings->riyal_per_point;
                    $discount += $loyaltyRedeemed;
                }
            }

            $totalAmount = $subtotal + $vatTotal + $service + $delivery - $discount;
            $paidAmount = collect($validated['payments'])->sum('amount');

            // 2) إنشاء الفاتورة
            $invoice = Invoice::create([
                'tenant_id'        => $tenant->id,
                'outlet_id'        => $shift->outlet_id,
                'terminal_id'      => $shift->terminal_id,
                'shift_id'         => $shift->id,
                'cashier_id'       => auth()->id(),
                'customer_id'      => $validated['customer_id'] ?? null,
                'invoice_number'   => $this->generateInvoiceNumber($tenant),
                'invoice_date'     => now(),
                'source'           => 'pos',
                'order_type'       => $validated['order_type'],
                'table_number'     => $validated['table_number'] ?? null,
                'subtotal'         => round($subtotal, 2),
                'discount_amount'  => round($discount, 2),
                'vat_amount'       => round($vatTotal, 2),
                'service_charge'   => $service,
                'delivery_fee'     => $delivery,
                'total_amount'     => round($totalAmount, 2),
                'paid_amount'      => $paidAmount,
                'balance_due'      => max(0, $totalAmount - $paidAmount),
                'status'           => 'completed',
                'payment_status'   => $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                'zatca_uuid'       => Str::uuid()->toString(),
                'notes'            => $validated['notes'] ?? null,
            ]);

            // 3) إنشاء العناصر
            foreach ($itemsData as $itemData) {
                $invoice->items()->create($itemData);
            }

            // 4) إنشاء المدفوعات
            foreach ($validated['payments'] as $payment) {
                Payment::create([
                    'tenant_id'  => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'customer_id'=> $validated['customer_id'] ?? null,
                    'shift_id'   => $shift->id,
                    'method'     => $payment['method'],
                    'amount'     => $payment['amount'],
                    'reference'  => $payment['reference'] ?? null,
                    'status'     => 'completed',
                    'paid_at'    => now(),
                ]);
            }

            // 5) توليد QR زاتكا
            $invoice->update([
                'zatca_qr_code' => $this->zatca->generateQrForInvoice($invoice),
                'zatca_hash'    => $this->zatca->generateInvoiceHash($invoice),
            ]);

            // 6) تحديث نقاط الولاء
            if ($validated['customer_id'] && $tenant->loyaltySettings?->is_active) {
                $this->processLoyaltyPoints($invoice, $loyaltyRedeemed);
            }

            // 7) تحديث الوردية
            $shift->increment('invoice_count');
            $shift->increment('total_sales', $totalAmount);
            $shift->increment('total_vat', $vatTotal);

            return response()->json([
                'success' => true,
                'invoice' => $invoice->load('items', 'payments', 'customer'),
                'message' => 'تمّ إنشاء الفاتورة بنجاح',
            ], 201);
        });
    }

    /**
     * استرجاع فاتورة (مرتجع)
     */
    public function refund(Request $request, Invoice $invoice)
    {
        $this->authorize('refund', $invoice);

        $validated = $request->validate([
            'reason'        => 'required|string',
            'type'          => 'required|in:full,partial',
            'items'         => 'required_if:type,partial|array',
            'items.*.id'    => 'required_if:type,partial|exists:sahab_invoice_items,id',
            'items.*.quantity' => 'required_if:type,partial|numeric|min:0.001',
        ]);

        return DB::transaction(function () use ($validated, $invoice) {
            $refundAmount = 0;

            if ($validated['type'] === 'full') {
                $refundAmount = $invoice->total_amount;
                // إعادة المخزون
                foreach ($invoice->items as $item) {
                    if ($item->product) {
                        $item->product->increment('current_stock', $item->quantity);
                    }
                }
                $invoice->update(['status' => 'refunded']);
            } else {
                foreach ($validated['items'] as $itemData) {
                    $item = InvoiceItem::find($itemData['id']);
                    if ($item && $item->invoice_id === $invoice->id) {
                        $qty = min($itemData['quantity'], $item->quantity);
                        $unitPrice = $item->total / $item->quantity;
                        $refundAmount += $unitPrice * $qty;
                        if ($item->product) {
                            $item->product->increment('current_stock', $qty);
                        }
                    }
                }
                $invoice->update(['status' => 'partial_refund']);
            }

            // تسجيل في الوردية
            if ($invoice->shift) {
                $invoice->shift->increment('total_refunds', $refundAmount);
            }

            // إخطار العميل
            if ($invoice->customer && $invoice->customer->mobile) {
                $this->whatsapp->sendRefundNotice($invoice->customer->mobile, $invoice, $refundAmount);
            }

            return response()->json([
                'success'      => true,
                'refund_amount'=> round($refundAmount, 2),
                'type'         => $validated['type'],
            ]);
        });
    }

    /**
     * فتح وردية كاشير
     */
    public function openShift(Request $request)
    {
        $validated = $request->validate([
            'outlet_id'    => 'required|exists:sahab_outlets,id',
            'terminal_id'  => 'nullable|exists:sahab_pos_terminals,id',
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $existing = Shift::where('cashier_id', auth()->id())
            ->where('status', 'open')->first();

        if ($existing) {
            return response()->json([
                'error' => 'لديك وردية مفتوحة بالفعل',
                'shift' => $existing,
            ], 400);
        }

        $shift = Shift::create([
            'tenant_id'   => auth()->user()->tenant_id,
            'outlet_id'   => $validated['outlet_id'],
            'terminal_id' => $validated['terminal_id'] ?? null,
            'cashier_id'  => auth()->id(),
            'opened_at'   => now(),
            'opening_cash'=> $validated['opening_cash'],
            'status'      => 'open',
        ]);

        return response()->json(['success' => true, 'shift' => $shift]);
    }

    /**
     * إغلاق وردية + توليد Z-Report
     */
    public function closeShift(Request $request, Shift $shift)
    {
        $this->authorize('close', $shift);

        $validated = $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'notes'        => 'nullable|string',
        ]);

        // حساب النقد المتوقّع
        $expectedCash = $shift->opening_cash + Payment::where('shift_id', $shift->id)
            ->where('method', 'cash')->sum('amount')
            - 0; // أي مصروفات نقديّة (لاحقاً)

        $variance = $validated['closing_cash'] - $expectedCash;

        // تحضير breakdown
        $payments = Payment::where('shift_id', $shift->id)
            ->select('method', DB::raw('SUM(amount) as total'))
            ->groupBy('method')->get()->pluck('total', 'method');

        $shift->update([
            'closed_at'         => now(),
            'closing_cash'      => $validated['closing_cash'],
            'expected_cash'     => $expectedCash,
            'cash_variance'     => $variance,
            'payment_breakdown' => $payments->toArray(),
            'status'            => 'closed',
            'closing_notes'     => $validated['notes'] ?? null,
        ]);

        // إنشاء تقرير Z (للمحاسبة)
        $report = $this->buildZReport($shift);

        // تنبيه إذا الفرق كبير
        if (abs($variance) > 50) {
            \App\Models\Sahab\SuspiciousActivity::create([
                'tenant_id'    => $shift->tenant_id,
                'user_id'      => $shift->cashier_id,
                'shift_id'     => $shift->id,
                'activity_type'=> 'cash_variance',
                'severity'     => abs($variance) > 200 ? 'high' : 'medium',
                'title'        => 'فرق نقدي مشبوه في الوردية',
                'description'  => "المتوقّع: {$expectedCash} ر.س | الفعلي: {$validated['closing_cash']} ر.س | الفرق: " . round($variance, 2),
                'evidence'     => ['shift_id' => $shift->id, 'variance' => $variance],
            ]);
        }

        return response()->json(['success' => true, 'shift' => $shift, 'report' => $report]);
    }

    // ==========================================================
    //  Helpers
    // ==========================================================
    protected function getOrCreateShift(Request $request): Shift
    {
        $shift = Shift::where('cashier_id', auth()->id())
            ->where('status', 'open')->first();

        if (!$shift) {
            abort(400, 'لا توجد وردية مفتوحة. افتح وردية أوّلاً.');
        }
        return $shift;
    }

    protected function generateInvoiceNumber($tenant): string
    {
        $prefix = 'INV-' . date('Y');
        $last = Invoice::where('tenant_id', $tenant->id)
            ->where('invoice_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')->first();

        $next = $last
            ? (int) explode('-', $last->invoice_number)[2] + 1
            : 1;

        return "{$prefix}-" . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    protected function processLoyaltyPoints(Invoice $invoice, float $redeemed): void
    {
        $customer = $invoice->customer;
        $tenant = $invoice->tenant ?? \App\Models\Sahab\Tenant::find($invoice->tenant_id);
        $settings = $tenant->loyaltySettings;
        if (!$settings || !$settings->is_active) return;

        // خصم المُسترَدَة
        if ($redeemed > 0) {
            $points = (int) ($redeemed / $settings->riyal_per_point);
            $customer->decrement('loyalty_points', $points);

            LoyaltyTransaction::create([
                'tenant_id'     => $tenant->id,
                'customer_id'   => $customer->id,
                'invoice_id'    => $invoice->id,
                'type'          => 'redeemed',
                'points'        => -$points,
                'balance_after' => $customer->loyalty_points,
                'value_in_riyal'=> $redeemed,
                'description'   => "استرداد {$points} نقطة في الفاتورة #{$invoice->invoice_number}",
            ]);
        }

        // إضافة نقاط جديدة
        $multiplier = match($customer->loyalty_tier) {
            'bronze'   => $settings->bronze_multiplier,
            'silver'   => $settings->silver_multiplier,
            'gold'     => $settings->gold_multiplier,
            'platinum' => $settings->platinum_multiplier,
            default    => 1.0,
        };

        $earned = (int) ($invoice->total_amount * $settings->points_per_riyal * $multiplier);
        $customer->increment('loyalty_points', $earned);
        $customer->increment('total_spent', $invoice->total_amount);
        $customer->increment('total_orders');
        $customer->update(['last_order_date' => now()]);

        // ترقية الفئة
        $newTier = $this->calculateTier($customer->total_spent, $settings);
        if ($newTier !== $customer->loyalty_tier) {
            $customer->update(['loyalty_tier' => $newTier]);
        }

        LoyaltyTransaction::create([
            'tenant_id'     => $tenant->id,
            'customer_id'   => $customer->id,
            'invoice_id'    => $invoice->id,
            'type'          => 'earned',
            'points'        => $earned,
            'balance_after' => $customer->loyalty_points,
            'description'   => "كسب {$earned} نقطة من الفاتورة #{$invoice->invoice_number}",
        ]);
    }

    protected function calculateTier(float $spent, $settings): string
    {
        if ($spent >= $settings->platinum_threshold * $settings->riyal_per_point) return 'platinum';
        if ($spent >= $settings->gold_threshold * $settings->riyal_per_point) return 'gold';
        if ($spent >= $settings->silver_threshold * $settings->riyal_per_point) return 'silver';
        return 'bronze';
    }

    protected function buildZReport(Shift $shift): array
    {
        return [
            'shift_id'      => $shift->id,
            'cashier'       => $shift->cashier_id,
            'opened_at'     => $shift->opened_at,
            'closed_at'     => $shift->closed_at,
            'invoice_count' => $shift->invoice_count,
            'total_sales'   => $shift->total_sales,
            'total_vat'     => $shift->total_vat,
            'opening_cash'  => $shift->opening_cash,
            'closing_cash'  => $shift->closing_cash,
            'expected_cash' => $shift->expected_cash,
            'variance'      => $shift->cash_variance,
            'payments'      => $shift->payment_breakdown,
        ];
    }
}
