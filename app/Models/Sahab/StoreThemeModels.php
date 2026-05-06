<?php

namespace App\Models\Sahab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ====================================================================
//  StoreTheme — كتالوج الثيمات
// ====================================================================
class StoreTheme extends Model
{
    use SoftDeletes;
    protected $table = 'sahab_store_themes';
    protected $fillable = [
        'slug', 'name_ar', 'name_en', 'description_ar', 'description_en',
        'category', 'pricing_type', 'price', 'subscription_monthly',
        'preview_image', 'thumbnail_image', 'preview_screenshots', 'demo_url',
        'config', 'color_palettes', 'fonts', 'features',
        'supports_dark_mode', 'supports_rtl', 'supports_animations',
        'is_active', 'is_featured', 'is_new', 'display_order',
        'purchase_count', 'rating', 'rating_count',
    ];
    protected $casts = [
        'preview_screenshots'  => 'array',
        'config'               => 'array',
        'color_palettes'       => 'array',
        'fonts'                => 'array',
        'features'             => 'array',
        'supports_dark_mode'   => 'boolean',
        'supports_rtl'         => 'boolean',
        'supports_animations'  => 'boolean',
        'is_active'            => 'boolean',
        'is_featured'          => 'boolean',
        'is_new'               => 'boolean',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(TenantTheme::class, 'theme_id');
    }

    public function isFree(): bool
    {
        return $this->pricing_type === 'free' || $this->price <= 0;
    }

    public function isPurchasedBy(int $tenantId): bool
    {
        return $this->purchases()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('subscription_ends_at')
                  ->orWhere('subscription_ends_at', '>=', now());
            })
            ->exists();
    }
}

// ====================================================================
//  TenantTheme — ثيم اشتراه التاجر
// ====================================================================
class TenantTheme extends Model
{
    protected $table = 'sahab_tenant_themes';
    protected $fillable = [
        'tenant_id', 'theme_id', 'purchase_type', 'amount_paid',
        'transaction_id', 'payment_method',
        'subscription_starts_at', 'subscription_ends_at', 'auto_renew',
        'is_active', 'custom_overrides',
    ];
    protected $casts = [
        'subscription_starts_at' => 'date',
        'subscription_ends_at'   => 'date',
        'auto_renew'             => 'boolean',
        'is_active'              => 'boolean',
        'custom_overrides'       => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function theme(): BelongsTo { return $this->belongsTo(StoreTheme::class, 'theme_id'); }

    public function isExpired(): bool
    {
        if ($this->purchase_type !== 'subscription') return false;
        return $this->subscription_ends_at && $this->subscription_ends_at->isPast();
    }
}

// ====================================================================
//  StoreTransaction — معاملة دفع للمتجر
// ====================================================================
class StoreTransaction extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_transactions';
    protected $fillable = [
        'tenant_id', 'store_id', 'invoice_id',
        'transaction_id', 'gateway_reference',
        'payment_method', 'gateway',
        'amount', 'currency', 'fees', 'net_amount',
        'status', 'customer_name', 'customer_email', 'customer_mobile',
        'gateway_response', 'failure_reason', 'ip_address',
        'paid_at', 'refunded_at',
    ];
    protected $casts = [
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'refunded_at'      => 'datetime',
    ];

    public function store(): BelongsTo { return $this->belongsTo(WhatsappStore::class, 'store_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}

// ====================================================================
//  StoreMenuCategory — فئة منيو
// ====================================================================
class StoreMenuCategory extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_menu_categories';
    protected $fillable = [
        'tenant_id', 'store_id', 'parent_id',
        'name', 'slug', 'description', 'icon', 'image', 'color',
        'sort_order', 'is_active', 'is_featured', 'available_hours',
    ];
    protected $casts = [
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'available_hours' => 'array',
    ];

    public function store(): BelongsTo { return $this->belongsTo(WhatsappStore::class, 'store_id'); }
    public function items(): HasMany { return $this->hasMany(StoreMenuItem::class, 'category_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(StoreMenuCategory::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(StoreMenuCategory::class, 'parent_id'); }

    protected static function booted(): void
    {
        static::creating(function ($cat) {
            if (empty($cat->slug)) {
                $cat->slug = \Illuminate\Support\Str::slug($cat->name) ?: 'cat-' . random_int(100, 9999);
            }
        });
    }
}

// ====================================================================
//  StoreMenuItem — منتج في المنيو (مع تخصيص)
// ====================================================================
class StoreMenuItem extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_menu_items';
    protected $fillable = [
        'tenant_id', 'store_id', 'product_id', 'category_id',
        'display_name', 'display_description', 'display_image',
        'gallery_images', 'display_price', 'compare_at_price',
        'is_featured', 'is_bestseller', 'is_new',
        'is_spicy', 'is_vegan', 'is_glutenfree', 'badges',
        'sort_order', 'is_visible', 'is_available',
        'available_hours', 'stock_alert_threshold',
        'addons', 'variants',
    ];
    protected $casts = [
        'gallery_images'    => 'array',
        'is_featured'       => 'boolean',
        'is_bestseller'     => 'boolean',
        'is_new'            => 'boolean',
        'is_spicy'          => 'boolean',
        'is_vegan'          => 'boolean',
        'is_glutenfree'     => 'boolean',
        'badges'            => 'array',
        'is_visible'        => 'boolean',
        'is_available'      => 'boolean',
        'available_hours'   => 'array',
        'addons'            => 'array',
        'variants'          => 'array',
    ];

    public function store(): BelongsTo { return $this->belongsTo(WhatsappStore::class, 'store_id'); }
    public function category(): BelongsTo { return $this->belongsTo(StoreMenuCategory::class, 'category_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /**
     * الاسم النهائي (مخصّص أو من المنتج)
     */
    public function getNameAttribute(): string
    {
        return $this->display_name ?: $this->product?->name ?? '';
    }

    public function getDescriptionAttribute(): string
    {
        return $this->display_description ?: $this->product?->description ?? '';
    }

    public function getImageAttribute(): ?string
    {
        return $this->display_image ?: $this->product?->image;
    }

    public function getPriceAttribute(): float
    {
        return $this->display_price ?: ($this->product?->sale_price ?? 0);
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) return null;
        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }
}
