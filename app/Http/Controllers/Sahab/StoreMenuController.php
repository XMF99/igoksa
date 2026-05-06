<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Models\Sahab\WhatsappStore;
use App\Models\Sahab\StoreMenuCategory;
use App\Models\Sahab\StoreMenuItem;
use App\Models\Sahab\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * StoreMenuController — محرّر منيو المتجر
 * يسمح للتاجر بـ:
 *  - إنشاء فئات المنيو (مثل: مقبّلات، رئيسي، حلويات، مشروبات)
 *  - إضافة المنتجات للمنيو
 *  - تخصيص العرض (اسم/وصف/صورة) لكلّ منتج في المتجر
 *  - إعادة ترتيب الفئات والمنتجات (drag & drop)
 *  - تحديد المنتجات المميّزة، الأكثر مبيعاً، الجديدة
 */
class StoreMenuController extends Controller
{
    public function index(Request $request)
    {
        $store = $this->getStore($request);

        $categories = StoreMenuCategory::where('store_id', $store->id)
            ->orderBy('sort_order')
            ->withCount('items')
            ->get();

        $items = StoreMenuItem::where('store_id', $store->id)
            ->with('product:id,name,image,sale_price,stock_quantity', 'category:id,name')
            ->orderBy('sort_order')
            ->get();

        $availableProducts = Product::where('tenant_id', $store->tenant_id)
            ->where('is_active', true)
            ->whereNotIn('id', $items->pluck('product_id'))
            ->withoutGlobalScopes()
            ->get(['id', 'name', 'image', 'sale_price']);

        return view('sahab.store.menu_builder', compact(
            'store', 'categories', 'items', 'availableProducts'
        ));
    }

    // ============================================================
    //  فئات المنيو
    // ============================================================
    public function storeCategory(Request $request)
    {
        $store = $this->getStore($request);

        $validated = $request->validate([
            'name'        => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:10',
            'image'       => 'nullable|string',
            'color'       => 'nullable|string|size:7',
            'parent_id'   => 'nullable|exists:sahab_store_menu_categories,id',
            'is_featured' => 'boolean',
            'available_hours' => 'nullable|array',
        ]);

        $sortOrder = StoreMenuCategory::where('store_id', $store->id)->max('sort_order') ?? 0;

        $category = StoreMenuCategory::create($validated + [
            'tenant_id'  => $store->tenant_id,
            'store_id'   => $store->id,
            'sort_order' => $sortOrder + 1,
        ]);

        return response()->json(['success' => true, 'category' => $category]);
    }

    public function updateCategory(Request $request, StoreMenuCategory $category)
    {
        $this->authorizeOwnership($request, $category);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:80',
            'description' => 'nullable|string|max:500',
            'icon'        => 'nullable|string|max:10',
            'image'       => 'nullable|string',
            'color'       => 'nullable|string|size:7',
            'is_active'   => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $category->update($validated);
        return response()->json(['success' => true, 'category' => $category]);
    }

    public function deleteCategory(Request $request, StoreMenuCategory $category)
    {
        $this->authorizeOwnership($request, $category);

        // نقل المنتجات لـ "بدون فئة"
        StoreMenuItem::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return response()->json(['success' => true]);
    }

