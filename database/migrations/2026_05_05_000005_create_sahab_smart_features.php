<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 005 — الميزات الذكيّة
 * --------------------------------------------------------------------
 *  ✓ تكامل تطبيقات التوصيل
 *  ✓ التقارير الذكيّة (يومي/أسبوعي/شهري)
 *  ✓ كاشف الأنشطة المشبوهة
 *  ✓ نظام الولاء
 *  ✓ سجلّ AI واستهلاكه
 *  ✓ سجلّ الواتساب
 *  ✓ الاختبارات المفاجئة للموظّفين
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) تكاملات تطبيقات التوصيل
        // ============================================================
        Schema::create('sahab_delivery_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->enum('platform', [
                'hungerstation', 'jahez', 'toshhel', 'mrsool',
                'thechefz', 'ninja', 'toyou', 'talabat'
            ]);
            $table->boolean('is_active')->default(false);
            $table->text('api_credentials')->nullable()->comment('مشفّر AES-256');
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->integer('default_prep_time')->default(25);
            $table->boolean('auto_accept')->default(false);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_menu_sync_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'platform']);
        });

        // ============================================================
        // 2) الطلبات الخارجيّة
        // ============================================================
        Schema::create('sahab_external_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('external_id', 80);
            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices')->nullOnDelete();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();

            $table->json('items_json');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('platform_commission', 8, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method', 40)->nullable();
            $table->boolean('is_paid')->default(false);

            $table->enum('status', [
                'pending', 'accepted', 'rejected', 'cooking',
                'ready', 'picked_up', 'delivered', 'cancelled', 'failed'
            ])->default('pending');

            $table->integer('prep_time_minutes')->nullable();
            $table->string('rejection_reason', 120)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->unique(['platform', 'external_id']);
            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 3) سجلّ Webhooks (للتشخيص)
        // ============================================================
        Schema::create('sahab_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('source', 40);
            $table->string('event_type', 80)->nullable();
            $table->mediumText('raw_body')->nullable();
            $table->smallInteger('response_code')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'source', 'created_at']);
        });

        // ============================================================
        // 4) إعدادات التقارير الذكيّة (مستوحاة من دفترة)
        // ============================================================
        Schema::create('sahab_report_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->comment('FK to ERPGo users');

            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->time('send_time')->default('21:00:00');
            $table->json('recipients')->comment('[{"channel":"whatsapp","value":"+9665..."},...]');

            // محتوى التقرير (المستخدم يختار ماذا يريد)
            $table->boolean('include_sales_summary')->default(true);
            $table->boolean('include_top_products')->default(true);
            $table->boolean('include_top_categories')->default(false);
            $table->boolean('include_top_employees')->default(true);
            $table->boolean('include_payment_methods')->default(true);
            $table->boolean('include_delivery_breakdown')->default(true);
            $table->boolean('include_low_stock')->default(true);
            $table->boolean('include_expiring_documents')->default(true);
            $table->boolean('include_comparisons')->default(true);
            $table->boolean('include_predictions')->default(false);
            $table->boolean('include_ai_insights')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // سجلّ التقارير المُرسلة
        Schema::create('sahab_report_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->date('report_date');
            $table->json('payload');
            $table->json('channels_sent')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'report_date']);
        });

        // ============================================================
        // 5) كاشف الأنشطة المشبوهة
        // ============================================================
        Schema::create('sahab_suspicious_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('shift_id')->nullable();
            $table->foreignId('invoice_id')->nullable();

            $table->enum('activity_type', [
                'frequent_cancellations',  // إلغاءات متكرّرة
                'large_discount',          // خصم كبير
                'frequent_refunds',        // مرتجعات متكرّرة
                'cash_variance',           // فرق نقدي
                'odd_hours',               // عمل في وقت غريب
                'duplicate_invoice',       // فاتورة مكرّرة
                'price_modification',      // تعديل سعر
                'item_removal_after_payment', // حذف صنف بعد الدفع
                'other',
            ]);

            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('title');
            $table->text('description');
            $table->json('evidence')->nullable();

            $table->enum('status', ['new', 'reviewed', 'resolved', 'false_positive'])->default('new');
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('action_taken')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'severity', 'status']);
        });

        // ============================================================
        // 6) برنامج الولاء
        // ============================================================
        Schema::create('sahab_loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('sahab_tenants')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);

            // النقاط
            $table->decimal('points_per_riyal', 6, 2)->default(1)->comment('نقاط لكل ريال');
            $table->decimal('riyal_per_point', 6, 2)->default(0.01)->comment('قيمة النقطة');
            $table->integer('min_redemption_points')->default(100);

            // الفئات
            $table->integer('silver_threshold')->default(500)->comment('نقاط للترقّي');
            $table->integer('gold_threshold')->default(2000);
            $table->integer('platinum_threshold')->default(10000);

            // المضاعفات حسب الفئة
            $table->decimal('bronze_multiplier', 3, 1)->default(1.0);
            $table->decimal('silver_multiplier', 3, 1)->default(1.5);
            $table->decimal('gold_multiplier', 3, 1)->default(2.0);
            $table->decimal('platinum_multiplier', 3, 1)->default(3.0);

            // نقاط ميلاد العميل
            $table->integer('birthday_bonus_points')->default(500);

            $table->timestamps();
        });

        Schema::create('sahab_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('sahab_customers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices')->nullOnDelete();

            $table->enum('type', ['earned', 'redeemed', 'bonus', 'expired', 'adjusted']);
            $table->integer('points');
            $table->integer('balance_after');
            $table->decimal('value_in_riyal', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
        });

        // ============================================================
        // 7) سجلّ استخدام AI (للحسابات والفواتير)
        // ============================================================
        Schema::create('sahab_ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();

            $table->enum('feature', [
                'assistant',     // المساعد الذكي
                'ocr',           // قراءة الفواتير
                'vision',        // تحليل صور
                'reports',       // تقارير ذكيّة
                'predictions',   // توقّعات
                'insights',      // رؤى وتحليلات
                'whatsapp_bot',  // بوت واتساب
            ]);

            $table->string('model', 60);
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->integer('latency_ms')->nullable();

            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'feature', 'created_at']);
        });

        // ============================================================
        // 8) سجلّ الواتساب والرسائل
        // ============================================================
        Schema::create('sahab_messaging_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('sahab_tenants')->cascadeOnDelete();
            $table->enum('channel', ['whatsapp', 'sms', 'email', 'push']);
            $table->string('provider', 40);
            $table->string('recipient', 120);
            $table->string('template', 80)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('body_preview')->nullable();
            $table->string('external_id', 120)->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->text('error')->nullable();
            $table->decimal('cost_sar', 8, 4)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 9) الاختبارات المفاجئة (للموظّفين)
        // ============================================================
        Schema::create('sahab_surprise_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('sahab_attendance');

            $table->enum('test_type', ['qr_scan', 'photo_selfie', 'gps_check', 'pin_code']);
            $table->timestamp('triggered_at');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();

            $table->enum('result', ['pending', 'passed', 'failed', 'expired'])->default('pending');
            $table->json('response_data')->nullable();

            $table->decimal('response_lat', 10, 7)->nullable();
            $table->decimal('response_lng', 10, 7)->nullable();
            $table->integer('distance_meters')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'result']);
        });

        // ============================================================
        // 10) سجلّ القراءة الضوئيّة (OCR Log)
        // ============================================================
        Schema::create('sahab_ocr_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();
            $table->enum('document_type', ['invoice', 'receipt', 'iqama', 'cr', 'other']);
            $table->string('original_image_path');
            $table->json('extracted_data')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('was_confirmed')->default(false);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_ocr_log');
        Schema::dropIfExists('sahab_surprise_tests');
        Schema::dropIfExists('sahab_messaging_log');
        Schema::dropIfExists('sahab_ai_usage');
        Schema::dropIfExists('sahab_loyalty_transactions');
        Schema::dropIfExists('sahab_loyalty_settings');
        Schema::dropIfExists('sahab_suspicious_activities');
        Schema::dropIfExists('sahab_report_log');
        Schema::dropIfExists('sahab_report_subscriptions');
        Schema::dropIfExists('sahab_webhook_log');
        Schema::dropIfExists('sahab_external_orders');
        Schema::dropIfExists('sahab_delivery_integrations');
    }
};
