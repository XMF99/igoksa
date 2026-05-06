<?php

namespace App\Models\Sahab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ====================================================================
//  OtpCode — رمز التحقّق OTP
// ====================================================================
class OtpCode extends Model
{
    protected $table = 'sahab_otp_codes';
    protected $fillable = [
        'mobile', 'code', 'purpose', 'user_id', 'tenant_id',
        'ip_address', 'user_agent', 'expires_at', 'verified_at', 'attempts',
    ];
    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}

// ====================================================================
//  SalesTarget — المبيعات المستهدفة والعمولات
// ====================================================================
class SalesTarget extends Model
{
    use TenantScoped;
    protected $table = 'sahab_sales_targets';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'employee_id', 'name',
        'period', 'start_date', 'end_date',
        'target_amount', 'achieved_amount',
        'target_count', 'achieved_count',
        'commission_type', 'commission_rate', 'commission_amount',
        'commission_tiers', 'only_above_target', 'status', 'notes',
    ];
    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'commission_tiers'  => 'array',
        'only_above_target' => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }

    public function progressPercent(): float
    {
        if ($this->target_amount <= 0) return 0;
        return min(100, ($this->achieved_amount / $this->target_amount) * 100);
    }

    public function calculateCommission(): float
    {
        $base = $this->only_above_target
            ? max(0, $this->achieved_amount - $this->target_amount)
            : $this->achieved_amount;

        return match($this->commission_type) {
            'percentage' => $base * ($this->commission_rate / 100),
            'fixed'      => $this->achieved_amount >= $this->target_amount ? $this->commission_rate : 0,
            'tiered'     => $this->calculateTieredCommission($base),
            default      => 0,
        };
    }

    protected function calculateTieredCommission(float $base): float
    {
        $tiers = $this->commission_tiers ?? [];
        $progress = $this->progressPercent();
        foreach ($tiers as $tier) {
            if ($progress >= ($tier['from_percent'] ?? 0) && $progress <= ($tier['to_percent'] ?? 999)) {
                return $base * (($tier['rate'] ?? 0) / 100);
            }
        }
        return 0;
    }
}

// ====================================================================
//  Installment — الأقساط
// ====================================================================
class Installment extends Model
{
    use TenantScoped;
    protected $table = 'sahab_installments';
    protected $fillable = [
        'tenant_id', 'invoice_id', 'customer_id', 'plan_number',
        'total_amount', 'down_payment', 'financed_amount', 'admin_fee',
        'total_installments', 'paid_installments', 'installment_amount',
        'start_date', 'frequency', 'status', 'next_due_date',
        'overdue_count', 'notes',
    ];
    protected $casts = [
        'start_date'    => 'date',
        'next_due_date' => 'date',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function payments(): HasMany { return $this->hasMany(InstallmentPayment::class, 'installment_id'); }

    public function remainingAmount(): float
    {
        return $this->financed_amount - $this->payments()->sum('amount_paid');
    }

    public function progressPercent(): float
    {
        return $this->total_installments > 0
            ? ($this->paid_installments / $this->total_installments) * 100
            : 0;
    }
}

class InstallmentPayment extends Model
{
    use TenantScoped;
    protected $table = 'sahab_installment_payments';
    protected $fillable = [
        'tenant_id', 'installment_id', 'installment_number',
        'due_date', 'paid_date', 'amount_due', 'amount_paid',
        'late_fee', 'status', 'payment_method', 'reference',
    ];
    protected $casts = [
        'due_date'  => 'date',
        'paid_date' => 'date',
    ];

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }
}

// ====================================================================
//  MembershipPlan & Membership — الاشتراكات والعضويّات
// ====================================================================
class MembershipPlan extends Model
{
    use TenantScoped;
    protected $table = 'sahab_membership_plans';
    protected $fillable = [
        'tenant_id', 'name', 'description', 'price',
        'billing_cycle', 'duration_days',
        'benefits', 'max_visits_per_period', 'included_services',
        'discount_percent', 'is_active', 'auto_renew',
    ];
    protected $casts = [
        'benefits' => 'array',
        'included_services' => 'array',
        'is_active'  => 'boolean',
        'auto_renew' => 'boolean',
    ];

