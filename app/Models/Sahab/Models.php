<?php

namespace App\Models\Sahab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * ============================================================
 *  TenantScoped Trait — يفلتر الاستعلامات تلقائياً بـ tenant_id
 * ============================================================
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        // التطبيق التلقائي
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && $tenantId = auth()->user()->tenant_id ?? null) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // عند الإنشاء، اضف tenant_id تلقائياً
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id ?? null;
            }
        });
    }
}

// ====================================================================
//  Plan
// ====================================================================
class Plan extends Model
{
    protected $table = 'sahab_plans';
    protected $fillable = [
        'slug', 'name_ar', 'name_en', 'description_ar',
        'price_monthly', 'price_annual',
        'max_outlets', 'max_users', 'max_products', 'max_invoices_per_month',
        'ai_quota_monthly', 'ocr_quota_monthly',
        'features', 'is_popular', 'is_active', 'display_order',
    ];
    protected $casts = ['features' => 'array', 'is_popular' => 'boolean', 'is_active' => 'boolean'];

    public function tenants(): HasMany { return $this->hasMany(Tenant::class); }

    public function hasFeature(string $f): bool
    {
        return in_array($f, $this->features ?? []) || in_array('all', $this->features ?? []);
    }
}

// ====================================================================
//  Outlet
// ====================================================================
class Outlet extends Model
{
    use TenantScoped;
    protected $table = 'sahab_outlets';
    protected $fillable = [
        'tenant_id', 'code', 'name', 'manager_name', 'mobile',
        'address', 'latitude', 'longitude',
        'opens_at', 'closes_at', 'working_days',
        'geofence_radius_meters', 'is_active',
    ];
    protected $casts = ['working_days' => 'array', 'is_active' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function terminals(): HasMany { return $this->hasMany(PosTerminal::class); }
    public function shifts(): HasMany { return $this->hasMany(Shift::class); }
}

// ====================================================================
//  PosTerminal
// ====================================================================
class PosTerminal extends Model
{
    use TenantScoped;
    protected $table = 'sahab_pos_terminals';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'code', 'name', 'device_id', 'platform',
        'printer_ip', 'printer_port', 'printer_size',
        'cash_drawer_enabled', 'settings', 'last_active_at', 'is_active',
    ];
    protected $casts = ['settings' => 'array', 'last_active_at' => 'datetime', 'is_active' => 'boolean'];
}

