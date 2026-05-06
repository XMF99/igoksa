<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\StoreTheme;
use App\Models\Sahab\TenantTheme;
use App\Models\Sahab\WhatsappStore;
use App\Services\Sahab\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * StoreThemeController — متجر الثيمات
 * --------------------------------------------------------------------
 *  للتاجر:
 *    - تصفّح الثيمات المتاحة (مجانيّة + مدفوعة)
 *    - شراء ثيم (one-time أو subscription)
 *    - تفعيل ثيم اشتراه
 *    - معاينة قبل الشراء
 * --------------------------------------------------------------------
 */
class StoreThemeController extends Controller
{
    public function __construct(protected PaymentGatewayService $payment) {}

    /**
     * قائمة الثيمات (متجر الثيمات)
     */
    public function marketplace(Request $request)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('whatsapp_store')) {
            abort(403, 'متاح فقط للباقة المؤسّسيّة');
        }

        $query = StoreTheme::where('is_active', true);

        if ($request->category && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->pricing) {
            $query->where('pricing_type', $request->pricing);
        }

        $themes = $query->orderByDesc('is_featured')
            ->orderByDesc('is_new')
            ->orderBy('display_order')
            ->paginate(12);

        // الثيمات اللي يملكها التاجر
        $ownedThemeIds = TenantTheme::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('theme_id')
            ->toArray();

        $store = WhatsappStore::where('tenant_id', $tenant->id)->first();
        $activeThemeId = $store?->active_theme_id;

        $categories = [
            'all'         => '🌟 الكلّ',
            'restaurant'  => '🍽 مطاعم',
            'cafe'        => '☕ كافيهات',
            'fashion'     => '👗 أزياء',
            'beauty'      => '💄 تجميل',
            'pharmacy'    => '💊 صيدليّة',
            'grocery'     => '🛒 بقالة',
            'electronics' => '📱 إلكترونيّات',
            'flowers'     => '🌹 ورود',
            'jewelry'     => '💍 مجوهرات',
            'minimal'     => '⚪ بسيط',
            'modern'      => '🎨 عصري',
            'elegant'     => '✨ أنيق',
        ];

        return view('sahab.store.themes_marketplace', compact(
            'themes', 'ownedThemeIds', 'activeThemeId', 'categories'
        ));
    }

    /**
     * تفاصيل ثيم (للمعاينة قبل الشراء)
     */
    public function show(Request $request, StoreTheme $theme)
    {
        $tenant = $request->user()->tenant;
        $owned = TenantTheme::where('tenant_id', $tenant->id)
            ->where('theme_id', $theme->id)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'theme'        => $theme,
            'is_owned'     => (bool) $owned,
            'subscription' => $owned?->subscription_ends_at,
        ]);
    }

    /**
     * شراء ثيم (مجانّي = تفعيل مباشر، مدفوع = إنشاء معاملة دفع)
     */
    public function purchase(Request $request, StoreTheme $theme)
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('whatsapp_store')) abort(403);

        // ثيم مجانّي
        if ($theme->isFree()) {
            $tenantTheme = TenantTheme::firstOrCreate(
                ['tenant_id' => $tenant->id, 'theme_id' => $theme->id],
                [
                    'purchase_type' => 'free',
                    'amount_paid'   => 0,
                    'is_active'     => true,
                ]
            );

            $theme->increment('purchase_count');

            return response()->json([
                'success'      => true,
                'message'      => '✓ تمّ تفعيل الثيم المجّانيّ',
                'tenant_theme' => $tenantTheme,
            ]);
        }

        // ثيم مدفوع — إنشاء معاملة دفع عبر Moyasar
        $validated = $request->validate([
            'payment_method' => 'required|in:apple_pay,google_pay,mada,visa,mastercard,stc_pay',
        ]);

        // (اختصاراً للنموذج، نُنشئ السجلّ مباشرةً — في الإنتاج، نمرّ ببوّابة الدفع أوّلاً)
        $purchaseType = $theme->pricing_type === 'subscription' ? 'subscription' : 'one_time';
        $amount = $theme->pricing_type === 'subscription' ? $theme->subscription_monthly : $theme->price;

        $tenantTheme = TenantTheme::create([
            'tenant_id'              => $tenant->id,
            'theme_id'               => $theme->id,
            'purchase_type'          => $purchaseType,
            'amount_paid'            => $amount,
            'payment_method'         => $validated['payment_method'],
            'subscription_starts_at' => $purchaseType === 'subscription' ? now() : null,
            'subscription_ends_at'   => $purchaseType === 'subscription' ? now()->addMonth() : null,
            'auto_renew'             => $purchaseType === 'subscription',
            'is_active'              => false, // حتى تأكيد الدفع
        ]);

        // TODO: إنشاء معاملة دفع فعليّة عبر Moyasar
        return response()->json([
            'success'        => true,
            'tenant_theme_id'=> $tenantTheme->id,
            'redirect_url'   => "/app/themes/{$tenantTheme->id}/payment",
            'amount'         => $amount,
            'message'        => 'انتقل لإكمال الدفع',
        ]);
    }

    /**
     * تفعيل ثيم اشتراه التاجر
     */
    public function activate(Request $request, StoreTheme $theme)
    {
        $tenant = $request->user()->tenant;

        $owned = TenantTheme::where('tenant_id', $tenant->id)
            ->where('theme_id', $theme->id)
            ->where('is_active', true)
            ->first();

        if (!$owned) {
            return response()->json(['error' => 'لم تشتر هذا الثيم بعد'], 422);
        }

        if ($owned->isExpired()) {
            return response()->json(['error' => 'انتهى اشتراكك. جدّد للمتابعة.'], 422);
        }

        $store = WhatsappStore::where('tenant_id', $tenant->id)->firstOrFail();
        $store->update([
            'active_theme_id' => $theme->id,
            'theme'           => $theme->slug, // للتوافق
        ]);

        return response()->json([
            'success' => true,
            'message' => '✓ تمّ تفعيل الثيم في متجرك',
            'store'   => $store,
        ]);
    }

    /**
     * ثيمات التاجر (المُشترَاة)
     */
    public function myThemes(Request $request)
    {
        $tenant = $request->user()->tenant;

        $themes = TenantTheme::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('theme')
            ->orderByDesc('created_at')
            ->get();

        return view('sahab.store.my_themes', compact('themes'));
    }
}
