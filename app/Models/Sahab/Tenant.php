<?php

namespace App\Models\Sahab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * نموذج المستأجر — قلب نظام Multi-Tenant SaaS
 * كل محلّ/شركة عميلة هي tenant واحد.
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'sahab_tenants';

    protected $fillable = [
        'code', 'name', 'name_en', 'owner_name', 'mobile', 'email',
        'cr_number', 'vat_number', 'bldg_no', 'street', 'district',
        'city', 'postal_code', 'country', 'industry',
        'plan_id', 'subscription_status', 'trial_ends_at',
        'subscription_ends_at', 'billing_cycle',
        'api_key', 'api_secret_hash',
        'logo', 'primary_color', 'preferences',
        'last_active_at',
    ];

    protected $casts = [
        'preferences'         => 'array',
        'trial_ends_at'       => 'datetime',
        'subscription_ends_at'=> 'datetime',
        'last_active_at'      => 'datetime',
    ];

    protected $hidden = ['api_secret_hash'];

    // ========================================================
    //  Boot
    // ========================================================
    protected static function booted(): void
    {
        static::creating(function ($tenant) {
            if (empty($tenant->code)) {
                $tenant->code = self::generateUniqueCode($tenant->name);
            }
            if (empty($tenant->trial_ends_at)) {
                $tenant->trial_ends_at = now()->addDays(14);
            }
            if (empty($tenant->api_key)) {
                $tenant->api_key = bin2hex(random_bytes(16));
            }
        });

        static::created(function ($tenant) {
            $tenant->setupDefaults();
        });
    }

    // ========================================================
    //  Relationships
    // ========================================================
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function zatcaCredentials(): HasOne
    {
        return $this->hasOne(ZatcaCredential::class);
    }

    public function loyaltySettings(): HasOne
    {
        return $this->hasOne(LoyaltySettings::class);
    }

    public function deliveryIntegrations(): HasMany
    {
        return $this->hasMany(DeliveryIntegration::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    // ========================================================
    //  Subscription helpers
    // ========================================================
    public function isActive(): bool
    {
        return $this->subscription_status === 'active'
            || ($this->subscription_status === 'trial' && $this->trial_ends_at?->isFuture());
    }

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trial'
            && $this->trial_ends_at?->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (!$this->isOnTrial()) return 0;
        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public function subscriptionDaysLeft(): int
    {
        if (!$this->subscription_ends_at) return 0;
        return max(0, (int) now()->diffInDays($this->subscription_ends_at, false));
    }

    public function canAccessFeature(string $feature): bool
    {
        if (!$this->plan) return false;
        $features = $this->plan->features ?? [];
        return in_array($feature, $features) || in_array('all', $features);
    }

    // ========================================================
    //  Settings helpers
    // ========================================================
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting?->value ?? $default;
    }

    public function setSetting(string $key, $value, string $group = 'general'): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }

    // ========================================================
    //  Setup defaults — يُستدعى عند إنشاء مستأجر جديد
    // ========================================================
    public function setupDefaults(): void
    {
        // إنشاء فرع افتراضي
        if ($this->outlets()->count() === 0) {
            $this->outlets()->create([
                'code' => 'MAIN',
                'name' => 'الفرع الرئيسي',
                'is_active' => true,
            ]);
        }

        // إعدادات الولاء
        if (!$this->loyaltySettings) {
            $this->loyaltySettings()->create([
                'is_active' => false,
                'points_per_riyal' => 1,
                'riyal_per_point' => 0.01,
            ]);
        }

        // دليل الحسابات الافتراضي السعودي
        $this->createDefaultChartOfAccounts();
    }

    protected function createDefaultChartOfAccounts(): void
    {
        $accounts = [
            // الأصول
            ['code' => '1000', 'name_ar' => 'الأصول', 'type' => 'asset', 'allow_transactions' => false],
            ['code' => '1100', 'name_ar' => 'الأصول المتداولة', 'type' => 'asset', 'subtype' => 'current_asset', 'parent' => '1000', 'allow_transactions' => false],
            ['code' => '1110', 'name_ar' => 'النقدية في الصندوق', 'type' => 'asset', 'subtype' => 'current_asset', 'parent' => '1100'],
            ['code' => '1120', 'name_ar' => 'البنوك', 'type' => 'asset', 'subtype' => 'current_asset', 'parent' => '1100'],
            ['code' => '1130', 'name_ar' => 'العملاء', 'type' => 'asset', 'subtype' => 'current_asset', 'parent' => '1100'],
            ['code' => '1140', 'name_ar' => 'المخزون', 'type' => 'asset', 'subtype' => 'current_asset', 'parent' => '1100'],
            ['code' => '1200', 'name_ar' => 'الأصول الثابتة', 'type' => 'asset', 'subtype' => 'fixed_asset', 'parent' => '1000', 'allow_transactions' => false],

            // الخصوم
            ['code' => '2000', 'name_ar' => 'الخصوم', 'type' => 'liability', 'allow_transactions' => false],
            ['code' => '2100', 'name_ar' => 'الموردون', 'type' => 'liability', 'subtype' => 'current_liability', 'parent' => '2000'],
            ['code' => '2200', 'name_ar' => 'ضريبة القيمة المضافة المستحقّة', 'type' => 'liability', 'subtype' => 'current_liability', 'parent' => '2000'],

            // حقوق الملكيّة
            ['code' => '3000', 'name_ar' => 'حقوق الملكيّة', 'type' => 'equity', 'allow_transactions' => false],
            ['code' => '3100', 'name_ar' => 'رأس المال', 'type' => 'equity', 'subtype' => 'equity', 'parent' => '3000'],
            ['code' => '3200', 'name_ar' => 'الأرباح المحتجزة', 'type' => 'equity', 'subtype' => 'equity', 'parent' => '3000'],

            // الإيرادات
            ['code' => '4000', 'name_ar' => 'الإيرادات', 'type' => 'revenue', 'allow_transactions' => false],
            ['code' => '4100', 'name_ar' => 'إيرادات المبيعات', 'type' => 'revenue', 'subtype' => 'operating_revenue', 'parent' => '4000'],
            ['code' => '4200', 'name_ar' => 'إيرادات الخدمات', 'type' => 'revenue', 'subtype' => 'operating_revenue', 'parent' => '4000'],

            // المصروفات
            ['code' => '5000', 'name_ar' => 'المصروفات', 'type' => 'expense', 'allow_transactions' => false],
            ['code' => '5100', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'expense', 'subtype' => 'cogs', 'parent' => '5000'],
            ['code' => '5200', 'name_ar' => 'مصروفات الرواتب', 'type' => 'expense', 'subtype' => 'operating_expense', 'parent' => '5000'],
            ['code' => '5300', 'name_ar' => 'الإيجار', 'type' => 'expense', 'subtype' => 'operating_expense', 'parent' => '5000'],
            ['code' => '5400', 'name_ar' => 'الكهرباء والماء', 'type' => 'expense', 'subtype' => 'operating_expense', 'parent' => '5000'],
            ['code' => '5500', 'name_ar' => 'مصروفات أخرى', 'type' => 'expense', 'subtype' => 'operating_expense', 'parent' => '5000'],
        ];

        $created = [];
        foreach ($accounts as $acc) {
            $parentId = isset($acc['parent']) ? ($created[$acc['parent']] ?? null) : null;
            $row = $this->accounts()->create([
                'code'      => $acc['code'],
                'name_ar'   => $acc['name_ar'],
                'type'      => $acc['type'],
                'subtype'   => $acc['subtype'] ?? null,
                'parent_id' => $parentId,
                'allow_transactions' => $acc['allow_transactions'] ?? true,
                'is_system' => true,
                'is_active' => true,
                'level'     => $parentId ? 2 : 1,
            ]);
            $created[$acc['code']] = $row->id;
        }
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    // ========================================================
    //  Static helpers
    // ========================================================
    public static function generateUniqueCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z\x{0600}-\x{06FF}]/u', '', $name), 0, 4));
        if (strlen($base) < 4) $base = str_pad($base, 4, 'X');

        $attempts = 0;
        do {
            $code = $base . random_int(1000, 9999);
            $attempts++;
        } while (self::where('code', $code)->exists() && $attempts < 10);

        return $code;
    }

    /**
     * الحصول على المستأجر الحالي من الجلسة أو من الـ token
     */
    public static function current(): ?self
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            return self::find(auth()->user()->tenant_id);
        }
        return null;
    }
}