// ====================================================================
//  ZatcaCredential
// ====================================================================
class ZatcaCredential extends Model
{
    protected $table = 'sahab_zatca_credentials';
    protected $fillable = [
        'tenant_id', 'vat_number', 'cr_number', 'phase',
        'csr_pem', 'private_key_pem', 'compliance_csid',
        'production_csid', 'compliance_request_id',
        'last_invoice_counter', 'last_invoice_hash',
        'is_active', 'last_synced_at',
    ];
    protected $casts = ['is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    protected $hidden = ['private_key_pem', 'csr_pem', 'compliance_csid', 'production_csid'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}

// ====================================================================
//  Setting
// ====================================================================
class Setting extends Model
{
    use TenantScoped;
    protected $table = 'sahab_settings';
    protected $fillable = ['tenant_id', 'key', 'value', 'group'];
}

// ====================================================================
//  Employee
// ====================================================================
class Employee extends Model
{
    use SoftDeletes, TenantScoped;
    protected $table = 'sahab_employees';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'user_id', 'employee_code',
        'full_name', 'full_name_en', 'national_id', 'mobile', 'email',
        'birth_date', 'gender', 'nationality',
        'position', 'department', 'hire_date', 'contract_end_date', 'contract_type',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances',
        'bank_name', 'iban', 'photo', 'signature',
        'shift_start', 'shift_end', 'gps_required',
        'status', 'termination_date',
    ];
    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
        'contract_end_date' => 'date',
        'termination_date' => 'date',
        'gps_required' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function documents(): HasMany { return $this->hasMany(EmployeeDocument::class); }
    public function attendance(): HasMany { return $this->hasMany(Attendance::class); }
    public function leaves(): HasMany { return $this->hasMany(Leave::class); }
    public function payrolls(): HasMany { return $this->hasMany(Payroll::class); }

    /**
     * عدد الأيّام المتبقّية على انتهاء عقده
     */
    public function contractDaysLeft(): ?int
    {
        return $this->contract_end_date
            ? max(0, (int) now()->diffInDays($this->contract_end_date, false))
            : null;
    }

    /**
     * الراتب الإجمالي
     */
    public function getTotalSalaryAttribute(): float
    {
        return (float) ($this->basic_salary
            + $this->housing_allowance
            + $this->transport_allowance
            + $this->other_allowances);
    }
}

// ====================================================================
//  EmployeeDocument
// ====================================================================
class EmployeeDocument extends Model
{
    use TenantScoped;
    protected $table = 'sahab_employee_documents';
    protected $fillable = [
        'tenant_id', 'employee_id', 'document_type',
        'document_number', 'issue_date', 'expiry_date',
        'issuing_authority', 'file_path',
        'alert_60_days', 'alert_30_days', 'alert_7_days',
        'last_alert_sent_at',
        'approval_status', 'rejection_reason', 'approved_by',
        'notes',
    ];
    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'last_alert_sent_at' => 'datetime',
        'alert_60_days' => 'boolean',
        'alert_30_days' => 'boolean',
        'alert_7_days' => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date
            ? (int) now()->diffInDays($this->expiry_date, false)
            : null;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        $left = $this->daysUntilExpiry();
        return $left !== null && $left >= 0 && $left <= $days;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}

// ====================================================================
//  BusinessDocument (وثائق المحلّ)
// ====================================================================
class BusinessDocument extends Model
{
    use TenantScoped;
    protected $table = 'sahab_business_documents';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'document_type',
        'document_number', 'issue_date', 'expiry_date',
        'issuing_authority', 'file_path', 'renewal_cost',
        'alert_60_days', 'alert_30_days', 'alert_7_days',
        'last_alert_sent_at', 'notes',
    ];
    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'last_alert_sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }

    public function daysUntilExpiry(): ?int
    {
        return $this->expiry_date
            ? (int) now()->diffInDays($this->expiry_date, false)
            : null;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        $left = $this->daysUntilExpiry();
        return $left !== null && $left >= 0 && $left <= $days;
    }
}

// ====================================================================
//  Attendance
// ====================================================================
class Attendance extends Model
{
    use TenantScoped;
    protected $table = 'sahab_attendance';
    protected $fillable = [
        'tenant_id', 'employee_id', 'date',
        'check_in_time', 'check_out_time', 'break_start', 'break_end',
        'check_in_lat', 'check_in_lng', 'check_in_distance_meters',
        'check_out_lat', 'check_out_lng',
        'status', 'late_minutes', 'worked_hours', 'overtime_hours',
        'surprise_test_required', 'surprise_test_passed', 'surprise_test_at',
        'check_in_method', 'notes',
    ];
    protected $casts = [
        'date' => 'date',
        'surprise_test_at' => 'datetime',
        'surprise_test_required' => 'boolean',
        'surprise_test_passed' => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}

// ====================================================================
//  Leave
// ====================================================================
class Leave extends Model
{
    use TenantScoped;
    protected $table = 'sahab_leaves';
    protected $fillable = [
        'tenant_id', 'employee_id', 'leave_type',
        'start_date', 'end_date', 'days_count', 'reason', 'attachment',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}

// ====================================================================
//  Payroll
// ====================================================================
class Payroll extends Model
{
    use TenantScoped;
    protected $table = 'sahab_payrolls';
    protected $fillable = [
        'tenant_id', 'employee_id', 'year', 'month',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowances',
        'overtime_amount', 'bonus', 'commission', 'total_earnings',
        'absence_deduction', 'late_deduction', 'loan_deduction',
        'insurance_deduction', 'gosi_deduction', 'other_deductions', 'total_deductions',
        'net_salary',
        'working_days', 'present_days', 'absent_days', 'late_days', 'overtime_hours',
        'status', 'payment_date', 'payment_reference',
    ];
    protected $casts = ['payment_date' => 'date'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}

// ====================================================================
//  Account (دليل الحسابات)
// ====================================================================
class Account extends Model
{
    use TenantScoped;
    protected $table = 'sahab_accounts';
    protected $fillable = [
        'tenant_id', 'code', 'name_ar', 'name_en', 'type', 'subtype',
        'parent_id', 'level', 'path',
        'opening_balance', 'opening_balance_type',
        'allow_transactions', 'is_system', 'is_active', 'description',
    ];
    protected $casts = ['allow_transactions' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
}

// ====================================================================
//  JournalEntry + JournalLine
// ====================================================================
class JournalEntry extends Model
{
    use TenantScoped;
    protected $table = 'sahab_journal_entries';
    protected $fillable = [
        'tenant_id', 'entry_number', 'entry_date', 'type',
        'reference_type', 'reference_id', 'description',
        'total_debit', 'total_credit', 'status',
        'created_by', 'posted_by', 'posted_at',
    ];
    protected $casts = ['entry_date' => 'date', 'posted_at' => 'datetime'];

