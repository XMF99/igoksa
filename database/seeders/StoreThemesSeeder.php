<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sahab\StoreTheme;

/**
 * StoreThemesSeeder — كتالوج الثيمات الأوّلي
 *  6 ثيمات مجّانيّة + 6 ثيمات مدفوعة + 4 اشتراك شهري
 */
class StoreThemesSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            // ============ مجّانيّة ============
            [
                'slug' => 'modern-classic', 'name_ar' => 'كلاسيكي عصري', 'name_en' => 'Modern Classic',
                'description_ar' => 'تصميم أنيق ومتوازن يناسب جميع أنواع المحلّات',
                'category' => 'general', 'pricing_type' => 'free', 'price' => 0,
                'is_featured' => true, 'is_active' => true, 'display_order' => 1,
                'supports_dark_mode' => false, 'supports_animations' => false,
                'config' => ['layout' => 'grid', 'columns' => 2],
            ],
            [
                'slug' => 'minimal-white', 'name_ar' => 'بسيط أبيض', 'name_en' => 'Minimal White',
                'description_ar' => 'بساطة قصوى — للمتاجر المتطلّبة لتصميم نظيف',
                'category' => 'minimal', 'pricing_type' => 'free', 'price' => 0,
                'is_active' => true, 'display_order' => 2,
            ],
            [
                'slug' => 'restaurant-warm', 'name_ar' => 'مطعم دافئ', 'name_en' => 'Warm Restaurant',
                'description_ar' => 'ألوان دافئة وصور كبيرة — مثالي للمطاعم والكافيهات',
                'category' => 'restaurant', 'pricing_type' => 'free', 'price' => 0,
                'is_active' => true, 'display_order' => 3,
            ],
            [
                'slug' => 'fashion-clean', 'name_ar' => 'أزياء راقية', 'name_en' => 'Fashion Clean',
                'description_ar' => 'تصميم راقٍ يناسب محلّات الأزياء',
                'category' => 'fashion', 'pricing_type' => 'free', 'price' => 0,
                'is_active' => true, 'display_order' => 4,
            ],
            [
                'slug' => 'pharmacy-trust', 'name_ar' => 'صيدليّة موثوقة', 'name_en' => 'Trusted Pharmacy',
                'description_ar' => 'تصميم بألوان هادئة يبعث على الثقة',
                'category' => 'pharmacy', 'pricing_type' => 'free', 'price' => 0,
                'is_active' => true, 'display_order' => 5,
            ],
            [
                'slug' => 'grocery-fresh', 'name_ar' => 'بقالة طازجة', 'name_en' => 'Fresh Grocery',
                'description_ar' => 'تصميم حيوي للبقالات والسوبر ماركت',
                'category' => 'grocery', 'pricing_type' => 'free', 'price' => 0,
                'is_active' => true, 'display_order' => 6,
            ],

            // ============ مدفوعة One-Time (49-149 ر.س) ============
            [
                'slug' => 'luxury-gold', 'name_ar' => 'فخامة الذهب', 'name_en' => 'Luxury Gold',
                'description_ar' => 'تصميم فاخر بلمسات ذهبيّة — لمتاجر الفخامة والمجوهرات',
                'category' => 'jewelry', 'pricing_type' => 'one_time', 'price' => 99,
                'is_featured' => true, 'is_new' => true, 'is_active' => true, 'display_order' => 7,
                'supports_dark_mode' => true, 'supports_animations' => true,
                'features' => ['ألوان ذهبيّة', 'حركات سلسة', 'وضع داكن'],
            ],
            [
                'slug' => 'cafe-aesthetic', 'name_ar' => 'كافيه أنيق', 'name_en' => 'Aesthetic Cafe',
                'description_ar' => 'تصميم بصري جذّاب للكافيهات والـ specialty coffee',
                'category' => 'cafe', 'pricing_type' => 'one_time', 'price' => 79,
                'is_active' => true, 'display_order' => 8,
                'supports_animations' => true,
            ],
            [
                'slug' => 'beauty-pink', 'name_ar' => 'تجميل وردي', 'name_en' => 'Pink Beauty',
                'description_ar' => 'تصميم أنثوي ناعم لمحلّات التجميل والصالونات',
                'category' => 'beauty', 'pricing_type' => 'one_time', 'price' => 89,
                'is_active' => true, 'is_new' => true, 'display_order' => 9,
            ],
            [
                'slug' => 'electronics-tech', 'name_ar' => 'تكنولوجيا', 'name_en' => 'Tech Store',
                'description_ar' => 'تصميم عصري بتأثيرات تقنيّة لمحلّات الإلكترونيّات',
                'category' => 'electronics', 'pricing_type' => 'one_time', 'price' => 119,
                'is_active' => true, 'display_order' => 10,
                'supports_dark_mode' => true,
            ],
            [
                'slug' => 'flowers-romantic', 'name_ar' => 'ورود رومانسيّة', 'name_en' => 'Romantic Flowers',
                'description_ar' => 'تصميم ساحر لمحلّات الورود والهدايا',
                'category' => 'flowers', 'pricing_type' => 'one_time', 'price' => 69,
                'is_active' => true, 'display_order' => 11,
            ],
            [
                'slug' => 'modern-dark', 'name_ar' => 'عصري داكن', 'name_en' => 'Modern Dark',
                'description_ar' => 'تصميم داكن أنيق يبرز المنتجات',
                'category' => 'modern', 'pricing_type' => 'one_time', 'price' => 99,
                'is_active' => true, 'display_order' => 12,
                'supports_dark_mode' => true, 'supports_animations' => true,
            ],

            // ============ اشتراك شهري ============
            [
                'slug' => 'pro-restaurant', 'name_ar' => 'مطعم احترافيّ Pro', 'name_en' => 'Pro Restaurant',
                'description_ar' => 'ثيم متطوّر مع تحديثات مستمرّة + ميزات حصريّة',
                'category' => 'restaurant', 'pricing_type' => 'subscription',
                'price' => 0, 'subscription_monthly' => 19,
                'is_featured' => true, 'is_active' => true, 'display_order' => 20,
                'features' => ['تحديثات شهريّة', 'دعم فنّي', 'قوالب جديدة دائماً'],
            ],
            [
                'slug' => 'pro-fashion', 'name_ar' => 'أزياء احترافيّة Pro', 'name_en' => 'Pro Fashion',
                'description_ar' => 'ثيم أزياء متجدّد مع كل صيحة موضة',
                'category' => 'fashion', 'pricing_type' => 'subscription',
                'price' => 0, 'subscription_monthly' => 29,
                'is_active' => true, 'display_order' => 21,
            ],
            [
                'slug' => 'pro-luxury', 'name_ar' => 'فخامة Pro', 'name_en' => 'Pro Luxury',
                'description_ar' => 'الثيم الأكثر فخامة، مع تخصيصات لانهائيّة',
                'category' => 'elegant', 'pricing_type' => 'subscription',
                'price' => 0, 'subscription_monthly' => 39,
                'is_active' => true, 'display_order' => 22,
                'supports_dark_mode' => true, 'supports_animations' => true,
            ],
            [
                'slug' => 'pro-allinone', 'name_ar' => 'الكلّ في واحد Pro', 'name_en' => 'All-in-One Pro',
                'description_ar' => 'ثيم واحد، خيارات لانهائيّة، يناسب كل المجالات',
                'category' => 'general', 'pricing_type' => 'subscription',
                'price' => 0, 'subscription_monthly' => 49,
                'is_featured' => true, 'is_new' => true, 'is_active' => true, 'display_order' => 23,
                'supports_dark_mode' => true, 'supports_animations' => true,
                'features' => ['10+ تخطيطات', 'تخصيص كامل', 'أولويّة دعم', 'تحليلات متقدّمة'],
            ],
        ];

        foreach ($themes as $themeData) {
            StoreTheme::updateOrCreate(['slug' => $themeData['slug']], $themeData);
        }

        $this->command->info('✓ Created ' . count($themes) . ' default themes (6 free, 6 one-time, 4 subscription)');
    }
}
