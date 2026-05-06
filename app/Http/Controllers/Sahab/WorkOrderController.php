<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\WorkOrder;
use App\Models\Sahab\WorkOrderItem;
use App\Models\Sahab\Customer;
use App\Services\Sahab\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WorkOrderController — أوامر الشغل (مستوحى من دفترة)
 *  للحرف، الصيانة، الخدمات، التركيب، التصنيع
 */
class WorkOrderController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('work_orders')) {
            return $this->upgradePrompt();
        }

        $orders = WorkOrder::where('tenant_id', $tenant->id)
            ->with(['customer:id,name,mobile', 'assignedTo:id,full_name'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->priority, fn($q, $p) => $q->where('priority', $p))
            ->orderByDesc('priority')
            ->orderBy('scheduled_at')
            ->paginate(20);

        $summary = [
            'pending'     => WorkOrder::where('tenant_id', $tenant->id)->where('status', 'pending')->count(),
            'in_progress' => WorkOrder::where('tenant_id', $tenant->id)->where('status', 'in_progress')->count(),
            'completed_today' => WorkOrder::where('tenant_id', $tenant->id)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
            'urgent'      => WorkOrder::where('tenant_id', $tenant->id)
                ->where('priority', 'urgent')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
        ];

        return view('sahab.work_orders.index', compact('orders', 'summary'));
    }

    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('work_orders')) return $this->upgradePrompt();

        $validated = $request->validate([
            'customer_id'      => 'nullable|exists:sahab_customers,id',
            'assigned_to'      => 'nullable|exists:sahab_employees,id',
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string',
            'type'             => 'required|in:service,repair,maintenance,installation,consultation,manufacturing,other',
            'priority'         => 'required|in:low,normal,high,urgent',
            'scheduled_at'     => 'nullable|date',
            'estimated_hours'  => 'nullable|integer|min:0',
            'estimated_cost'   => 'nullable|numeric|min:0',
            'quoted_price'     => 'nullable|numeric|min:0',
            'items'            => 'nullable|array',
        ]);

        return DB::transaction(function () use ($validated, $tenant) {
            $order = WorkOrder::create($validated + [
                'tenant_id'    => $tenant->id,
                'order_number' => 'WO-' . date('Y') . '-' . str_pad(WorkOrder::count() + 1, 5, '0', STR_PAD_LEFT),
                'status'       => $validated['scheduled_at'] ? 'scheduled' : 'pending',
            ]);

            // إضافة العناصر
            foreach ($validated['items'] ?? [] as $item) {
                WorkOrderItem::create([
                    'tenant_id'     => $tenant->id,
                    'work_order_id' => $order->id,
                    'product_id'    => $item['product_id'] ?? null,
                    'description'   => $item['description'],
                    'type'          => $item['type'] ?? 'part',
                    'quantity'      => $item['quantity'],
                    'unit'          => $item['unit'] ?? 'piece',
                    'unit_cost'     => $item['unit_cost'] ?? 0,
                    'unit_price'    => $item['unit_price'] ?? 0,
                    'total'         => ($item['unit_price'] ?? 0) * $item['quantity'],
                    'is_billable'   => $item['is_billable'] ?? true,
                ]);
            }

            return response()->json(['success' => true, 'order' => $order->load('items', 'customer')]);
        });
    }

    /**
     * تحديث حالة أمر الشغل + إشعار العميل
     */
    public function updateStatus(Request $request, WorkOrder $order)
    {
        $this->authorizeTenant($request, $order);

        $validated = $request->validate([
            'status' => 'required|in:pending,scheduled,in_progress,on_hold,completed,cancelled,invoiced',
            'completion_notes' => 'nullable|string',
        ]);

        $oldStatus = $order->status;

        $updates = ['status' => $validated['status']];
        if ($validated['status'] === 'in_progress' && !$order->started_at) {
            $updates['started_at'] = now();
        }
        if ($validated['status'] === 'completed') {
            $updates['completed_at'] = now();
            $updates['completion_notes'] = $validated['completion_notes'] ?? null;
        }

        $order->update($updates);

        // إشعار العميل عبر الواتساب
        if ($order->customer && $order->customer->mobile && in_array($validated['status'], ['scheduled', 'in_progress', 'completed'])) {
            $this->whatsapp->sendWorkOrderUpdate($order->customer->mobile, $order, $oldStatus);
        }

        return response()->json(['success' => true, 'order' => $order]);
    }

    protected function authorizeTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant_id) abort(403);
    }

    protected function upgradePrompt()
    {
        return response()->json([
            'error'   => 'feature_not_available',
            'message' => 'أوامر الشغل متاحة في الباقة الاحترافيّة والمؤسّسيّة.',
            'upgrade_url' => '/app/subscription',
        ], 403);
    }
}