    public function lines(): HasMany { return $this->hasMany(JournalLine::class, 'entry_id'); }
}

class JournalLine extends Model
{
    use TenantScoped;
    protected $table = 'sahab_journal_lines';
    protected $fillable = [
        'tenant_id', 'entry_id', 'account_id', 'cost_center_id',
        'debit', 'credit', 'description', 'line_order',
    ];
    public function entry(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}

// ====================================================================
//  Check (الشيكات)
// ====================================================================
class Check extends Model
{
    use TenantScoped;
    protected $table = 'sahab_checks';
    protected $fillable = [
        'tenant_id', 'direction', 'check_number', 'bank_name',
        'account_number', 'check_date', 'due_date',
        'amount', 'payee_name', 'issuer_name', 'status',
        'cleared_date', 'bounced_date', 'bounce_reason',
        'attachment', 'notes', 'account_id',
        'reference_type', 'reference_id',
    ];
    protected $casts = [
        'check_date' => 'date',
        'due_date' => 'date',
        'cleared_date' => 'date',
        'bounced_date' => 'date',
    ];
}

// ====================================================================
//  Asset (الأصول الثابتة)
// ====================================================================
class Asset extends Model
{
    use SoftDeletes, TenantScoped;
    protected $table = 'sahab_assets';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'cost_center_id', 'code', 'name',
        'serial_number', 'barcode', 'category',
        'purchase_cost', 'purchase_date', 'salvage_value', 'useful_life_years',
        'depreciation_method', 'accumulated_depreciation', 'current_value',
        'assigned_to_employee_id', 'assigned_date',
        'status', 'disposal_date', 'disposal_amount',
        'photo', 'warranty_until', 'notes',
    ];
    protected $casts = [
        'purchase_date' => 'date',
        'assigned_date' => 'date',
        'disposal_date' => 'date',
    ];