    public function memberships(): HasMany { return $this->hasMany(Membership::class, 'plan_id'); }
}

class Membership extends Model
{
    use TenantScoped;
    protected $table = 'sahab_memberships';
    protected $fillable = [
        'tenant_id', 'customer_id', 'plan_id', 'membership_number',
        'start_date', 'end_date', 'renewed_until',
        'status', 'visits_used', 'total_paid',
        'auto_renew', 'next_billing_date', 'notes',
    ];
    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'renewed_until'     => 'date',
        'next_billing_date' => 'date',
        'auto_renew'        => 'boolean',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function plan(): BelongsTo { return $this->belongsTo(MembershipPlan::class, 'plan_id'); }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->end_date->isFuture();
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->end_date, false));
    }
}

// ====================================================================
//  WorkOrder — أوامر الشغل
// ====================================================================
class WorkOrder extends Model
{
    use TenantScoped, SoftDeletes;
    protected $table = 'sahab_work_orders';
    protected $fillable = [
        'tenant_id', 'customer_id', 'assigned_to',
        'order_number', 'title', 'description', 'type', 'priority', 'status',
        'scheduled_at', 'started_at', 'completed_at',
        'estimated_hours', 'actual_hours',
        'estimated_cost', 'actual_cost', 'quoted_price',
        'invoice_id', 'attachments', 'completion_notes',
        'customer_rating', 'customer_feedback',
    ];
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'attachments'  => 'array',
    ];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(Employee::class, 'assigned_to'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function items(): HasMany { return $this->hasMany(WorkOrderItem::class, 'work_order_id'); }
    public function timeEntries(): HasMany { return $this->hasMany(TimeEntry::class, 'work_order_id'); }
}

class WorkOrderItem extends Model
{
    use TenantScoped;
    protected $table = 'sahab_work_order_items';
    protected $fillable = [
        'tenant_id', 'work_order_id', 'product_id', 'description',
        'type', 'quantity', 'unit', 'unit_cost', 'unit_price', 'total', 'is_billable',
    ];
    protected $casts = ['is_billable' => 'boolean'];
}

// ====================================================================
//  Booking & BookableResource — الحجوزات
// ====================================================================
class BookableResource extends Model
{
    use TenantScoped;
    protected $table = 'sahab_bookable_resources';
    protected $fillable = [
        'tenant_id', 'name', 'type', 'description', 'capacity',
        'hourly_rate', 'daily_rate', 'booking_rate',
        'working_hours', 'availability_rules',
        'requires_approval', 'is_active',
    ];
    protected $casts = [
        'working_hours'      => 'array',
        'availability_rules' => 'array',
        'requires_approval'  => 'boolean',
        'is_active'          => 'boolean',
    ];
}

class Booking extends Model
{
    use TenantScoped;
    protected $table = 'sahab_bookings';
    protected $fillable = [
        'tenant_id', 'resource_id', 'customer_id', 'booking_number',
        'start_at', 'end_at', 'duration_minutes', 'guest_count',
        'status', 'total_amount', 'deposit_paid', 'payment_status',
        'invoice_id', 'special_requests', 'notes', 'reminders_sent',
    ];
    protected $casts = [
        'start_at'        => 'datetime',
        'end_at'          => 'datetime',
        'reminders_sent'  => 'array',
    ];

