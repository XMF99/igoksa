<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 001 — البنية الأساسيّة
 * --------------------------------------------------------------------
 *  - sahab_tenants            : المستأجرون (شركات/محلّات)
 *  - sahab_subscriptions      : الاشتراكات النشطة
 *  - sahab_outlets            : الفروع (متعدّد)
 *  - sahab_pos_terminals      : أجهزة الكاشير
 *  - sahab_zatca_credentials  : بيانات الزكاة لكل مستأجر
 *  - sahab_settings           : إعدادات لكل مستأجر
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) المستأجرون (Tenants) — كل محلّ/شركة
        // ============================================================
        Schema::create('sahab_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('كود فريد للمستأجر');
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('owner_name');
            $table->string('mobile', 20)->index();
            $table->string('email')->index();

            // البيانات التجاريّة (دفترة-style)
            $table->string('cr_number', 30)->nullable()->comment('السجل التجاري');
            $table->string('vat_number', 20)->nullable()->comment('الرقم الضريبي 15 خانة');
            $table->string('bldg_no', 10)->nullable()->comment('رقم المبنى');
            $table->string('street', 120)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('city', 60)->default('الرياض');
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 30)->default('SA');

            // الفئة والقطاع
            $table->enum('industry', [
                'restaurant', 'cafe', 'grocery', 'pharmacy', 'retail',
                'salon', 'fashion', 'electronics', 'service', 'other'
            ])->default('restaurant');

            // SaaS subscription
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->enum('subscription_status', [
                'trial', 'active', 'past_due', 'suspended', 'cancelled'
            ])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');

            // مفاتيح API لتطبيق الجوّال
            $table->string('api_key', 64)->nullable()->unique();
            $table->string('api_secret_hash')->nullable();

            // العلامة التجاريّة
            $table->string('logo')->nullable();
            $table->string('primary_color', 7)->default('#0F6E56');
            $table->json('preferences')->nullable();

            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subscription_status', 'subscription_ends_at']);
        });

        // ============================================================
        // 2) خطط الاشتراكات
        // ============================================================
        Schema::create('sahab_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_annual', 10, 2);
            $table->integer('max_outlets')->default(1);
            $table->integer('max_users')->default(2);
            $table->integer('max_products')->default(0)->comment('0 = غير محدود');
            $table->integer('max_invoices_per_month')->default(0);
            $table->integer('ai_quota_monthly')->default(0)->comment('عدد طلبات AI المتاحة شهرياً');
            $table->integer('ocr_quota_monthly')->default(0);
            $table->json('features');
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        // ============================================================
        // 3) الفروع (Outlets)
        // ============================================================
        Schema::create('sahab_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('manager_name')->nullable();
            $table->string('mobile', 20)->nullable();

            // العنوان (للزكاة وللتوصيل)
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // إعدادات التشغيل
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->json('working_days')->nullable()->comment('أيام العمل [0-6]');

            // Geofencing لحضور الموظفين
            $table->integer('geofence_radius_meters')->default(150);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // ============================================================
        // 4) أجهزة الكاشير (Terminals)
        // ============================================================
        Schema::create('sahab_pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('sahab_outlets')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('device_id', 100)->nullable()->comment('UUID للجهاز');
            $table->enum('platform', ['android', 'ios', 'windows', 'macos', 'web'])->default('android');
            $table->string('printer_ip', 45)->nullable();
            $table->integer('printer_port')->default(9100);
            $table->enum('printer_size', ['mm58', 'mm80'])->default('mm80');
            $table->boolean('cash_drawer_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // ============================================================
        // 5) بيانات اعتماد الزكاة (ZATCA Phase 1 + Phase 2)
        // ============================================================
        Schema::create('sahab_zatca_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('vat_number', 20);
            $table->string('cr_number', 30);
            $table->enum('phase', ['phase1', 'phase2'])->default('phase1');

            // مرحلة 2: شهادات
            $table->text('csr_pem')->nullable()->comment('Certificate Signing Request');
            $table->text('private_key_pem')->nullable();
            $table->text('compliance_csid')->nullable()->comment('CSID من زاتكا');
            $table->text('production_csid')->nullable();
            $table->string('compliance_request_id', 80)->nullable();

            // سلسلة الفواتير
            $table->bigInteger('last_invoice_counter')->default(0);
            $table->string('last_invoice_hash', 128)->nullable();

            $table->boolean('is_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 6) الإعدادات لكل مستأجر
        // ============================================================
        Schema::create('sahab_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('key', 80);
            $table->text('value')->nullable();
            $table->string('group', 40)->default('general');
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'group']);
        });

        // الـ FK الذي تأخّر
        Schema::table('sahab_tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('sahab_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sahab_tenants', fn(Blueprint $t) => $t->dropForeign(['plan_id']));
        Schema::dropIfExists('sahab_settings');
        Schema::dropIfExists('sahab_zatca_credentials');
        Schema::dropIfExists('sahab_pos_terminals');
        Schema::dropIfExists('sahab_outlets');
        Schema::dropIfExists('sahab_plans');
        Schema::dropIfExists('sahab_tenants');
    }
};