    /**
     * حساب الإهلاك الشهري بالطريقة المباشرة
     */
    public function monthlyDepreciation(): float
    {
        if ($this->depreciation_method !== 'straight_line') return 0;
        $months = $this->useful_life_years * 12;
        return $months > 0 ? ($this->purchase_cost - $this->salvage_value) / $months : 0;
    }
}

// ====================================================================
//  Category, Product
// ====================================================================
class Category extends Model
{
    use TenantScoped;
    protected $table = 'sahab_categories';
    protected $fillable = ['tenant_id', 'parent_id', 'name', 'name_en', 'icon', 'color', 'sort_order', 'is_active'];
    public function products(): HasMany { return $this->hasMany(Product::class); }
}

class Product extends Model
{
    use SoftDeletes, TenantScoped;
    protected $table = 'sahab_products';
    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'name_en', 'sku', 'barcode',
        'description', 'cost_price', 'sale_price', 'discount_percentage',
        'unit', 'min_quantity', 'vat_included', 'vat_rate',
        'track_inventory', 'current_stock', 'low_stock_threshold',
        'has_modifiers', 'is_combo', 'preparation_time_minutes',
        'allergens', 'nutritional_info',
        'available_in_delivery_apps', 'external_ids',
        'image', 'gallery',
        'is_active', 'is_featured', 'sort_order',
    ];
    protected $casts = [
        'allergens' => 'array',
        'nutritional_info' => 'array',
        'external_ids' => 'array',
        'gallery' => 'array',
        'vat_included' => 'boolean',
        'track_inventory' => 'boolean',
        'has_modifiers' => 'boolean',
        'is_combo' => 'boolean',
        'available_in_delivery_apps' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->current_stock <= $this->low_stock_threshold;
    }
}

// ====================================================================
//  Customer
// ====================================================================
class Customer extends Model
{
    use SoftDeletes, TenantScoped;
    protected $table = 'sahab_customers';
    protected $fillable = [
        'tenant_id', 'code', 'name', 'mobile', 'email',
        'vat_number', 'cr_number',
        'address', 'city', 'latitude', 'longitude', 'customer_type',
        'credit_limit', 'current_balance', 'payment_terms_days',
        'loyalty_points', 'loyalty_tier', 'total_spent', 'total_orders',
        'last_order_date', 'birth_date',
        'is_active', 'notes',
    ];
    protected $casts = [
        'last_order_date' => 'date',
        'birth_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
    public function loyaltyTransactions(): HasMany { return $this->hasMany(LoyaltyTransaction::class); }
}

// ====================================================================
//  Supplier
// ====================================================================
class Supplier extends Model
{
    use TenantScoped;
    protected $table = 'sahab_suppliers';
    protected $fillable = [
        'tenant_id', 'code', 'name', 'contact_person',
        'mobile', 'email', 'vat_number', 'cr_number', 'address',
        'current_balance', 'payment_terms_days', 'is_active', 'notes',
    ];
}

// ====================================================================
//  Shift
// ====================================================================
class Shift extends Model
{
    use TenantScoped;
    protected $table = 'sahab_shifts';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'terminal_id', 'cashier_id',
        'opened_at', 'closed_at',
        'opening_cash', 'closing_cash', 'expected_cash', 'cash_variance',
        'invoice_count', 'total_sales', 'total_vat', 'total_discount',
        'total_refunds', 'payment_breakdown',
        'status', 'closing_notes',
    ];
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'payment_breakdown' => 'array',
    ];

    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
}

// ====================================================================
//  Invoice
// ====================================================================
class Invoice extends Model
{
    use SoftDeletes, TenantScoped;
    protected $table = 'sahab_invoices';
    protected $fillable = [
        'tenant_id', 'outlet_id', 'terminal_id', 'shift_id',
        'cashier_id', 'customer_id', 'invoice_number', 'invoice_date',
        'source', 'source_order_id', 'order_type',
        'table_number', 'guests_count',
        'subtotal', 'discount_amount', 'vat_amount', 'service_charge',
        'delivery_fee', 'total_amount', 'paid_amount', 'balance_due',
        'status', 'payment_status',
        'zatca_qr_code', 'zatca_uuid', 'zatca_hash',
        'zatca_submitted', 'zatca_submitted_at',
        'notes',
    ];
    protected $casts = [
        'invoice_date' => 'datetime',
        'zatca_submitted' => 'boolean',
        'zatca_submitted_at' => 'datetime',
    ];

