<?php

namespace App\Services\Sahab;

use App\Models\Sahab\DeliveryIntegration;
use App\Models\Sahab\ExternalOrder;
use App\Models\Sahab\Invoice;
use App\Models\Sahab\InvoiceItem;
use App\Models\Sahab\Product;
use App\Models\Sahab\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DeliveryAggregatorService
 *
 * المنسّق المركزي لتطبيقات التوصيل السعوديّة:
 *  🟠 هنقرستيشن (HungerStation)
 *  🔴 جاهز (Jahez)
 *  🟢 توصيل (Toshhel)
 *  🟣 مرسول (Mrsool)
 *  ⚫ تشيفز (The Chefz)
 *  🔴 نينجا (NinjaPro)
 *  🟡 تو يو (ToYou)
 *  🟠 طلبات (Talabat)
 *
 *  المهام:
 *   - استقبال webhooks وتحويلها لصيغة موحّدة
 *   - قبول/رفض الطلب
 *   - تحديث الحالات (cooking → ready → delivered)
 *   - مزامنة القائمة
 */
class DeliveryAggregatorService
{
    protected array $platforms = [
        'hungerstation' => [
            'name_ar' => 'هنقرستيشن', 'name_en' => 'HungerStation',
            'base_url' => 'https://api.hungerstation.com/partners/v1',
            'commission_default' => 18.0, 'supports_menu_sync' => true,
        ],
        'jahez' => [
            'name_ar' => 'جاهز', 'name_en' => 'Jahez',
            'base_url' => 'https://api.jahez.net/v2',
            'commission_default' => 17.0, 'supports_menu_sync' => true,
        ],
        'toshhel' => [
            'name_ar' => 'توصيل', 'name_en' => 'Toshhel',
            'base_url' => 'https://partner-api.toshhel.com/v1',
            'commission_default' => 15.0, 'supports_menu_sync' => true,
        ],
        'mrsool' => [
            'name_ar' => 'مرسول', 'name_en' => 'Mrsool',
            'base_url' => 'https://api.mrsool.co/business/v1',
            'commission_default' => 0.0, 'supports_menu_sync' => false,
        ],
        'thechefz' => [
            'name_ar' => 'تشيفز', 'name_en' => 'The Chefz',
            'base_url' => 'https://api.thechefz.co/partners/v1',
            'commission_default' => 22.0, 'supports_menu_sync' => true,
        ],
        'ninja' => [
            'name_ar' => 'نينجا', 'name_en' => 'NinjaPro',
            'base_url' => 'https://api.ninja.sa/merchant/v3',
            'commission_default' => 12.0, 'supports_menu_sync' => true,
        ],
        'toyou' => [
            'name_ar' => 'تو يو', 'name_en' => 'ToYou',
            'base_url' => 'https://api.toyou.sa/integrations/v1',
            'commission_default' => 16.0, 'supports_menu_sync' => true,
        ],
        'talabat' => [
            'name_ar' => 'طلبات', 'name_en' => 'Talabat',
            'base_url' => 'https://api.talabat.com/integration/v1',
            'commission_default' => 19.0, 'supports_menu_sync' => true,
        ],
    ];

    /**
     * استقبال webhook ومعالجته
     */
    public function handleWebhook(string $platform, string $tenantCode, array $payload, array $headers): array
    {
        if (!isset($this->platforms[$platform])) {
            return ['success' => false, 'error' => 'منصّة غير مدعومة'];
        }

        $tenant = Tenant::where('code', $tenantCode)->first();
        if (!$tenant) return ['success' => false, 'error' => 'مستأجر غير موجود'];

        // التحقّق من التوقيع
        if (!$this->verifySignature($platform, $payload, $headers, $tenant)) {
            return ['success' => false, 'error' => 'توقيع غير صالح'];
        }

        // تحويل الـ payload لصيغة موحّدة
        $normalized = $this->normalizePayload($platform, $payload);
        if (!$normalized) {
            return ['success' => true, 'note' => 'event ignored'];
        }

        return match ($normalized['event']) {
            'order_created' => $this->createExternalOrder($tenant, $platform, $normalized),
            'order_updated' => $this->updateExternalOrder($tenant, $platform, $normalized),
            'order_cancelled' => $this->cancelExternalOrder($tenant, $platform, $normalized),
            default => ['success' => true],
        };
    }

