<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 009 — متجر الثيمات + إعدادات الدفع للمتجر
 * --------------------------------------------------------------------
 *  ✓ ثيمات إضافيّة يشتريها التاجر (free + premium)
 *  ✓ ربط الثيم المشترى بالمتجر
 *  ✓ إعدادات Apple Pay / Google Pay / Mada / STC Pay للمتجر
 *  ✓ معاملات الدفع الإلكتروني للطلبات
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        //  1) كتالوج الثيمات (يديره الـ super-admin)
        // ============================================================
        Schema::create('sahab_store_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name_ar', 80);
            $table->string('name_en', 80)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            // فئة الثيم
            $table->enum('category', [
                'restaurant',     // مطاعم
                'cafe',           // كافيهات
                'fashion',        // أزياء
                'beauty',         // تجميل
                'pharmacy',       // صيدلية
                'grocery',        // بقالة
                'electronics',    // إلكترونيات
                'flowers',        // ورود وهدايا
                'jewelry',        // مجوهرات
                'general',        // عام
                'minimal',        // بسيط
                'modern',         // عصري
                'elegant',        // أنيق
            ])->default('general');

            // التسعير
            $table->enum('pricing_type', ['free', 'one_time', 'subscription'])->default('free');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('subscription_monthly', 10, 2)->nullable();

            // الملفّات
            $table->string('preview_image')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->json('preview_screenshots')->nullable();
            $table->string('demo_url')->nullable();

            // إعدادات الثيم (CSS variables, layouts, fonts)
            $table->json('config')->nullable()->comment('CSS vars, layout settings, animations');
            $table->json('color_palettes')->nullable()->comment('color schemes');
            $table->json('fonts')->nullable();

            // مزايا
            $table->json('features')->nullable()->comment('قائمة المزايا');
            $table->boolean('supports_dark_mode')->default(false);
            $table->boolean('supports_rtl')->default(true);
            $table->boolean('supports_animations')->default(false);

            // الحالة
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->integer('display_order')->default(0);
            $table->integer('purchase_count')->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('rating_count')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        //  2) ثيمات اشتراها التجّار
        // ============================================================
        Schema::create('sahab_tenant_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('theme_id')->constrained('sahab_store_themes')->cascadeOnDelete();

            $table->enum('purchase_type', ['free', 'one_time', 'subscription']);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('transaction_id')->nullable();
            $table->string('payment_method', 40)->nullable();

            // للاشتراك الشهري
            $table->date('subscription_starts_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->boolean('auto_renew')->default(false);

            $table->boolean('is_active')->default(true);
            $table->json('custom_overrides')->nullable()->comment('تعديلات التاجر على الثيم');

            $table->timestamps();
            $table->unique(['tenant_id', 'theme_id']);
        });

        // ============================================================
        //  3) إعدادات الدفع للمتجر — توسيع
        // ============================================================
        Schema::table('sahab_whatsapp_stores', function (Blueprint $table) {
            // الثيم النشط (كان theme بسيط، نخلّيه يربط بالكتالوج)
            if (!Schema::hasColumn('sahab_whatsapp_stores', 'active_theme_id')) {
                $table->foreignId('active_theme_id')->nullable()
                    ->after('theme')
                    ->constrained('sahab_store_themes')->nullOnDelete();
            }

            // الدفع الإلكتروني — تفعيل
            if (!Schema::hasColumn('sahab_whatsapp_stores', 'enable_online_payment')) {
                $table->boolean('enable_online_payment')->default(false);
                $table->boolean('enable_apple_pay')->default(false);
                $table->boolean('enable_google_pay')->default(false);
                $table->boolean('enable_mada')->default(true);
                $table->boolean('enable_visa_master')->default(true);
                $table->boolean('enable_stc_pay')->default(false);
                $table->boolean('enable_tabby')->default(false);
                $table->boolean('enable_tamara')->default(false);
                $table->boolean('enable_cash_on_delivery')->default(true);
                $table->boolean('enable_bank_transfer')->default(false);
            }

            // مفاتيح بوابة الدفع (مشفّرة)
            if (!Schema::hasColumn('sahab_whatsapp_stores', 'payment_gateway')) {
                $table->string('payment_gateway', 40)->nullable()
                    ->comment('moyasar, hyperpay, paytabs, stripe');
                $table->text('payment_credentials_encrypted')->nullable();
                $table->string('apple_pay_merchant_id')->nullable();
                $table->string('apple_pay_domain')->nullable();
            }

            // تحرير المنيو
            if (!Schema::hasColumn('sahab_whatsapp_stores', 'menu_layout')) {
                $table->enum('menu_layout', ['grid', 'list', 'cards', 'masonry'])->default('grid')
                    ->after('theme');
                $table->boolean('show_categories_horizontal')->default(true);
                $table->boolean('show_search')->default(true);
                $table->boolean('show_filters')->default(true);
                $table->boolean('group_by_category')->default(true);
            }
        });

        // ============================================================
        //  4) معاملات الدفع للمتجر (online payments)
        // ============================================================
        Schema::create('sahab_store_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices');

            $table->string('transaction_id')->unique();
            $table->string('gateway_reference')->nullable()->index();

            $table->enum('payment_method', [
                'apple_pay', 'google_pay', 'mada', 'visa', 'mastercard',
                'stc_pay', 'tabby', 'tamara', 'cash_on_delivery', 'bank_transfer',
            ]);
            $table->string('gateway', 40)->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->decimal('fees', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);

            $table->enum('status', [
                'pending', 'authorized', 'captured', 'paid',
                'failed', 'refunded', 'partially_refunded', 'cancelled',
            ])->default('pending');

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_mobile', 20)->nullable();

            $table->json('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->ipAddress('ip_address')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        // ============================================================
        //  5) فئات المنيو للمتجر (للمحرّر)
        // ============================================================
        Schema::create('sahab_store_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->references('id')->on('sahab_store_menu_categories');

            $table->string('name');
            $table->string('slug', 80);
            $table->text('description')->nullable();
            $table->string('icon', 10)->nullable()->comment('emoji icon');
            $table->string('image')->nullable();
            $table->string('color', 9)->nullable();

            // عرض
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->json('available_hours')->nullable()->comment('breakfast/lunch/dinner only');

            $table->timestamps();
            $table->index(['store_id', 'sort_order']);
        });

        // ============================================================
        //  6) ربط منتجات المنيو بالفئات (Many-to-Many)
        // ============================================================
        Schema::create('sahab_store_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('sahab_products')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('sahab_store_menu_categories');

            // تخصيص المتجر (يطغى على المنتج الأساسي)
            $table->string('display_name')->nullable();
            $table->text('display_description')->nullable();
            $table->string('display_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->decimal('display_price', 10, 2)->nullable();
            $table->decimal('compare_at_price', 10, 2)->nullable()->comment('السعر قبل التخفيض');

            // مزايا
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_bestseller')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_glutenfree')->default(false);
            $table->json('badges')->nullable()->comment('Custom badges: حلال، عضوي، خالي من السكر');

            // المنيو
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_available')->default(true);
            $table->json('available_hours')->nullable();
            $table->integer('stock_alert_threshold')->nullable();

            // مزيد من خيارات
            $table->json('addons')->nullable()->comment('إضافات اختياريّة');
            $table->json('variants')->nullable()->comment('أحجام/نكهات');

            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
            $table->index(['store_id', 'category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_store_menu_items');
        Schema::dropIfExists('sahab_store_menu_categories');
        Schema::dropIfExists('sahab_store_transactions');
        Schema::table('sahab_whatsapp_stores', function (Blueprint $table) {
            $table->dropColumn([
                'active_theme_id', 'enable_online_payment',
                'enable_apple_pay', 'enable_google_pay', 'enable_mada',
                'enable_visa_master', 'enable_stc_pay', 'enable_tabby',
                'enable_tamara', 'enable_cash_on_delivery', 'enable_bank_transfer',
                'payment_gateway', 'payment_credentials_encrypted',
                'apple_pay_merchant_id', 'apple_pay_domain',
                'menu_layout', 'show_categories_horizontal', 'show_search',
                'show_filters', 'group_by_category',
            ]);
        });
        Schema::dropIfExists('sahab_tenant_themes');
        Schema::dropIfExists('sahab_store_themes');
    }
};
