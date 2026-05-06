<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\DeliveryIntegration;
use App\Models\Sahab\ExternalOrder;
use App\Models\Sahab\Tenant;
use App\Services\Sahab\DeliveryAggregatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryController — تكاملات التوصيل
 * --------------------------------------------------------------------
 *  - integrations()         : قائمة المنصّات (مع حالة كل واحدة)
 *  - configure()            : ربط منصّة (إدخال API keys)
 *  - webhook()              : استقبال طلب من المنصّة
 *  - acceptOrder()          : قبول طلب
 *  - rejectOrder()          : رفض طلب
 *  - updateStatus()         : تحديث حالة
 *  - syncMenu()             : مزامنة القائمة
 * --------------------------------------------------------------------
 */
class DeliveryController extends Controller
{
    public function __construct(
        protected DeliveryAggregatorService $aggregator,
    ) {}

    /**
     * قائمة التكاملات لمستأجر معيّن
     */
    public function integrations(Request $request)
    {
        $this->middleware('auth:sanctum');

        $tenant = auth()->user()->tenant;
        $platforms = ['hungerstation', 'jahez', 'toshhel', 'mrsool', 'thechefz', 'ninja', 'toyou', 'talabat'];
        $rows = DeliveryIntegration::where('tenant_id', $tenant->id)->get()->keyBy('platform');

        $result = collect($platforms)->map(function ($platform) use ($rows, $tenant) {
            $info = $this->aggregator->getPlatformInfo($platform);
            $integration = $rows->get($platform);

            return [
                'platform'         => $platform,
                'name_ar'          => $info['name_ar'],
                'name_en'          => $info['name_en'],
                'color'            => $info['color'],
                'commission_default'=> $info['commission_default'],
                'supports_menu_sync'=> $info['menu_sync'],
                'is_active'        => $integration?->is_active ?? false,
                'is_configured'    => $integration && $integration->api_credentials,
                'commission_rate'  => $integration?->commission_rate ?? $info['commission_default'],
                'orders_today'     => ExternalOrder::where('platform', $platform)
                                        ->where('tenant_id', $tenant->id)
                                        ->whereDate('received_at', today())
                                        ->count(),
                'last_order_at'    => $integration?->last_order_at,
                'webhook_url'      => url("/api/sahab/delivery/webhook/{$platform}/{$tenant->code}"),
            ];
        });

        return response()->json($result);
    }

    /**
     * تفعيل/تكوين منصّة
     */
    public function configure(Request $request, string $platform)
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate([
            'is_active'         => 'required|boolean',
            'credentials'       => 'nullable|array',
            'commission_rate'   => 'nullable|numeric|min:0|max:100',
            'default_prep_time' => 'nullable|integer|min:5|max:120',
            'auto_accept'       => 'nullable|boolean',
        ]);

        $tenant = auth()->user()->tenant;

        $data = [
            'is_active'         => $validated['is_active'],
            'commission_rate'   => $validated['commission_rate'] ?? 0,
            'default_prep_time' => $validated['default_prep_time'] ?? 25,
            'auto_accept'       => $validated['auto_accept'] ?? false,
        ];

        if (!empty($validated['credentials'])) {
            $data['api_credentials'] = encrypt(json_encode($validated['credentials']));
        }

        $integration = DeliveryIntegration::updateOrCreate(
            ['tenant_id' => $tenant->id, 'platform' => $platform],
            $data,
        );

