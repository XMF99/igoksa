<?php

namespace App\Models\Sahab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * متجر الواتساب — كل tenant له متجره
 * متاح فقط في الباقة المؤسّسيّة
 */
class WhatsappStore extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'sahab_whatsapp_stores';

    protected $fillable = [
        'tenant_id', 'slug', 'custom_domain',
        'name', 'tagline', 'description', 'logo', 'cover_image',
        'primary_color', 'secondary_color', 'theme',
        'whatsapp_number', 'order_message_template',
        'show_prices', 'require_customer_info',
        'enable_delivery', 'enable_pickup',
        'min_order_amount', 'delivery_fee', 'free_delivery_above', 'delivery_zones',
        'working_hours', 'show_when_closed', 'closed_message',
        'meta_title', 'meta_description', 'meta_keywords', 'social_links',
        'total_visits', 'total_orders', 'total_revenue',
        'is_active', 'is_published', 'published_at',
        'enable_pwa', 'pwa_name', 'pwa_short_name',
    ];

    protected $casts = [
        'delivery_zones'  => 'array',
        'working_hours'   => 'array',
        'social_links'    => 'array',
        'is_active'       => 'boolean',
        'is_published'    => 'boolean',
        'show_prices'     => 'boolean',
        'require_customer_info' => 'boolean',
        'enable_delivery' => 'boolean',
        'enable_pickup'   => 'boolean',
        'show_when_closed'=> 'boolean',
        'enable_pwa'      => 'boolean',
        'published_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($store) {
            if (empty($store->slug)) {
                $store->slug = self::generateUniqueSlug($store->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(StorePage::class, 'store_id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(StoreCoupon::class, 'store_id');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(StoreCart::class, 'store_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(StoreAnalytic::class, 'store_id');
    }

    /**
     * الرابط الكامل للمتجر
     */
    public function getUrlAttribute(): string
    {
        if ($this->custom_domain) return "https://{$this->custom_domain}";
        return url("/store/{$this->slug}");
    }

    /**
     * هل المتجر مفتوح حالياً؟
     */
    public function isOpenNow(): bool
    {
        $hours = $this->working_hours ?? [];
        if (empty($hours)) return true;

        $now = now()->setTimezone('Asia/Riyadh');
        $today = strtolower($now->format('l'));

        $todayHours = $hours[$today] ?? null;
        if (!$todayHours || empty($todayHours['open'])) return false;

        $open = \Carbon\Carbon::createFromFormat('H:i', $todayHours['open'])->setTimezone('Asia/Riyadh');
        $close = \Carbon\Carbon::createFromFormat('H:i', $todayHours['close'])->setTimezone('Asia/Riyadh');

        return $now->between($open, $close);
    }

    /**
     * توليد رابط واتساب للطلب
     */
    public function generateWhatsappLink(string $message): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->whatsapp_number);
        return "https://wa.me/{$phone}?text=" . urlencode($message);
    }

    /**
     * توليد slug فريد
     */
    public static function generateUniqueSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name, '-');
        if (empty($base) || strlen($base) < 3) {
            $base = 'store-' . random_int(1000, 9999);
        }

        $slug = $base;
        $i = 1;
        while (self::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}

// ====================================================================

class StorePage extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_pages';
    protected $fillable = [
        'tenant_id', 'store_id', 'slug', 'title', 'content',
        'show_in_menu', 'sort_order', 'is_active',
    ];
}

class StoreCoupon extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_coupons';
    protected $fillable = [
        'tenant_id', 'store_id', 'code', 'name', 'description',
        'type', 'value', 'min_order_amount', 'max_discount_amount',
        'usage_limit', 'usage_count', 'per_customer_limit',
        'starts_at', 'expires_at', 'is_active',
    ];
    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) return false;
        return true;
    }

    /**
     * حساب قيمة الخصم
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < $this->min_order_amount) return 0;

        $discount = match($this->type) {
            'percentage'    => $subtotal * ($this->value / 100),
            'fixed'         => $this->value,
            'free_shipping' => 0,
            default         => 0,
        };

        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        return min($discount, $subtotal);
    }
}

class StoreCart extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_carts';
    protected $fillable = [
        'tenant_id', 'store_id', 'session_id',
        'customer_name', 'customer_phone',
        'items', 'subtotal', 'discount', 'total', 'coupon_code',
        'status', 'last_activity_at',
    ];
    protected $casts = [
        'items' => 'array',
        'last_activity_at' => 'datetime',
    ];
}

class StoreAnalytic extends Model
{
    use TenantScoped;
    protected $table = 'sahab_store_analytics';
    protected $fillable = [
        'tenant_id', 'store_id', 'date',
        'visits', 'unique_visitors', 'product_views',
        'add_to_cart_count', 'whatsapp_orders_count', 'total_revenue',
        'traffic_sources', 'top_products',
    ];
    protected $casts = [
        'date' => 'date',
        'traffic_sources' => 'array',
        'top_products' => 'array',
    ];
}
