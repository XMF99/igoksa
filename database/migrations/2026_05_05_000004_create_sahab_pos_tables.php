<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 004 — نقطة البيع والمخزون
 * --------------------------------------------------------------------
 *  ✓ المنتجات والتصنيفات (مع المُعدِّلات/Modifiers للمطاعم)
 *  ✓ المخزون متعدّد المستودعات
 *  ✓ العملاء والموردين
 *  ✓ الفواتير (مبيعات + مشتريات)
 *  ✓ الورديّات (Cashier Shifts)
 *  ✓ المدفوعات وطرق الدفع
 *  ✓ المرتجعات
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) تصنيفات المنتجات
        // ============================================================
        Schema::create('sahab_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('sahab_categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('icon', 30)->nullable();
            $table->string('color', 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        // ============================================================
        // 2) المنتجات
        // ============================================================
        Schema::create('sahab_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('sahab_categories')->nullOnDelete();

            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('sku', 60)->nullable();
            $table->string('barcode', 60)->nullable()->index();
            $table->text('description')->nullable();

            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);

            $table->enum('unit', ['piece', 'kg', 'gram', 'liter', 'meter', 'box', 'pack'])->default('piece');
            $table->decimal('min_quantity', 10, 3)->default(1);

            // الضريبة
            $table->boolean('vat_included')->default(true);
            $table->decimal('vat_rate', 5, 2)->default(15.00);

            // المخزون
            $table->boolean('track_inventory')->default(true);
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->decimal('low_stock_threshold', 10, 3)->default(5);

            // للمطاعم
            $table->boolean('has_modifiers')->default(false);
            $table->boolean('is_combo')->default(false);
            $table->integer('preparation_time_minutes')->nullable();
            $table->json('allergens')->nullable();
            $table->json('nutritional_info')->nullable();

            // للتطبيقات الخارجيّة
            $table->boolean('available_in_delivery_apps')->default(true);
            $table->json('external_ids')->nullable()->comment('IDs in HungerStation, Jahez, etc.');

            // الصورة
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active']);
        });

        // ============================================================
        // 3) المُعدِّلات (Modifiers) — للمطاعم
        // ============================================================
        Schema::create('sahab_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->integer('min_selections')->default(0);
            $table->integer('max_selections')->default(1);
            $table->timestamps();
        });

        Schema::create('sahab_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('sahab_modifier_groups')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('extra_price', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // علاقة المنتجات بالمُعدِّلات
        Schema::create('sahab_product_modifier_groups', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('sahab_products')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained('sahab_modifier_groups')->cascadeOnDelete();
            $table->primary(['product_id', 'modifier_group_id']);
        });

        // ============================================================
        // 4) العملاء
        // ============================================================
        Schema::create('sahab_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('mobile', 20)->index();
            $table->string('email')->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('cr_number', 30)->nullable();

            // العنوان
            $table->text('address')->nullable();
            $table->string('city', 60)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // النوع
            $table->enum('customer_type', ['individual', 'business'])->default('individual');

            // الائتمان
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->integer('payment_terms_days')->default(0);

            // الولاء
            $table->integer('loyalty_points')->default(0);
            $table->enum('loyalty_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->integer('total_orders')->default(0);

            // إحصائيّات
            $table->date('last_order_date')->nullable();
            $table->date('birth_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'mobile']);
        });

        // ============================================================
        // 5) الموردين
        // ============================================================
        Schema::create('sahab_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('mobile', 20)->index();
            $table->string('email')->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('cr_number', 30)->nullable();
            $table->text('address')->nullable();

            $table->decimal('current_balance', 12, 2)->default(0);
            $table->integer('payment_terms_days')->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // ============================================================
        // 6) الورديّات (Cashier Shifts)
        // ============================================================
        Schema::create('sahab_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('sahab_outlets');
            $table->foreignId('terminal_id')->nullable()->constrained('sahab_pos_terminals');
            $table->foreignId('cashier_id')->comment('FK to ERPGo users');

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash', 12, 2)->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('cash_variance', 12, 2)->default(0);

            // الإحصائيّات
            $table->integer('invoice_count')->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_vat', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_refunds', 12, 2)->default(0);
            $table->json('payment_breakdown')->nullable();

            $table->enum('status', ['open', 'closed', 'reconciled'])->default('open');
            $table->text('closing_notes')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'cashier_id', 'status']);
        });

        // ============================================================
        // 7) فواتير المبيعات (POS Invoices)
        // ============================================================
        Schema::create('sahab_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('sahab_outlets');
            $table->foreignId('terminal_id')->nullable()->constrained('sahab_pos_terminals');
            $table->foreignId('shift_id')->nullable()->constrained('sahab_shifts');
            $table->foreignId('cashier_id');
            $table->foreignId('customer_id')->nullable()->constrained('sahab_customers');

            $table->string('invoice_number', 30)->unique();
            $table->timestamp('invoice_date');

            // المصدر
            $table->enum('source', [
                'pos', 'whatsapp', 'website', 'mobile_app',
                'hungerstation', 'jahez', 'toshhel', 'mrsool',
                'thechefz', 'ninja', 'toyou', 'talabat', 'other'
            ])->default('pos');
            $table->string('source_order_id', 80)->nullable();

            // النوع
            $table->enum('order_type', ['dine_in', 'takeaway', 'delivery'])->default('dine_in');
            $table->string('table_number', 20)->nullable();
            $table->integer('guests_count')->nullable();

            // المبالغ
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);

            // الحالة
            $table->enum('status', [
                'pending', 'preparing', 'ready', 'completed',
                'cancelled', 'refunded', 'partial_refund'
            ])->default('completed');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('paid');

            // الزكاة
            $table->text('zatca_qr_code')->nullable();
            $table->string('zatca_uuid', 36)->nullable();
            $table->string('zatca_hash', 128)->nullable();
            $table->boolean('zatca_submitted')->default(false);
            $table->timestamp('zatca_submitted_at')->nullable();

            // عام
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'invoice_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'source']);
        });

        // ============================================================
        // 8) عناصر الفاتورة
        // ============================================================
        Schema::create('sahab_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('sahab_invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('sahab_products');
            $table->string('product_name');
            $table->string('product_sku', 60)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->json('modifiers')->nullable();
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 9) المدفوعات
        // ============================================================
        Schema::create('sahab_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('sahab_invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('sahab_shifts');

            $table->enum('method', [
                'cash', 'mada', 'visa', 'mastercard',
                'apple_pay', 'stc_pay', 'urpay', 'tabby', 'tamara',
                'bank_transfer', 'check', 'wallet_balance', 'mixed'
            ]);

            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->nullable();
            $table->string('transaction_id', 80)->nullable();
            $table->json('metadata')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->timestamp('paid_at');

            $table->timestamps();
            $table->index(['tenant_id', 'method', 'status']);
        });

        // ============================================================
        // 10) فواتير المشتريات
        // ============================================================
        Schema::create('sahab_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets');
            $table->foreignId('supplier_id')->constrained('sahab_suppliers');

            $table->string('purchase_number', 30)->unique();
            $table->string('supplier_invoice_number', 30)->nullable();
            $table->date('purchase_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);

            $table->enum('status', ['draft', 'received', 'partial_received', 'cancelled'])->default('received');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');

            // OCR (مستوحى من دفترة)
            $table->boolean('from_ocr')->default(false);
            $table->string('ocr_image_path')->nullable();
            $table->json('ocr_raw_data')->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'purchase_date']);
        });

        Schema::create('sahab_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('sahab_purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('sahab_products');
            $table->string('product_name');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->date('expiry_date')->nullable();
            $table->string('batch_number', 40)->nullable();
            $table->timestamps();
        });

        // ============================================================
        // 11) حركات المخزون
        // ============================================================
        Schema::create('sahab_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('sahab_products')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets');

            $table->enum('movement_type', [
                'sale', 'purchase', 'return_in', 'return_out',
                'transfer_in', 'transfer_out', 'adjustment_in', 'adjustment_out',
                'production', 'waste', 'damaged'
            ]);

            $table->decimal('quantity', 10, 3);
            $table->decimal('balance_before', 10, 3);
            $table->decimal('balance_after', 10, 3);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);

            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_stock_movements');
        Schema::dropIfExists('sahab_purchase_items');
        Schema::dropIfExists('sahab_purchases');
        Schema::dropIfExists('sahab_payments');
        Schema::dropIfExists('sahab_invoice_items');
        Schema::dropIfExists('sahab_invoices');
        Schema::dropIfExists('sahab_shifts');
        Schema::dropIfExists('sahab_suppliers');
        Schema::dropIfExists('sahab_customers');
        Schema::dropIfExists('sahab_product_modifier_groups');
        Schema::dropIfExists('sahab_modifiers');
        Schema::dropIfExists('sahab_modifier_groups');
        Schema::dropIfExists('sahab_products');
        Schema::dropIfExists('sahab_categories');
    }
};