    public function resource(): BelongsTo { return $this->belongsTo(BookableResource::class, 'resource_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

// ====================================================================
//  TimeEntry — تتبّع الوقت
// ====================================================================
class TimeEntry extends Model
{
    use TenantScoped;
    protected $table = 'sahab_time_entries';
    protected $fillable = [
        'tenant_id', 'employee_id', 'work_order_id', 'customer_id',
        'date', 'start_time', 'end_time', 'duration_hours',
        'task_description', 'is_billable', 'hourly_rate', 'total_amount',
        'status', 'invoice_id',
    ];
    protected $casts = [
        'date'        => 'date',
        'is_billable' => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}

// ====================================================================
//  RentalUnit & RentalContract — الإيجارات
// ====================================================================
class RentalUnit extends Model
{
    use TenantScoped;
    protected $table = 'sahab_rental_units';
    protected $fillable = [
        'tenant_id', 'unit_number', 'type', 'building', 'floor',
        'area_sqm', 'rooms', 'default_rent', 'status',
        'amenities', 'photos', 'notes',
    ];
    protected $casts = [
        'amenities' => 'array',
        'photos'    => 'array',
    ];
}

class RentalContract extends Model
{
    use TenantScoped;
    protected $table = 'sahab_rental_contracts';
    protected $fillable = [
        'tenant_id', 'unit_id', 'customer_id', 'contract_number',
        'start_date', 'end_date', 'monthly_rent', 'security_deposit',
        'admin_fee', 'billing_frequency', 'status', 'terminated_at',
        'terms', 'attachments',
    ];
    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'terminated_at' => 'date',
        'attachments'   => 'array',
    ];

    public function unit(): BelongsTo { return $this->belongsTo(RentalUnit::class, 'unit_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

// ====================================================================
//  Department & Position — الهيكل التنظيمي
// ====================================================================
class Department extends Model
{
    use TenantScoped;
    protected $table = 'sahab_departments';
    protected $fillable = [
        'tenant_id', 'parent_id', 'name', 'code', 'manager_id',
        'description', 'budget', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(Department::class, 'parent_id'); }
    public function manager(): BelongsTo { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function positions(): HasMany { return $this->hasMany(Position::class); }
}

class Position extends Model
{
    use TenantScoped;
    protected $table = 'sahab_positions';
    protected $fillable = [
        'tenant_id', 'department_id', 'title', 'code',
        'min_salary', 'max_salary', 'required_skills',
        'responsibilities', 'is_active',
    ];
    protected $casts = [
        'required_skills'  => 'array',
        'responsibilities' => 'array',
        'is_active'        => 'boolean',
    ];

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
}

// ====================================================================
//  StockPermit — الأذون المخزنية
// ====================================================================
class StockPermit extends Model
{
    use TenantScoped;
    protected $table = 'sahab_stock_permits';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'permit_number', 'type',
        'permit_date', 'source_outlet_id', 'destination_outlet_id',
        'employee_id', 'reference_type', 'reference_id',
        'status', 'approved_by', 'approved_at', 'reason', 'notes',
    ];
    protected $casts = [
        'permit_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function items(): HasMany { return $this->hasMany(StockPermitItem::class, 'permit_id'); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}

class StockPermitItem extends Model
{
    use TenantScoped;
    protected $table = 'sahab_stock_permit_items';
    protected $fillable = [
        'tenant_id', 'permit_id', 'product_id', 'quantity', 'unit',
        'unit_cost', 'total_cost', 'batch_number', 'expiry_date', 'notes',
    ];
    protected $casts = ['expiry_date' => 'date'];
}

// ====================================================================
//  WhatsappTemplate — قوالب الواتساب
// ====================================================================
class WhatsappTemplate extends Model
{
    protected $table = 'sahab_whatsapp_templates';
    protected $fillable = [
        'key', 'name', 'content_ar', 'content_en', 'variables', 'is_active',
    ];
    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * استبدال المتغيّرات في القالب
     */
    public function render(array $data, string $lang = 'ar'): string
    {
        $content = $lang === 'en' && $this->content_en ? $this->content_en : $this->content_ar;

        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }
}
