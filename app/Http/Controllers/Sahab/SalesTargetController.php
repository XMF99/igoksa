<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\SalesTarget;
use App\Models\Sahab\Employee;
use App\Models\Sahab\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SalesTargetController — المبيعات المستهدفة والعمولات
 * (مستوحى من دفترة)
 */
class SalesTargetController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('sales_targets')) {
            return $this->upgradePrompt('المبيعات المستهدفة');
        }

        $targets = SalesTarget::where('tenant_id', $tenant->id)
            ->with(['employee:id,full_name', 'outlet:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $summary = [
            'active'    => SalesTarget::where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'achieved'  => SalesTarget::where('tenant_id', $tenant->id)->where('status', 'achieved')->count(),
            'commission_due' => SalesTarget::where('tenant_id', $tenant->id)
                ->where('status', 'achieved')
                ->sum('commission_amount'),
        ];

        return view('sahab.sales_targets.index', compact('targets', 'summary'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:120',
            'employee_id'      => 'nullable|exists:sahab_employees,id',
            'outlet_id'        => 'nullable|exists:sahab_outlets,id',
            'period'           => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'target_amount'    => 'required|numeric|min:0',
            'target_count'     => 'nullable|integer|min:0',
            'commission_type'  => 'required|in:percentage,fixed,tiered',
            'commission_rate'  => 'nullable|numeric|min:0',
            'commission_tiers' => 'nullable|array',
            'only_above_target'=> 'boolean',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $target = SalesTarget::create($validated);

        return response()->json(['success' => true, 'target' => $target]);
    }

    /**
     * تحديث الإنجاز (يستدعى تلقائياً عند إنشاء فاتورة)
     */
    public function updateAchievement(SalesTarget $target)
    {
        $invoices = Invoice::where('tenant_id', $target->tenant_id)
            ->whereBetween('invoice_date', [$target->start_date, $target->end_date])
            ->where('status', '!=', 'cancelled');

        if ($target->employee_id) {
            $invoices->where('cashier_id', $target->employee_id);
        }
        if ($target->outlet_id) {
            $invoices->where('outlet_id', $target->outlet_id);
        }

        $achieved = $invoices->sum('total_amount');
        $count = $invoices->count();

        $target->update([
            'achieved_amount' => $achieved,
            'achieved_count'  => $count,
            'commission_amount' => $target->calculateCommission(),
            'status' => $achieved >= $target->target_amount ? 'achieved' :
                       ($target->end_date < now()->toDateString() ? 'failed' : 'active'),
        ]);

        return $target;
    }

    public function show(Request $request, SalesTarget $target)
    {
        $this->authorizeTenant($request, $target);
        $this->updateAchievement($target);

        return response()->json([
            'target' => $target,
            'progress_percent' => $target->progressPercent(),
            'commission'       => $target->calculateCommission(),
        ]);
    }

    protected function authorizeTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant_id) abort(403);
    }

    protected function upgradePrompt(string $featureName)
    {
        return response()->json([
            'error'       => 'feature_not_available',
            'message'     => "ميزة \"{$featureName}\" متاحة في الباقة الاحترافيّة والمؤسّسيّة.",
            'upgrade_url' => '/app/subscription',
        ], 403);
    }
}