    public function reorderCategories(Request $request)
    {
        $store = $this->getStore($request);
        $validated = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        DB::transaction(function () use ($validated, $store) {
            foreach ($validated['order'] as $i => $categoryId) {
                StoreMenuCategory::where('store_id', $store->id)
                    ->where('id', $categoryId)
                    ->update(['sort_order' => $i + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ============================================================
    //  منتجات المنيو
    // ============================================================
    public function addItem(Request $request)
    {
        $store = $this->getStore($request);

        $validated = $request->validate([
            'product_id'         => 'required|exists:sahab_products,id',
            'category_id'        => 'nullable|exists:sahab_store_menu_categories,id',
            'display_name'       => 'nullable|string|max:120',
            'display_description'=> 'nullable|string|max:500',
            'display_image'      => 'nullable|string',
            'gallery_images'     => 'nullable|array',
            'display_price'      => 'nullable|numeric|min:0',
            'compare_at_price'   => 'nullable|numeric|min:0',
            'is_featured'        => 'boolean',
            'is_bestseller'      => 'boolean',
            'is_new'             => 'boolean',
            'is_spicy'           => 'boolean',
            'is_vegan'           => 'boolean',
            'is_glutenfree'      => 'boolean',
            'badges'             => 'nullable|array',
            'addons'             => 'nullable|array',
            'variants'           => 'nullable|array',
        ]);

        $sortOrder = StoreMenuItem::where('store_id', $store->id)->max('sort_order') ?? 0;

        $item = StoreMenuItem::updateOrCreate(
            ['store_id' => $store->id, 'product_id' => $validated['product_id']],
            $validated + [
                'tenant_id'  => $store->tenant_id,
                'sort_order' => $sortOrder + 1,
                'is_visible' => true,
                'is_available' => true,
            ]
        );

        return response()->json(['success' => true, 'item' => $item->load('product', 'category')]);
    }

    public function updateItem(Request $request, StoreMenuItem $item)
    {
        $this->authorizeOwnership($request, $item);

        $validated = $request->validate([
            'category_id'        => 'nullable|exists:sahab_store_menu_categories,id',
            'display_name'       => 'nullable|string|max:120',
            'display_description'=> 'nullable|string|max:500',
            'display_image'      => 'nullable|string',
            'gallery_images'     => 'nullable|array',
            'display_price'      => 'nullable|numeric|min:0',
            'compare_at_price'   => 'nullable|numeric|min:0',
            'is_featured'        => 'boolean',
            'is_bestseller'      => 'boolean',
            'is_new'             => 'boolean',
            'is_spicy'           => 'boolean',
            'is_vegan'           => 'boolean',
            'is_glutenfree'      => 'boolean',
            'is_visible'         => 'boolean',
            'is_available'       => 'boolean',
            'badges'             => 'nullable|array',
            'addons'             => 'nullable|array',
            'variants'           => 'nullable|array',
        ]);

        $item->update($validated);
        return response()->json(['success' => true, 'item' => $item]);
    }

    public function removeItem(Request $request, StoreMenuItem $item)
    {
        $this->authorizeOwnership($request, $item);
        $item->delete();
        return response()->json(['success' => true]);
    }

    public function reorderItems(Request $request)
    {
        $store = $this->getStore($request);
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer',
            'order.*.category_id' => 'nullable|integer',
        ]);

        DB::transaction(function () use ($validated, $store) {
            foreach ($validated['order'] as $i => $row) {
                StoreMenuItem::where('store_id', $store->id)
                    ->where('id', $row['id'])
                    ->update([
                        'sort_order'  => $i + 1,
                        'category_id' => $row['category_id'] ?? null,
                    ]);
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * استيراد دفعة منتجات من POS للمنيو
     */
    public function bulkImport(Request $request)
    {
        $store = $this->getStore($request);

        $validated = $request->validate([
            'product_ids'  => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:sahab_products,id',
            'category_id'  => 'nullable|exists:sahab_store_menu_categories,id',
        ]);

        $created = 0;
        DB::transaction(function () use ($validated, $store, &$created) {
            $sortOrder = StoreMenuItem::where('store_id', $store->id)->max('sort_order') ?? 0;
            foreach ($validated['product_ids'] as $productId) {
                $exists = StoreMenuItem::where('store_id', $store->id)
                    ->where('product_id', $productId)->exists();
                if ($exists) continue;

                StoreMenuItem::create([
                    'tenant_id'    => $store->tenant_id,
                    'store_id'     => $store->id,
                    'product_id'   => $productId,
                    'category_id'  => $validated['category_id'] ?? null,
                    'sort_order'   => ++$sortOrder,
                    'is_visible'   => true,
                    'is_available' => true,
                ]);
                $created++;
            }
        });

        return response()->json([
            'success'      => true,
            'imported'     => $created,
            'message'      => "تمّ إضافة {$created} منتج للمنيو",
        ]);
    }

    // ============================================================
    //  Helpers
    // ============================================================
    protected function getStore(Request $request): WhatsappStore
    {
        $tenant = $request->user()->tenant;
        if (!$tenant->canAccessFeature('whatsapp_store')) {
            abort(403, 'متجر الواتساب متاح فقط في الباقة المؤسّسيّة');
        }
        return WhatsappStore::where('tenant_id', $tenant->id)->firstOrFail();
    }

    protected function authorizeOwnership(Request $request, $model): void
    {
        $tenant = $request->user()->tenant;
        if ($model->tenant_id !== $tenant->id) abort(403);
    }
}
