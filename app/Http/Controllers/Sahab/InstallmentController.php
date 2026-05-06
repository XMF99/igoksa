<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\Installment;
use App\Models\Sahab\InstallmentPayment;
use App\Models\Sahab\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InstallmentController — إدارة الأقساط (مستوحى من دفترة)
 */
class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('installments')) {
            return $this->upgradePrompt();
        }

        $installments = Installment::where('tenant_id', $tenant->id)
            ->with(['customer:id,name,mobile', 'invoice:id,invoice_number'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // تنبيهات الأقساط المتأخّرة
        $overdueCount = InstallmentPayment::whereHas('installment', fn($q) => $q->where('tenant_id', $tenant->id))
            ->where('status', 'pending')
            ->where('due_date', '<', now())
            ->count();

        $summary = [
            'active'      => Installment::where('tenant_id', $tenant->id)->where('status', 'active')->count(),
            'completed'   => Installment::where('tenant_id', $tenant->id)->where('status', 'completed')->count(),
            'defaulted'   => Installment::where('tenant_id', $tenant->id)->where('status', 'defaulted')->count(),
            'overdue'     => $overdueCount,
            'total_financed' => Installment::where('tenant_id', $tenant->id)->where('status', 'active')->sum('financed_amount'),
        ];

        return view('sahab.installments.index', compact('installments', 'summary'));
    }

    /**
     * إنشاء خطّة أقساط جديدة من فاتورة
     */
    public function store(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('installments')) return $this->upgradePrompt();

        $validated = $request->validate([
            'invoice_id'         => 'required|exists:sahab_invoices,id',
            'down_payment'       => 'nullable|numeric|min:0',
            'admin_fee'          => 'nullable|numeric|min:0',
            'total_installments' => 'required|integer|min:2|max:60',
            'frequency'          => 'required|in:weekly,biweekly,monthly',
            'start_date'         => 'required|date',
        ]);

        $invoice = Invoice::where('tenant_id', $tenant->id)
            ->findOrFail($validated['invoice_id']);

        $financed = $invoice->total_amount - ($validated['down_payment'] ?? 0);
        $installmentAmount = round($financed / $validated['total_installments'], 2);

        return DB::transaction(function () use ($validated, $invoice, $tenant, $financed, $installmentAmount) {
            $installment = Installment::create([
                'tenant_id'         => $tenant->id,
                'invoice_id'        => $invoice->id,
                'customer_id'       => $invoice->customer_id,
                'plan_number'       => 'INS-' . date('Y') . '-' . str_pad(Installment::count() + 1, 5, '0', STR_PAD_LEFT),
                'total_amount'      => $invoice->total_amount,
                'down_payment'      => $validated['down_payment'] ?? 0,
                'financed_amount'   => $financed,
                'admin_fee'         => $validated['admin_fee'] ?? 0,
                'total_installments'=> $validated['total_installments'],
                'installment_amount'=> $installmentAmount,
                'start_date'        => $validated['start_date'],
                'frequency'         => $validated['frequency'],
                'next_due_date'     => $validated['start_date'],
                'status'            => 'active',
            ]);

            // إنشاء جدول الدفعات
            $dueDate = \Carbon\Carbon::parse($validated['start_date']);
            for ($i = 1; $i <= $validated['total_installments']; $i++) {
                InstallmentPayment::create([
                    'tenant_id'          => $tenant->id,
                    'installment_id'     => $installment->id,
                    'installment_number' => $i,
                    'due_date'           => $dueDate->copy(),
                    'amount_due'         => $installmentAmount,
                    'status'             => 'pending',
                ]);

                $dueDate = match($validated['frequency']) {
                    'weekly'   => $dueDate->addWeek(),
                    'biweekly' => $dueDate->addWeeks(2),
                    'monthly'  => $dueDate->addMonth(),
                };
            }

            return response()->json(['success' => true, 'installment' => $installment->load('payments')]);
        });
    }

    /**
     * دفع قسط
     */
    public function payInstallment(Request $request, InstallmentPayment $payment)
    {
        $this->authorizeTenant($request, $payment);

        $validated = $request->validate([
            'amount_paid'    => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference'      => 'nullable|string',
        ]);

        $payment->update([
            'amount_paid'    => $validated['amount_paid'],
            'paid_date'      => now(),
            'payment_method' => $validated['payment_method'],
            'reference'      => $validated['reference'] ?? null,
            'status'         => $validated['amount_paid'] >= $payment->amount_due ? 'paid' : 'partial',
        ]);

        // تحديث الخطّة الأم
        $installment = $payment->installment;
        $installment->increment('paid_installments');

        if ($installment->paid_installments >= $installment->total_installments) {
            $installment->update(['status' => 'completed']);
        } else {
            $next = InstallmentPayment::where('installment_id', $installment->id)
                ->where('status', 'pending')
                ->orderBy('installment_number')
                ->first();
            $installment->update(['next_due_date' => $next?->due_date]);
        }

        return response()->json(['success' => true, 'payment' => $payment]);
    }

    protected function authorizeTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant_id) abort(403);
    }

    protected function upgradePrompt()
    {
        return response()->json([
            'error'       => 'feature_not_available',
            'message'     => 'إدارة الأقساط متاحة في الباقة الاحترافيّة والمؤسّسيّة.',
            'upgrade_url' => '/app/subscription',
        ], 403);
    }
}