    public function items(): HasMany { return $this->hasMany(InvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function shift(): BelongsTo { return $this->belongsTo(Shift::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
}

class InvoiceItem extends Model
{
    protected $table = 'sahab_invoice_items';
    protected $fillable = [
        'invoice_id', 'product_id', 'product_name', 'product_sku',
        'quantity', 'unit_price', 'discount_amount', 'vat_amount',
        'subtotal', 'total', 'modifiers', 'special_instructions',
    ];
    protected $casts = ['modifiers' => 'array'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}

class Payment extends Model
{
    use TenantScoped;
    protected $table = 'sahab_payments';
    protected $fillable = [
        'tenant_id', 'invoice_id', 'customer_id', 'shift_id',
        'method', 'amount', 'reference', 'transaction_id',
        'metadata', 'status', 'paid_at',
    ];
    protected $casts = ['metadata' => 'array', 'paid_at' => 'datetime'];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}

// ====================================================================
//  DeliveryIntegration + ExternalOrder
// ====================================================================
class DeliveryIntegration extends Model
{
    use TenantScoped;
    protected $table = 'sahab_delivery_integrations';
    protected $fillable = [
        'tenant_id', 'platform', 'is_active', 'api_credentials',
        'commission_rate', 'default_prep_time', 'auto_accept',
        'last_order_at', 'last_menu_sync_at', 'total_orders',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'auto_accept' => 'boolean',
        'last_order_at' => 'datetime',
        'last_menu_sync_at' => 'datetime',
    ];
    protected $hidden = ['api_credentials'];

    public function getDecryptedCredentialsAttribute(): array
    {
        if (!$this->api_credentials) return [];
        try {
            return json_decode(decrypt($this->api_credentials), true) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}

class ExternalOrder extends Model
{
    use TenantScoped;
    protected $table = 'sahab_external_orders';
    protected $fillable = [
        'tenant_id', 'platform', 'external_id', 'invoice_id',
        'customer_name', 'customer_phone', 'delivery_address',
        'delivery_lat', 'delivery_lng', 'items_json',
        'subtotal', 'delivery_fee', 'platform_commission',
        'total_amount', 'payment_method', 'is_paid',
        'status', 'prep_time_minutes', 'rejection_reason',
        'received_at', 'accepted_at', 'delivered_at',
    ];
    protected $casts = [
        'items_json' => 'array',
        'is_paid' => 'boolean',
        'received_at' => 'datetime',
        'accepted_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}

// ====================================================================
//  LoyaltySettings + LoyaltyTransaction
// ====================================================================
class LoyaltySettings extends Model
{
    protected $table = 'sahab_loyalty_settings';
    protected $fillable = [
        'tenant_id', 'is_active',
        'points_per_riyal', 'riyal_per_point', 'min_redemption_points',
        'silver_threshold', 'gold_threshold', 'platinum_threshold',
        'bronze_multiplier', 'silver_multiplier',
        'gold_multiplier', 'platinum_multiplier',
        'birthday_bonus_points',
    ];
    protected $casts = ['is_active' => 'boolean'];
}

class LoyaltyTransaction extends Model
{
    use TenantScoped;
    protected $table = 'sahab_loyalty_transactions';
    protected $fillable = [
        'tenant_id', 'customer_id', 'invoice_id', 'type',
        'points', 'balance_after', 'value_in_riyal', 'description', 'expires_at',
    ];
    protected $casts = ['expires_at' => 'date'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
}

// ====================================================================
//  AiUsage + MessagingLog + SuspiciousActivity
// ====================================================================
class AiUsage extends Model
{
    use TenantScoped;
    protected $table = 'sahab_ai_usage';
    protected $fillable = [
        'tenant_id', 'user_id', 'feature', 'model',
        'tokens_in', 'tokens_out', 'cost_usd', 'latency_ms',
        'reference_type', 'reference_id',
    ];
}

class MessagingLog extends Model
{
    use TenantScoped;
    protected $table = 'sahab_messaging_log';
    protected $fillable = [
        'tenant_id', 'channel', 'provider', 'recipient',
        'template', 'subject', 'body_preview', 'external_id',
        'status', 'error', 'cost_sar',
    ];
}

class SuspiciousActivity extends Model
{
    use TenantScoped;
    protected $table = 'sahab_suspicious_activities';
    protected $fillable = [
        'tenant_id', 'user_id', 'shift_id', 'invoice_id',
        'activity_type', 'severity', 'title', 'description', 'evidence',
        'status', 'reviewed_by', 'reviewed_at', 'action_taken',
    ];
    protected $casts = ['evidence' => 'array', 'reviewed_at' => 'datetime'];
}