    /**
     * قبول طلب خارجي → تحويله لفاتورة بيع
     */
    public function acceptOrder(int $orderId, int $prepTimeMinutes = 25): array
    {
        $order = ExternalOrder::findOrFail($orderId);
        $tenant = Tenant::find($order->tenant_id);

        // إرسال للمنصّة الأصليّة
        $result = $this->callPlatformApi(
            $order->platform,
            'POST',
            $this->getAcceptEndpoint($order->platform, $order->external_id),
            ['prep_time_minutes' => $prepTimeMinutes],
            $tenant
        );

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'فشل الاتّصال بالمنصّة'];
        }

        // إنشاء فاتورة بيع داخل سحاب
        $invoice = $this->createInvoiceFromExternalOrder($order);

        $order->update([
            'status' => 'accepted',
            'invoice_id' => $invoice->id,
            'prep_time_minutes' => $prepTimeMinutes,
            'accepted_at' => now(),
        ]);

        return ['success' => true, 'invoice_id' => $invoice->id];
    }

    /**
     * رفض طلب
     */
    public function rejectOrder(int $orderId, string $reason): array
    {
        $order = ExternalOrder::findOrFail($orderId);
        $tenant = Tenant::find($order->tenant_id);

        $result = $this->callPlatformApi(
            $order->platform,
            'POST',
            $this->getRejectEndpoint($order->platform, $order->external_id),
            ['reason' => $reason],
            $tenant
        );

        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $result;
    }

    /**
     * تحديث حالة الطلب (cooking, ready, picked_up, delivered)
     */
    public function updateStatus(int $orderId, string $newStatus): array
    {
        $order = ExternalOrder::findOrFail($orderId);
        $tenant = Tenant::find($order->tenant_id);

        $platformStatus = $this->mapStatusForPlatform($order->platform, $newStatus);

        $result = $this->callPlatformApi(
            $order->platform,
            'PATCH',
            $this->getUpdateStatusEndpoint($order->platform, $order->external_id),
            ['status' => $platformStatus],
            $tenant
        );

        if ($result['success']) {
            $order->update(['status' => $newStatus]);
            if ($newStatus === 'delivered') {
                $order->update(['delivered_at' => now()]);
            }
        }

        return $result;
    }

    /**
     * مزامنة القائمة لكل المنصّات النشطة
     */
    public function syncMenuToAllPlatforms(Tenant $tenant): array
    {
        $integrations = DeliveryIntegration::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $products = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('available_in_delivery_apps', true)
            ->with('category')
            ->get();

        $results = [];

        foreach ($integrations as $integration) {
            if (!($this->platforms[$integration->platform]['supports_menu_sync'] ?? false)) {
                $results[$integration->platform] = ['success' => false, 'note' => 'لا يدعم مزامنة القائمة'];
                continue;
            }

            $menuPayload = $this->buildMenuPayload($integration->platform, $products);
            $endpoint = $this->getMenuSyncEndpoint($integration->platform);

            $result = $this->callPlatformApi(
                $integration->platform,
                'PUT',
                $endpoint,
                $menuPayload,
                $tenant
            );

            $results[$integration->platform] = $result;

            if ($result['success']) {
                $integration->update(['last_menu_sync_at' => now()]);
            }
        }

        return $results;
    }

    // ================================================================
    //  Payload Normalization (لكل منصّة)
    // ================================================================

    protected function normalizePayload(string $platform, array $payload): ?array
    {
        return match ($platform) {
            'hungerstation' => $this->normalizeHungerStation($payload),
            'jahez' => $this->normalizeJahez($payload),
            'toshhel' => $this->normalizeToshhel($payload),
            'mrsool' => $this->normalizeMrsool($payload),
            'thechefz' => $this->normalizeChefz($payload),
            'ninja' => $this->normalizeNinja($payload),
            'toyou' => $this->normalizeToYou($payload),
            'talabat' => $this->normalizeTalabat($payload),
            default => null,
        };
    }

    protected function normalizeHungerStation(array $p): ?array
    {
        $event = $p['event'] ?? '';
        $order = $p['order'] ?? [];
        if ($event !== 'order.placed') return null;

        return [
            'event' => 'order_created',
            'external_id' => (string) ($order['id'] ?? ''),
            'customer' => [
                'name' => $order['customer']['name'] ?? '',
                'phone' => $order['customer']['phone'] ?? '',
            ],
            'delivery' => [
                'address' => $order['delivery_address']['formatted'] ?? '',
                'lat' => $order['delivery_address']['latitude'] ?? null,
                'lng' => $order['delivery_address']['longitude'] ?? null,
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['name'],
                'qty' => $i['quantity'],
                'price' => $i['unit_price'],
                'note' => $i['special_instructions'] ?? null,
            ], $order['items'] ?? []),
            'amounts' => [
                'subtotal' => $order['subtotal'] ?? 0,
                'delivery_fee' => $order['delivery_fee'] ?? 0,
                'commission' => $order['commission'] ?? 0,
                'total' => $order['total'] ?? 0,
            ],
            'payment_method' => $order['payment_method'] ?? 'cash',
            'is_paid' => ($order['payment_status'] ?? '') === 'paid',
        ];
    }

    protected function normalizeJahez(array $p): ?array
    {
        if (($p['type'] ?? '') !== 'NEW_ORDER') return null;
        $d = $p['data'] ?? [];

        return [
            'event' => 'order_created',
            'external_id' => (string) ($d['order_id'] ?? ''),
            'customer' => [
                'name' => $d['customer_name'] ?? '',
                'phone' => $d['customer_mobile'] ?? '',
            ],
            'delivery' => [
                'address' => $d['address']['full_address'] ?? '',
                'lat' => $d['address']['lat'] ?? null,
                'lng' => $d['address']['lng'] ?? null,
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['product_name'],
                'qty' => $i['quantity'],
                'price' => $i['price'],
                'note' => $i['notes'] ?? null,
            ], $d['items'] ?? []),
            'amounts' => [
                'subtotal' => $d['amount_before_tax'] ?? $d['total'] ?? 0,
                'delivery_fee' => $d['delivery_fee'] ?? 0,
                'commission' => $d['commission'] ?? 0,
                'total' => $d['total'] ?? 0,
            ],
            'payment_method' => ($d['payment_type'] ?? '') === 'ONLINE' ? 'online' : 'cash',
            'is_paid' => ($d['payment_type'] ?? '') === 'ONLINE',
        ];
    }

    protected function normalizeToshhel(array $p): ?array
    {
        if (($p['event_type'] ?? '') !== 'order.created') return null;
        $o = $p['order'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['reference'],
            'customer' => ['name' => $o['client_name'], 'phone' => $o['client_phone']],
            'delivery' => [
                'address' => $o['drop_off']['address'] ?? '',
                'lat' => $o['drop_off']['latitude'] ?? null,
                'lng' => $o['drop_off']['longitude'] ?? null,
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['title'], 'qty' => $i['count'],
                'price' => $i['unit_cost'], 'note' => $i['comment'] ?? null,
            ], $o['items']),
            'amounts' => [
                'subtotal' => $o['cart_total'],
                'delivery_fee' => $o['delivery_cost'],
                'total' => $o['grand_total'],
            ],
            'payment_method' => $o['payment_method'] ?? 'cash',
            'is_paid' => ($o['payment_status'] ?? '') === 'completed',
        ];
    }

    protected function normalizeMrsool(array $p): ?array
    {
        if (($p['event'] ?? '') !== 'pickup.requested') return null;
        $o = $p['order'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['order_id'],
            'customer' => [
                'name' => $o['customer_name'] ?? 'عميل مرسول',
                'phone' => $o['customer_phone'] ?? '',
            ],
            'delivery' => [
                'address' => $o['drop_off_address'] ?? '',
                'lat' => $o['drop_off_lat'] ?? null,
                'lng' => $o['drop_off_lng'] ?? null,
            ],
            'items' => [[
                'name' => 'طلب مرسول — ' . ($o['notes'] ?? 'مفصّل بالصورة'),
                'qty' => 1,
                'price' => (float) ($o['estimated_value'] ?? 0),
                'note' => $o['notes'] ?? null,
            ]],
            'amounts' => [
                'subtotal' => (float) ($o['estimated_value'] ?? 0),
                'delivery_fee' => 0,
                'total' => (float) ($o['estimated_value'] ?? 0),
            ],
            'payment_method' => 'driver_cash',
            'is_paid' => false,
        ];
    }

    protected function normalizeChefz(array $p): ?array
    {
        if (($p['action'] ?? '') !== 'create_order') return null;
        $o = $p['data'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['order_number'],
            'customer' => [
                'name' => $o['customer']['full_name'],
                'phone' => $o['customer']['mobile'],
            ],
            'delivery' => [
                'address' => $o['delivery_location']['address_text'],
                'lat' => $o['delivery_location']['latitude'],
                'lng' => $o['delivery_location']['longitude'],
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['name'], 'qty' => $i['quantity'],
                'price' => $i['price'], 'note' => $i['customer_note'] ?? null,
            ], $o['line_items']),
            'amounts' => [
                'subtotal' => $o['amounts']['subtotal'],
                'delivery_fee' => $o['amounts']['delivery'],
                'commission' => $o['amounts']['commission'] ?? 0,
                'total' => $o['amounts']['total'],
            ],
            'payment_method' => $o['payment']['method'] ?? 'cash',
            'is_paid' => $o['payment']['is_paid'] ?? false,
        ];
    }

    protected function normalizeNinja(array $p): ?array
    {
        if (($p['notification_type'] ?? '') !== 'order.received') return null;
        $o = $p['order'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['id'],
            'customer' => ['name' => $o['user']['name'], 'phone' => $o['user']['phone']],
            'delivery' => [
                'address' => $o['address']['details'],
                'lat' => $o['address']['lat'], 'lng' => $o['address']['lng'],
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['product_name'], 'qty' => $i['qty'], 'price' => $i['unit_price'],
            ], $o['products']),
            'amounts' => [
                'subtotal' => $o['products_total'],
                'delivery_fee' => $o['delivery_fee'],
                'commission' => $o['platform_fee'] ?? 0,
                'total' => $o['grand_total'],
            ],
            'payment_method' => $o['payment_type'] ?? 'cash',
            'is_paid' => ($o['payment_status'] ?? '') === 'paid',
        ];
    }

    protected function normalizeToYou(array $p): ?array
    {
        if (($p['event'] ?? '') !== 'NEW_ORDER') return null;
        $o = $p['order'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['order_uuid'],
            'customer' => ['name' => $o['customer_name'], 'phone' => $o['customer_phone']],
            'delivery' => [
                'address' => $o['delivery_address'],
                'lat' => $o['delivery_lat'] ?? null,
                'lng' => $o['delivery_lng'] ?? null,
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['item_name'], 'qty' => $i['quantity'], 'price' => $i['price'],
            ], $o['items']),
            'amounts' => [
                'subtotal' => $o['items_total'],
                'delivery_fee' => $o['delivery_amount'],
                'total' => $o['total_amount'],
            ],
            'payment_method' => $o['payment_method'] ?? 'cash',
            'is_paid' => $o['is_prepaid'] ?? false,
        ];
    }

    protected function normalizeTalabat(array $p): ?array
    {
        if (($p['notificationType'] ?? '') !== 'NewOrder') return null;
        $o = $p['payload'];
        return [
            'event' => 'order_created',
            'external_id' => (string) $o['orderId'],
            'customer' => [
                'name' => trim(($o['customer']['firstName'] ?? '') . ' ' . ($o['customer']['lastName'] ?? '')),
                'phone' => $o['customer']['mobileNumber'] ?? '',
            ],
            'delivery' => [
                'address' => $o['address']['readableAddress'] ?? '',
                'lat' => $o['address']['latitude'] ?? null,
                'lng' => $o['address']['longitude'] ?? null,
            ],
            'items' => array_map(fn($i) => [
                'name' => $i['name'], 'qty' => $i['quantity'],
                'price' => $i['price'], 'note' => $i['comments'] ?? null,
            ], $o['products']),
            'amounts' => [
                'subtotal' => $o['subTotalCost'],
                'delivery_fee' => $o['deliveryFee'],
                'commission' => $o['commission'] ?? 0,
                'total' => $o['totalCost'],
            ],
            'payment_method' => strtolower($o['paymentType'] ?? 'cash'),
            'is_paid' => in_array($o['paymentType'] ?? '', ['CARD', 'WALLET']),
        ];
    }

    // ================================================================
    //  Order Management Helpers
    // ================================================================

    protected function createExternalOrder(Tenant $tenant, string $platform, array $data): array
    {
        $order = ExternalOrder::create([
            'tenant_id' => $tenant->id,
            'platform' => $platform,
            'external_id' => $data['external_id'],
            'customer_name' => $data['customer']['name'] ?? null,
            'customer_phone' => $data['customer']['phone'] ?? null,
            'delivery_address' => $data['delivery']['address'] ?? null,
            'delivery_lat' => $data['delivery']['lat'] ?? null,
            'delivery_lng' => $data['delivery']['lng'] ?? null,
            'items_json' => $data['items'],
            'subtotal' => $data['amounts']['subtotal'],
            'delivery_fee' => $data['amounts']['delivery_fee'] ?? 0,
            'platform_commission' => $data['amounts']['commission'] ?? 0,
            'total_amount' => $data['amounts']['total'],
            'payment_method' => $data['payment_method'] ?? 'unknown',
            'is_paid' => $data['is_paid'] ?? false,
            'status' => 'pending',
            'received_at' => now(),
        ]);

        return ['success' => true, 'order_id' => $order->id];
    }

    protected function updateExternalOrder(Tenant $tenant, string $platform, array $data): array
    {
        ExternalOrder::where('tenant_id', $tenant->id)
            ->where('platform', $platform)
            ->where('external_id', $data['external_id'])
            ->update(['status' => $data['status'] ?? 'updated']);

        return ['success' => true];
    }

    protected function cancelExternalOrder(Tenant $tenant, string $platform, array $data): array
    {
        $order = ExternalOrder::where('tenant_id', $tenant->id)
            ->where('platform', $platform)
            ->where('external_id', $data['external_id'])
            ->first();

        if ($order) {
            $order->update([
                'status' => 'cancelled',
                'rejection_reason' => $data['reason'] ?? '',
            ]);

            // إلغاء الفاتورة المرتبطة
            if ($order->invoice_id) {
                Invoice::where('id', $order->invoice_id)->update(['status' => 'cancelled']);
            }
        }

        return ['success' => true];
    }

    protected function createInvoiceFromExternalOrder(ExternalOrder $extOrder): Invoice
    {
        $invoiceNumber = 'INV-' . now()->format('Ymd') . '-' . str_pad(
            (Invoice::where('tenant_id', $extOrder->tenant_id)->whereDate('invoice_date', today())->count() + 1),
            4, '0', STR_PAD_LEFT
        );

        $vatAmount = round($extOrder->subtotal * 0.15, 2);

        $invoice = Invoice::create([
            'tenant_id' => $extOrder->tenant_id,
            'outlet_id' => 1, // الفرع الافتراضي
            'cashier_id' => 1, // النظام
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now(),
            'source' => $extOrder->platform,
            'source_order_id' => $extOrder->external_id,
            'order_type' => 'delivery',
            'subtotal' => $extOrder->subtotal,
            'vat_amount' => $vatAmount,
            'delivery_fee' => $extOrder->delivery_fee,
            'total_amount' => $extOrder->total_amount,
            'paid_amount' => $extOrder->is_paid ? $extOrder->total_amount : 0,
            'status' => 'preparing',
            'payment_status' => $extOrder->is_paid ? 'paid' : 'unpaid',
        ]);

        // إنشاء الأصناف
        foreach ($extOrder->items_json as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_name' => $item['name'],
                'quantity' => $item['qty'],
                'unit_price' => $item['price'],
                'subtotal' => $item['price'] * $item['qty'],
                'total' => $item['price'] * $item['qty'],
                'special_instructions' => $item['note'] ?? null,
            ]);
        }

        return $invoice;
    }

    // ================================================================
    //  Helpers
    // ================================================================

    public function getPlatforms(): array { return $this->platforms; }

    public function getActiveIntegrations(Tenant $tenant): array
    {
        $integrations = DeliveryIntegration::where('tenant_id', $tenant->id)->get()->keyBy('platform');
        $result = [];

        foreach ($this->platforms as $key => $info) {
            $i = $integrations[$key] ?? null;
            $result[] = [
                'platform' => $key,
                'name_ar' => $info['name_ar'],
                'name_en' => $info['name_en'],
                'commission_default' => $info['commission_default'],
                'supports_menu_sync' => $info['supports_menu_sync'],
                'is_active' => $i?->is_active ?? false,
                'is_configured' => $i && $i->api_credentials,
                'commission_rate' => $i?->commission_rate ?? $info['commission_default'],
                'last_order_at' => $i?->last_order_at,
                'webhook_url' => url("/api/delivery/webhook/{$key}/{$tenant->code}"),
            ];
        }

        return $result;
    }

    protected function callPlatformApi(string $platform, string $method, string $endpoint, array $body, Tenant $tenant): array
    {
        $integration = DeliveryIntegration::where('tenant_id', $tenant->id)
            ->where('platform', $platform)
            ->first();

        if (!$integration || !$integration->is_active) {
            return ['success' => false, 'error' => 'التكامل غير مفعّل'];
        }

        $creds = $integration->decrypted_credentials;
        $headers = $this->buildHeaders($platform, $creds);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->send($method, $endpoint, ['json' => $body]);

            return [
                'success' => $response->successful(),
                'code' => $response->status(),
                'data' => $response->json(),
                'error' => $response->successful() ? null : ($response->json()['message'] ?? "HTTP " . $response->status()),
            ];
        } catch (\Exception $e) {
            Log::error("API call failed [{$platform}]", ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function buildHeaders(string $platform, array $creds): array
    {
        $base = ['Content-Type' => 'application/json'];

        return match ($platform) {
            'hungerstation' => [...$base, 'Authorization' => 'Bearer ' . ($creds['api_key'] ?? '')],
            'jahez' => [...$base, 'X-Api-Key' => $creds['api_key'] ?? '', 'X-Account-Id' => $creds['account_id'] ?? ''],
            'toshhel' => [...$base, 'Authorization' => 'Token ' . ($creds['api_token'] ?? '')],
            'mrsool' => [...$base, 'Authorization' => 'Bearer ' . ($creds['access_token'] ?? '')],
            'thechefz' => [...$base, 'Authorization' => 'Bearer ' . ($creds['api_key'] ?? '')],
            'ninja' => [...$base, 'Authorization' => 'Bearer ' . ($creds['merchant_token'] ?? '')],
            'toyou' => [...$base, 'X-API-Key' => $creds['api_key'] ?? ''],
            'talabat' => [...$base, 'Authorization' => 'Bearer ' . ($creds['access_token'] ?? '')],
            default => $base,
        };
    }

    protected function verifySignature(string $platform, array $payload, array $headers, Tenant $tenant): bool
    {
        // في الإنتاج: تحقّق HMAC. الآن نسمح للتجربة
        return true;
    }

    protected function mapStatusForPlatform(string $platform, string $status): string
    {
        return match ($platform) {
            'hungerstation' => match ($status) {
                'cooking' => 'preparing', 'ready' => 'ready_for_pickup',
                'picked_up' => 'in_delivery', default => $status,
            },
            'jahez' => strtoupper(match ($status) {
                'cooking' => 'IN_KITCHEN', 'ready' => 'READY',
                'picked_up' => 'PICKED_UP', 'delivered' => 'DELIVERED',
                default => $status,
            }),
            'talabat' => match ($status) {
                'cooking' => 'IN_PREPARATION', 'ready' => 'READY_FOR_COLLECTION',
                'picked_up' => 'COLLECTED', 'delivered' => 'DELIVERED',
                default => $status,
            },
            default => $status,
        };
    }

    protected function getAcceptEndpoint(string $platform, string $orderId): string
    {
        $base = $this->platforms[$platform]['base_url'];
        return match ($platform) {
            'hungerstation', 'thechefz' => "$base/orders/$orderId/accept",
            'jahez' => "$base/orders/$orderId/accept",
            'toshhel' => "$base/orders/$orderId/confirm",
            'mrsool' => "$base/orders/$orderId/ready",
            'ninja', 'toyou' => "$base/orders/$orderId/accept",
            'talabat' => "$base/orders/$orderId/accept",
            default => "$base/orders/$orderId/accept",
        };
    }

    protected function getRejectEndpoint(string $platform, string $orderId): string
    {
        $base = $this->platforms[$platform]['base_url'];
        return "$base/orders/$orderId/reject";
    }

    protected function getUpdateStatusEndpoint(string $platform, string $orderId): string
    {
        $base = $this->platforms[$platform]['base_url'];
        return "$base/orders/$orderId/status";
    }

    protected function getMenuSyncEndpoint(string $platform): string
    {
        $base = $this->platforms[$platform]['base_url'];
        return match ($platform) {
            'hungerstation' => "$base/menu",
            'jahez' => "$base/products/bulk",
            'toshhel' => "$base/catalog",
            'thechefz' => "$base/menu/upsert",
            'ninja' => "$base/inventory/sync",
            'toyou' => "$base/menu/sync",
            'talabat' => "$base/menu",
            default => "$base/menu",
        };
    }

    protected function buildMenuPayload(string $platform, $products): array
    {
        $items = $products->map(fn($p) => [
            'sku' => 'sahab-' . $p->id,
            'name' => $p->name,
            'name_en' => $p->name_en ?? $p->name,
            'price' => (float) $p->sale_price,
            'description' => $p->description ?? '',
            'category' => $p->category->name ?? 'عام',
            'image_url' => $p->image,
            'is_available' => $p->current_stock > 0 && $p->is_active,
        ])->toArray();

        return ['items' => $items];
    }
}
