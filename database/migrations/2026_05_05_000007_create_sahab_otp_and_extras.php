<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 007 — OTP + ميزات دفترة الإضافيّة
 * --------------------------------------------------------------------
 *  ✓ نظام OTP عبر واتساب (للتسجيل + تسجيل الدخول + للموظّفين)
 *  ✓ المبيعات المستهدفة والعمولات (Sales Targets)
 *  ✓ نظام الأقساط
 *  ✓ نقاط الولاء المتقدّمة (الموجودة بالفعل، فقط نتأكّد)
 *  ✓ الاشتراكات والعضويّات
 *  ✓ أوامر الشغل
 *  ✓ الحجوزات
 *  ✓ تتبّع الوقت
 *  ✓ عقود الإيجار والوحدات
 *  ✓ المبيعات المستهدفة
 *  ✓ الأذون المخزنية
 *  ✓ التصنيع
 *  ✓ الهيكل التنظيمي
 *  ✓ مركز التكلفة (موسّع)
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        //  1) OTP CODES — رموز التحقق
        // ============================================================
        Schema::create('sahab_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20)->index();
            $table->string('code', 6);
            $table->enum('purpose', [
                'register',         // التسجيل الجديد
                'login',            // تسجيل الدخول
                'reset_password',   // إعادة تعيين كلمة المرور
                'verify_mobile',    // التحقّق من الجوّال
                'employee_login',   // دخول الموظّف
                'sensitive_action', // عمليّة حسّاسة (حذف، خصم كبير)
            ]);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index(['mobile', 'code', 'expires_at']);
            $table->index(['mobile', 'purpose']);
        });

        // ============================================================
        //  2) المبيعات المستهدفة والعمولات (Sales Targets)
        // ============================================================
        Schema::create('sahab_sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('sahab_employees')->cascadeOnDelete();

            $table->string('name');
            $table->enum('period', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
            $table->date('start_date');
            $table->date('end_date');

            $table->decimal('target_amount', 12, 2);
            $table->decimal('achieved_amount', 12, 2)->default(0);
            $table->integer('target_count')->nullable()->comment('هدف عدد الفواتير');
            $table->integer('achieved_count')->default(0);

            // العمولة
            $table->enum('commission_type', ['percentage', 'fixed', 'tiered'])->default('percentage');
            $table->decimal('commission_rate', 8, 4)->nullable();
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->json('commission_tiers')->nullable()->comment('للنوع tiered');

            $table->boolean('only_above_target')->default(false);
            $table->enum('status', ['active', 'achieved', 'failed', 'paused'])->default('active');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        // ============================================================
        //  3) الأقساط (Installments)
        // ============================================================
        Schema::create('sahab_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('sahab_invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('sahab_customers');

            $table->string('plan_number')->unique();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('down_payment', 12, 2)->default(0);
            $table->decimal('financed_amount', 12, 2);
            $table->decimal('admin_fee', 10, 2)->default(0);

            $table->integer('total_installments');
            $table->integer('paid_installments')->default(0);
            $table->decimal('installment_amount', 10, 2);

            $table->date('start_date');
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly'])->default('monthly');

            $table->enum('status', ['active', 'completed', 'cancelled', 'defaulted'])->default('active');
            $table->date('next_due_date')->nullable();
            $table->integer('overdue_count')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sahab_installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('sahab_installments')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('late_fee', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue', 'waived'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        // ============================================================
        //  4) الاشتراكات والعضويّات (Memberships)
        // ============================================================
        Schema::create('sahab_membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('billing_cycle', ['weekly', 'monthly', 'quarterly', 'yearly', 'one_time'])->default('monthly');
            $table->integer('duration_days')->nullable();

            $table->json('benefits')->nullable()->comment('قائمة المزايا');
            $table->integer('max_visits_per_period')->nullable();
            $table->json('included_services')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
        });

        Schema::create('sahab_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('sahab_customers');
            $table->foreignId('plan_id')->constrained('sahab_membership_plans');

            $table->string('membership_number')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('renewed_until')->nullable();

            $table->enum('status', ['active', 'expired', 'suspended', 'cancelled'])->default('active');
            $table->integer('visits_used')->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);

            $table->boolean('auto_renew')->default(true);
            $table->date('next_billing_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================================================
        //  5) أوامر الشغل (Work Orders)
        // ============================================================
        Schema::create('sahab_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('sahab_customers');
            $table->foreignId('assigned_to')->nullable()->constrained('sahab_employees');

            $table->string('order_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('type', [
                'service',      // خدمة عامة
                'repair',       // إصلاح
                'maintenance',  // صيانة
                'installation', // تركيب
                'consultation', // استشارة
                'manufacturing',// تصنيع
                'other',
            ])->default('service');

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', [
                'pending', 'scheduled', 'in_progress', 'on_hold',
                'completed', 'cancelled', 'invoiced',
            ])->default('pending');

            $table->datetime('scheduled_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->integer('estimated_hours')->nullable();
            $table->decimal('actual_hours', 8, 2)->default(0);

            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('actual_cost', 12, 2)->default(0);
            $table->decimal('quoted_price', 12, 2)->nullable();

            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices');
            $table->json('attachments')->nullable();
            $table->text('completion_notes')->nullable();
            $table->integer('customer_rating')->nullable();
            $table->text('customer_feedback')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('sahab_work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('sahab_work_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('sahab_products');
            $table->string('description');
            $table->enum('type', ['part', 'service', 'labor', 'expense'])->default('part');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit')->default('piece');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->boolean('is_billable')->default(true);
            $table->timestamps();
        });

        // ============================================================
        //  6) الحجوزات (Bookings)
        // ============================================================
        Schema::create('sahab_bookable_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['room', 'table', 'equipment', 'employee', 'vehicle', 'service', 'unit', 'other']);
            $table->text('description')->nullable();
            $table->integer('capacity')->default(1);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->decimal('booking_rate', 10, 2)->nullable();
            $table->json('working_hours')->nullable();
            $table->json('availability_rules')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sahab_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('sahab_bookable_resources');
            $table->foreignId('customer_id')->nullable()->constrained('sahab_customers');

            $table->string('booking_number')->unique();
            $table->datetime('start_at');
            $table->datetime('end_at');
            $table->integer('duration_minutes');
            $table->integer('guest_count')->default(1);

            $table->enum('status', [
                'pending', 'confirmed', 'checked_in', 'checked_out',
                'completed', 'cancelled', 'no_show',
            ])->default('pending');

            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('deposit_paid', 10, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'deposit_paid', 'paid', 'refunded'])->default('unpaid');

            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices');
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();
            $table->json('reminders_sent')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'start_at', 'status']);
        });

        // ============================================================
        //  7) تتبّع الوقت (Time Tracking)
        // ============================================================
        Schema::create('sahab_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees');
            $table->foreignId('work_order_id')->nullable()->constrained('sahab_work_orders');
            $table->foreignId('customer_id')->nullable()->constrained('sahab_customers');

            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->decimal('duration_hours', 6, 2)->default(0);

            $table->string('task_description');
            $table->boolean('is_billable')->default(true);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->enum('status', ['running', 'paused', 'completed', 'invoiced'])->default('running');
            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices');

            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'date']);
        });

        // ============================================================
        //  8) العقود والإيجارات (Rentals/Contracts)
        // ============================================================
        Schema::create('sahab_rental_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('unit_number');
            $table->enum('type', ['apartment', 'shop', 'office', 'warehouse', 'villa', 'other']);
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->integer('rooms')->nullable();
            $table->decimal('default_rent', 10, 2);
            $table->enum('status', ['available', 'rented', 'maintenance', 'reserved'])->default('available');
            $table->json('amenities')->nullable();
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sahab_rental_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('sahab_rental_units');
            $table->foreignId('customer_id')->constrained('sahab_customers');

            $table->string('contract_number')->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_rent', 10, 2);
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->decimal('admin_fee', 10, 2)->default(0);
            $table->enum('billing_frequency', ['monthly', 'quarterly', 'biannual', 'yearly'])->default('monthly');

            $table->enum('status', ['active', 'expired', 'terminated', 'pending_renewal'])->default('active');
            $table->date('terminated_at')->nullable();
            $table->text('terms')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // ============================================================
        //  9) الهيكل التنظيمي (Organizational Structure)
        // ============================================================
        Schema::create('sahab_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->references('id')->on('sahab_departments');
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->foreignId('manager_id')->nullable()->references('id')->on('sahab_employees');
            $table->text('description')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sahab_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('sahab_departments');
            $table->string('title');
            $table->string('code', 20)->nullable();
            $table->decimal('min_salary', 10, 2)->nullable();
            $table->decimal('max_salary', 10, 2)->nullable();
            $table->json('required_skills')->nullable();
            $table->json('responsibilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        //  10) الأذون المخزنية (Stock Permits/Vouchers)
        // ============================================================
        Schema::create('sahab_stock_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('sahab_outlets');

            $table->string('permit_number')->unique();
            $table->enum('type', [
                'in',           // إذن استلام
                'out',          // إذن صرف
                'transfer',     // إذن تحويل
                'adjustment',   // إذن تسوية
                'return',       // إذن مرتجع
                'damage',       // إذن تالف
            ]);

            $table->date('permit_date');
            $table->foreignId('source_outlet_id')->nullable()->references('id')->on('sahab_outlets');
            $table->foreignId('destination_outlet_id')->nullable()->references('id')->on('sahab_outlets');
            $table->foreignId('employee_id')->nullable()->references('id')->on('sahab_employees');
            $table->string('reference_type')->nullable()->comment('purchase, sale, transfer');
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'completed'])->default('draft');
            $table->foreignId('approved_by')->nullable()->references('id')->on('users');
            $table->datetime('approved_at')->nullable();

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sahab_stock_permit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('permit_id')->constrained('sahab_stock_permits')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('sahab_products');
            $table->decimal('quantity', 12, 3);
            $table->string('unit')->default('piece');
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ============================================================
        //  11) قوالب الواتساب (لرسائل OTP والتنبيهات)
        // ============================================================
        Schema::create('sahab_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique()->comment('otp_register, otp_login, ...');
            $table->string('name');
            $table->text('content_ar');
            $table->text('content_en')->nullable();
            $table->json('variables')->nullable()->comment('قائمة المتغيّرات: {{name}}, {{code}}, ...');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_whatsapp_templates');
        Schema::dropIfExists('sahab_stock_permit_items');
        Schema::dropIfExists('sahab_stock_permits');
        Schema::dropIfExists('sahab_positions');
        Schema::dropIfExists('sahab_departments');
        Schema::dropIfExists('sahab_rental_contracts');
        Schema::dropIfExists('sahab_rental_units');
        Schema::dropIfExists('sahab_time_entries');
        Schema::dropIfExists('sahab_bookings');
        Schema::dropIfExists('sahab_bookable_resources');
        Schema::dropIfExists('sahab_work_order_items');
        Schema::dropIfExists('sahab_work_orders');
        Schema::dropIfExists('sahab_memberships');
        Schema::dropIfExists('sahab_membership_plans');
        Schema::dropIfExists('sahab_installment_payments');
        Schema::dropIfExists('sahab_installments');
        Schema::dropIfExists('sahab_sales_targets');
        Schema::dropIfExists('sahab_otp_codes');
    }
};