        return response()->json([
            'success'     => true,
            'integration' => $integration,
            'webhook_url' => url("/api/sahab/delivery/webhook/{$platform}/{$tenant->code}"),
        ]);
    }

    /**
     * Webhook من المنصّة الخارجيّة (لا يحتاج auth — توقيع HMAC)
     * /api/sahab/delivery/webhook/{platform}/{tenant_code}
     */
    public function webhook(Request $request, string $platform, string $tenantCode)
    {
        $start = microtime(true);

        $tenant = Tenant::where('code', $tenantCode)->first();
        if (!$tenant) {
            return response()->json(['error' => 'tenant_not_found'], 404);
        }

        // التحقّق من التوقيع
        if (!$this->aggregator->verifyWebhookSignature($platform, $tenant, $request)) {
            \App\Models\Sahab\WebhookLog::create([
                'tenant_id'   => $tenant->id,
                'source'      => $platform,
                'event_type'  => 'invalid_signature',
                'raw_body'    => $request->getContent(),
                'response_code' => 401,
                'error'       => 'Invalid signature',
            ]);
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        // معالجة الطلب
        try {
            $result = $this->aggregator->handleWebhook($platform, $tenant, $request->all());

            \App\Models\Sahab\WebhookLog::create([
                'tenant_id'         => $tenant->id,
                'source'            => $platform,
                'event_type'        => $result['event'] ?? null,
                'raw_body'          => $request->getContent(),
                'response_code'     => 200,
                'processing_time_ms'=> (int) ((microtime(true) - $start) * 1000),
            ]);

            return response()->json(['ok' => true, ...$result]);
        } catch (\Exception $e) {
            Log::error("Webhook error from {$platform}", ['error' => $e->getMessage()]);

            \App\Models\Sahab\WebhookLog::create([
                'tenant_id'   => $tenant->id,
                'source'      => $platform,
                'raw_body'    => $request->getContent(),
                'response_code' => 500,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['error' => 'processing_failed'], 500);
        }
    }

    /**
     * قبول طلب وارد
     */
    public function acceptOrder(Request $request, ExternalOrder $order)
    {
        $this->middleware('auth:sanctum');
        $this->authorize('update', $order);

        $validated = $request->validate([
            'prep_time_minutes' => 'required|integer|min:5|max:120',
        ]);

        return DB::transaction(function () use ($validated, $order) {
            // 1) إخطار المنصّة
            $result = $this->aggregator->acceptOrder($order, $validated['prep_time_minutes']);

            if (!$result['success']) {
                return response()->json([
                    'error' => 'فشل قبول الطلب من المنصّة',
                    'details' => $result['error'] ?? null,
                ], 502);
            }

            // 2) إنشاء فاتورة سحاب من الطلب الخارجي
            $invoice = $this->aggregator->createInvoiceFromExternalOrder($order);

            $order->update([
                'status'            => 'accepted',
                'invoice_id'        => $invoice->id,
                'prep_time_minutes' => $validated['prep_time_minutes'],
                'accepted_at'       => now(),
            ]);

            return response()->json([
                'success'    => true,
                'invoice'    => $invoice,
                'prep_time'  => $validated['prep_time_minutes'],
            ]);
        });
    }

    public function rejectOrder(Request $request, ExternalOrder $order)
    {
        $this->middleware('auth:sanctum');
        $this->authorize('update', $order);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $this->aggregator->rejectOrder($order, $validated['reason']);

        $order->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['reason'],
        ]);

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, ExternalOrder $order)
    {
        $this->middleware('auth:sanctum');
        $this->authorize('update', $order);

        $validated = $request->validate([
            'status' => 'required|in:cooking,ready,picked_up,delivered,cancelled',
        ]);

        $this->aggregator->updateStatus($order, $validated['status']);

        $order->update(['status' => $validated['status']]);

        return response()->json(['success' => true]);
    }

    public function syncMenu(Request $request)
    {
        $this->middleware('auth:sanctum');

        $tenant = auth()->user()->tenant;
        $integrations = DeliveryIntegration::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $results = [];
        foreach ($integrations as $integration) {
            $info = $this->aggregator->getPlatformInfo($integration->platform);
            if (!$info['menu_sync']) continue;

            $result = $this->aggregator->syncMenu($tenant, $integration->platform);
            $results[$integration->platform] = $result;

            $integration->update(['last_menu_sync_at' => now()]);
        }

        return response()->json($results);
    }
}
