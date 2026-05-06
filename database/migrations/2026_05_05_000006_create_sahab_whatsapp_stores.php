<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 006 — متجر الواتساب لكل مستأجر
 * --------------------------------------------------------------------
 *  مستوحى من WhatsStore — لكنّ مرتبط بالـ tenant
 *
 *  ✓ كل مستأجر له متجر واتساب خاص
 *  ✓ رابط فريد: sahab.sa/store/{slug}
 *  ✓ العميل يضيف للسلّة → يضغط "إرسال على واتساب" → يصل للتاجر مباشرة
 *  ✓ الطلب يدخل تلقائياً في sahab_invoices بـ source='whatsapp'
 *  ✓ صفحة هبوط جذّابة + 6 ثيمات
 *
 *  متاح فقط في الباقة المؤسّسيّة (enterprise)
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) متاجر الواتساب
        // ============================================================
        Schema::create('sahab_whatsapp_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('sahab_tenants')->cascadeOnDelete();

            // الرابط
            $table->string('slug', 60)->unique()->comment('sahab.sa/store/{slug}');
            $table->string('custom_domain')->nullable()->unique();

            // البيانات الأساسيّة
            $table->string('name');
            $table->string('tagline')->nullable()->comment('عبارة جذّابة قصيرة');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('cover_image')->nullable();

            // العلامة التجاريّة
            $table->string('primary_color', 9)->default('#0F6E56');
            $table->string('secondary_color', 9)->default('#B07B3F');
            $table->enum('theme', [
                'modern',      // عصري
                'elegant',     // أنيق
                'minimal',     // بسيط
                'classic',     // كلاسيكي
                'food',        // مطاعم
                'fashion',     // أزياء
            ])->default('modern');

            // إعدادات الواتساب
            $table->string('whatsapp_number', 20)->comment('بصيغة 9665XXXXXXXX');
            $table->text('order_message_template')->nullable()->comment('القالب');
            $table->boolean('show_prices')->default(true);
            $table->boolean('require_customer_info')->default(true);

            // إعدادات الطلب
            $table->boolean('enable_delivery')->default(true);
            $table->boolean('enable_pickup')->default(true);
            $table->decimal('min_order_amount', 8, 2)->default(0);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('free_delivery_above', 8, 2)->nullable();
            $table->json('delivery_zones')->nullable()->comment('المناطق + الأسعار');

            // وقت العمل
            $table->json('working_hours')->nullable();
            $table->boolean('show_when_closed')->default(true);
            $table->text('closed_message')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->json('social_links')->nullable();

            // Statistics
            $table->integer('total_visits')->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);

            // الحالة
            $table->boolean('is_active')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // PWA
            $table->boolean('enable_pwa')->default(true);
            $table->string('pwa_name', 60)->nullable();
            $table->string('pwa_short_name', 12)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // 2) صفحات إضافيّة للمتجر (عن المتجر، الشروط، إلخ)
        // ============================================================
        Schema::create('sahab_store_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();
            $table->string('slug', 60);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('show_in_menu')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
        });

        // ============================================================
        // 3) كوبونات الخصم
        // ============================================================
        Schema::create('sahab_store_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();

            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('type', ['percentage', 'fixed', 'free_shipping']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->decimal('max_discount_amount', 10, 2)->nullable();

            $table->integer('usage_limit')->nullable()->comment('حدّ الاستخدام الكلّي');
            $table->integer('usage_count')->default(0);
            $table->integer('per_customer_limit')->default(1);

            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->unique(['store_id', 'code']);
        });

        // ============================================================
        // 4) سلّات العملاء (Cart sessions) — قبل الطلب
        // ============================================================
        Schema::create('sahab_store_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();

            $table->string('session_id', 80)->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();

            $table->json('items')->comment('المنتجات في السلّة');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('coupon_code', 30)->nullable();

            $table->enum('status', ['active', 'sent_to_whatsapp', 'completed', 'abandoned'])->default('active');
            $table->timestamp('last_activity_at');

            $table->timestamps();
            $table->index(['store_id', 'session_id']);
        });

        // ============================================================
        // 5) إحصائيّات الزيارات
        // ============================================================
        Schema::create('sahab_store_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('sahab_whatsapp_stores')->cascadeOnDelete();
            $table->date('date')->index();

            $table->integer('visits')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->integer('product_views')->default(0);
            $table->integer('add_to_cart_count')->default(0);
            $table->integer('whatsapp_orders_count')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);

            // المصادر
            $table->json('traffic_sources')->nullable();
            $table->json('top_products')->nullable();

            $table->timestamps();
            $table->unique(['store_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_store_analytics');
        Schema::dropIfExists('sahab_store_carts');
        Schema::dropIfExists('sahab_store_coupons');
        Schema::dropIfExists('sahab_store_pages');
        Schema::dropIfExists('sahab_whatsapp_stores');
    }
};
