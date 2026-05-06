<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\WhatsappStore;
use App\Models\Sahab\StoreCart;
use App\Models\Sahab\StoreCoupon;
use App\Models\Sahab\StoreAnalytic;
use App\Models\Sahab\Product;
use App\Models\Sahab\Category;
use App\Models\Sahab\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WhatsappStoreController — متجر الواتساب
 * --------------------------------------------------------------------
 *  جزآن:
 *
 *  أ) إدارة (للتاجر — Authenticated):
 *     - settings()       : إعدادات المتجر
 *     - update()         : تحديث المتجر
 *     - publish()        : نشر/إيقاف المتجر
 *     - analytics()      : إحصائيّات
 *     - coupons()        : الكوبونات
 *
 *  ب) عرض عام (للعملاء — Public):
 *     - show()           : صفحة المتجر العامّة
 *     - product()        : عرض منتج
 *     - addToCart()      : إضافة للسلّة
 *     - checkout()       : تأكيد الطلب → واتساب
 *
 *  هذه الميزة متاحة فقط للباقة المؤسّسيّة (enterprise)
 * --------------------------------------------------------------------
 */
class WhatsappStoreController extends Controller
{
    // ============================================================
    //  أ) إدارة (للتاجر)
    // ============================================================

    public function settings(Request $request)
    {
        $this->ensureFeatureAccess($request);

        $tenant = $request->user()->tenant;
        $store = WhatsappStore::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'name'             => $tenant->name,
                'whatsapp_number'  => $tenant->mobile,
                'is_active'        => false,
                'is_published'     => false,
            ]
        );

        return view('sahab.store.settings', compact('store'));
    }

    public function update(Request $request)
    {
        $this->ensureFeatureAccess($request);

        $validated = $request->validate([
            'name'                  => 'required|string|max:120',
            'tagline'               => 'nullable|string|max:200',
            'description'           => 'nullable|string',
            'whatsapp_number'       => 'required|string|max:20',
            'theme'                 => 'required|in:modern,elegant,minimal,classic,food,fashion',
            'primary_color'         => 'required|string|size:7',
            'secondary_color'       => 'required|string|size:7',
            'min_order_amount'      => 'nullable|numeric|min:0',
            'delivery_fee'          => 'nullable|numeric|min:0',
            'free_delivery_above'   => 'nullable|numeric|min:0',
            'enable_delivery'       => 'boolean',
            'enable_pickup'         => 'boolean',
            'show_prices'           => 'boolean',
            'require_customer_info' => 'boolean',
            'order_message_template' => 'nullable|string',
            'working_hours'         => 'nullable|array',
            'social_links'          => 'nullable|array',
        ]);

        $store = WhatsappStore::firstOrCreate(['tenant_id' => $request->user()->tenant_id]);
        $store->update($validated);

        return response()->json(['success' => true, 'store' => $store, 'url' => $store->url]);
    }

    public function publish(Request $request)
    {
        $this->ensureFeatureAccess($request);

        $store = WhatsappStore::where('tenant_id', $request->user()->tenant_id)->firstOrFail();

        $publish = $request->boolean('publish', true);

        $store->update([
            'is_active'    => $publish,
            'is_published' => $publish,
            'published_at' => $publish ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'is_published' => $store->is_published,
            'url' => $store->url,
        ]);
    }

    public function analytics(Request $request)
    {
        $this->ensureFeatureAccess($request);

        $store = WhatsappStore::where('tenant_id', $request->user()->tenant_id)->firstOrFail();

        $last30Days = StoreAnalytic::where('store_id', $store->id)
            ->where('date', '>=', now()->subDays(30))
            ->orderBy('date')
            ->get();

        return response()->json([
            'store' => [
                'total_visits'  => $store->total_visits,
                'total_orders'  => $store->total_orders,
                'total_revenue' => $store->total_revenue,
                'is_published'  => $store->is_published,
                'url'           => $store->url,
            ],
            'daily' => $last30Days,
            'today' => $last30Days->where('date', today())->first(),
        ]);
    }

    // ============================================================
    //  ب) عرض عام (للعملاء — public, no auth)
    // ============================================================

    public function show(Request $request, string $slug)
    {
        $store = WhatsappStore::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        // تتبّع الزيارة
        $this->trackVisit($store, $request);

        // المنتجات
        $products = Product::where('tenant_id', $store->tenant_id)
            ->where('is_active', true)
            ->where('available_in_delivery_apps', true)
            ->withoutGlobalScopes()
            ->orderBy('sort_order')
            ->get();

        $categories = Category::where('tenant_id', $store->tenant_id)
            ->where('is_active', true)
            ->withoutGlobalScopes()
            ->orderBy('sort_order')
            ->get();

        return view('sahab.store.public', compact('store', 'products', 'categories'));
    }

    public function product(Request $request, string $slug, int $productId)
    {
        $store = WhatsappStore::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $product = Product::where('tenant_id', $store->tenant_id)
            ->where('id', $productId)
            ->withoutGlobalScopes()
            ->firstOrFail();

        return view('sahab.store.product', compact('store', 'product'));
    }

    public function addToCart(Request $request, string $slug)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'quantity'   => 'required|integer|min:1',
            'modifiers'  => 'nullable|array',
            'notes'      => 'nullable|string',
        ]);

        $store = WhatsappStore::where('slug', $slug)->firstOrFail();
        $sessionId = $request->session()->getId();

        $product = Product::where('tenant_id', $store->tenant_id)
            ->where('id', $validated['product_id'])
            ->withoutGlobalScopes()
            ->firstOrFail();

        // الحصول على/إنشاء سلّة
        $cart = StoreCart::firstOrCreate(
            [
                'store_id'   => $store->id,
                'session_id' => $sessionId,
                'status'     => 'active',
            ],
            [
                'tenant_id' => $store->tenant_id,
                'items'     => [],
                'subtotal'  => 0,
                'total'     => 0,
                'last_activity_at' => now(),
            ]
        );

        $items = $cart->items ?? [];

        // تحقّق إذا المنتج موجود — زِد الكمّيّة
        $found = false;
        foreach ($items as &$item) {
            if ($item['product_id'] === $product->id && empty($validated['modifiers'])) {
                $item['quantity'] += $validated['quantity'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            $items[] = [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'unit_price'  => (float) $product->sale_price,
                'quantity'    => $validated['quantity'],
                'modifiers'   => $validated['modifiers'] ?? [],
                'notes'       => $validated['notes'] ?? null,
                'image'       => $product->image,
            ];
        }

        // إعادة الحساب
        $subtotal = array_sum(array_map(fn($i) => $i['unit_price'] * $i['quantity'], $items));

        $cart->update([
            'items'    => $items,
            'subtotal' => $subtotal,
            'total'    => $subtotal,
            'last_activity_at' => now(),
        ]);

        // تتبّع
        StoreAnalytic::firstOrCreate(
            ['store_id' => $store->id, 'date' => today()],
            ['tenant_id' => $store->tenant_id]
        )->increment('add_to_cart_count');

        return response()->json([
            'success' => true,
            'cart' => $cart,
            'items_count' => count($items),
        ]);
    }

    public function applyCoupon(Request $request, string $slug)
    {
        $validated = $request->validate(['code' => 'required|string']);

        $store = WhatsappStore::where('slug', $slug)->firstOrFail();
        $cart = StoreCart::where('store_id', $store->id)
            ->where('session_id', $request->session()->getId())
            ->where('status', 'active')
            ->firstOrFail();

        $coupon = StoreCoupon::where('store_id', $store->id)
            ->where('code', $validated['code'])
            ->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['error' => 'الكوبون غير صالح أو منتهي'], 422);
        }

        $discount = $coupon->calculateDiscount($cart->subtotal);

        $cart->update([
            'discount'    => $discount,
            'total'       => $cart->subtotal - $discount + ($store->delivery_fee ?? 0),
            'coupon_code' => $coupon->code,
        ]);

        return response()->json([
            'success'  => true,
            'discount' => $discount,
            'total'    => $cart->total,
        ]);
    }

    /**
     * تأكيد الطلب — توليد رسالة واتساب
     */
    public function checkout(Request $request, string $slug)
    {
        $validated = $request->validate([
            'customer_name'  => 'required|string|max:120',
            'customer_phone' => 'required|string|max:20',
            'order_type'     => 'required|in:delivery,pickup',
            'delivery_address' => 'required_if:order_type,delivery|string',
            'notes'          => 'nullable|string',
        ]);

        $store = WhatsappStore::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $cart = StoreCart::where('store_id', $store->id)
            ->where('session_id', $request->session()->getId())
            ->where('status', 'active')
            ->firstOrFail();

        if (empty($cart->items)) {
            return response()->json(['error' => 'السلّة فارغة'], 422);
        }

        // إنشاء الفاتورة في sahab_invoices
        $invoice = $this->createInvoiceFromCart($store, $cart, $validated);

        // توليد رسالة واتساب
        $message = $this->buildWhatsappMessage($store, $cart, $validated, $invoice);
        $whatsappLink = $store->generateWhatsappLink($message);

        // تحديث السلّة
        $cart->update([
            'status'         => 'sent_to_whatsapp',
            'customer_name'  => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
        ]);

        // إحصائيّات
        $store->increment('total_orders');
        $store->increment('total_revenue', $cart->total);
        StoreAnalytic::firstOrCreate(
            ['store_id' => $store->id, 'date' => today()],
            ['tenant_id' => $store->tenant_id]
        )->increment('whatsapp_orders_count');

        return response()->json([
            'success'        => true,
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'whatsapp_url'   => $whatsappLink,
        ]);
    }

    // ============================================================
    //  Helpers
    // ============================================================

    /**
     * التحقّق من إمكانيّة الوصول للميزة (الباقة المؤسّسيّة فقط)
     */
    protected function ensureFeatureAccess(Request $request): void
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) abort(403);

        if (!$tenant->canAccessFeature('whatsapp_store')) {
            abort(403, 'متجر الواتساب متاح فقط في الباقة المؤسّسيّة. ترقّى الآن للاستفادة من هذه الميزة.');
        }
    }

    protected function trackVisit(WhatsappStore $store, Request $request): void
    {
        // لمنع تسجيل زيارات متكرّرة من نفس الجلسة في نفس الدقيقة
        $key = "store_visit:{$store->id}:" . $request->session()->getId();
        if (!cache()->has($key)) {
            cache()->put($key, true, 60);
            $store->increment('total_visits');

            $analytic = StoreAnalytic::firstOrCreate(
                ['store_id' => $store->id, 'date' => today()],
                ['tenant_id' => $store->tenant_id]
            );
            $analytic->increment('visits');
        }
    }

    protected function createInvoiceFromCart($store, $cart, $customerData): Invoice
    {
        return DB::transaction(function () use ($store, $cart, $customerData) {
            $vat = $cart->subtotal * 0.15 / 1.15;

            $invoice = Invoice::create([
                'tenant_id'        => $store->tenant_id,
                'outlet_id'        => $store->tenant->outlets->first()?->id,
                'cashier_id'       => $store->tenant->user_id ?? 1,
                'invoice_number'   => 'WS-' . date('Y') . '-' . str_pad(\App\Models\Sahab\Invoice::where('source', 'whatsapp')->count() + 1, 6, '0', STR_PAD_LEFT),
                'invoice_date'     => now(),
                'source'           => 'whatsapp',
                'order_type'       => $customerData['order_type'],
                'subtotal'         => round($cart->subtotal - $vat, 2),
                'discount_amount'  => $cart->discount ?? 0,
                'vat_amount'       => round($vat, 2),
                'delivery_fee'     => $store->delivery_fee ?? 0,
                'total_amount'     => $cart->total,
                'paid_amount'      => 0,
                'balance_due'      => $cart->total,
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'notes'            => "طلب من متجر واتساب — {$customerData['customer_name']} — {$customerData['customer_phone']}\n" .
                                     ($customerData['delivery_address'] ?? '') . "\n" .
                                     ($customerData['notes'] ?? ''),
            ]);

            // عناصر الفاتورة
            foreach ($cart->items as $item) {
                $itemTotal = $item['unit_price'] * $item['quantity'];
                $itemVat = $itemTotal * 0.15 / 1.15;
                $invoice->items()->create([
                    'product_id'    => $item['product_id'],
                    'product_name'  => $item['name'],
                    'quantity'      => $item['quantity'],
                    'unit_price'    => $item['unit_price'],
                    'vat_amount'    => round($itemVat, 2),
                    'subtotal'      => round($itemTotal - $itemVat, 2),
                    'total'         => $itemTotal,
                    'modifiers'     => $item['modifiers'] ?? null,
                    'special_instructions' => $item['notes'] ?? null,
                ]);
            }

            return $invoice;
        });
    }

    protected function buildWhatsappMessage($store, $cart, $customer, $invoice): string
    {
        $msg = "*طلب جديد من متجر {$store->name}*\n\n";
        $msg .= "📋 رقم الطلب: {$invoice->invoice_number}\n";
        $msg .= "👤 الاسم: {$customer['customer_name']}\n";
        $msg .= "📱 الجوّال: {$customer['customer_phone']}\n";

        if ($customer['order_type'] === 'delivery' && !empty($customer['delivery_address'])) {
            $msg .= "📍 العنوان: {$customer['delivery_address']}\n";
        } else {
            $msg .= "🏪 سيتمّ الاستلام من المحلّ\n";
        }

        $msg .= "\n*🛒 الطلبات:*\n";
        foreach ($cart->items as $item) {
            $msg .= "• {$item['name']} × {$item['quantity']}";
            if ($store->show_prices) {
                $msg .= " = " . number_format($item['unit_price'] * $item['quantity'], 2) . " ر.س";
            }
            if (!empty($item['notes'])) {
                $msg .= "\n   📝 {$item['notes']}";
            }
            $msg .= "\n";
        }

        if ($store->show_prices) {
            $msg .= "\n*💰 الفاتورة:*\n";
            $msg .= "المجموع: " . number_format($cart->subtotal, 2) . " ر.س\n";
            if ($cart->discount > 0) {
                $msg .= "الخصم: - " . number_format($cart->discount, 2) . " ر.س\n";
            }
            if ($customer['order_type'] === 'delivery' && $store->delivery_fee > 0) {
                $msg .= "التوصيل: " . number_format($store->delivery_fee, 2) . " ر.س\n";
            }
            $msg .= "*الإجمالي: " . number_format($cart->total, 2) . " ر.س*\n";
        }

        if (!empty($customer['notes'])) {
            $msg .= "\n📝 ملاحظات: {$customer['notes']}\n";
        }

        $msg .= "\n_تمّ إرسال هذا الطلب من متجر {$store->name} عبر منصّة سحاب 🌥_";

        return $msg;
    }
}
