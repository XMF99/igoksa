<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 003 — المحاسبة المتقدّمة (مستوحاة من دفترة)
 * --------------------------------------------------------------------
 *  ERPGo فيه SalesInvoice و PurchaseInvoice. لكن دفترة فيه أكثر:
 *  ✓ دليل حسابات هرمي كامل
 *  ✓ القيود اليوميّة المركّبة (Journal Entries)
 *  ✓ مراكز التكلفة (Cost Centers)
 *  ✓ دورة الشيكات الكاملة (وارد/صادر/مرتجع)
 *  ✓ إدارة الأصول الثابتة + الإهلاك
 *  ✓ التسويات البنكيّة
 *  ✓ تقارير: الميزانيّة، الأرباح والخسائر، التدفّق النقدي
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) دليل الحسابات (Chart of Accounts)
        // ============================================================
        Schema::create('sahab_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();

            $table->enum('type', [
                'asset',       // الأصول
                'liability',   // الخصوم
                'equity',      // حقوق الملكيّة
                'revenue',     // الإيرادات
                'expense',     // المصروفات
            ]);

            $table->enum('subtype', [
                // أصول
                'current_asset', 'fixed_asset', 'intangible_asset',
                // خصوم
                'current_liability', 'long_term_liability',
                // أخرى
                'equity', 'operating_revenue', 'other_revenue',
                'cogs',  // تكلفة البضاعة المباعة
                'operating_expense', 'other_expense',
            ])->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('sahab_accounts')->nullOnDelete();
            $table->integer('level')->default(1);
            $table->string('path', 200)->nullable()->comment('1.5.10.20 hierarchy');

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');

            $table->boolean('allow_transactions')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
        });

        // ============================================================
        // 2) القيود اليوميّة (Journal Entries) — رأس
        // ============================================================
        Schema::create('sahab_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('entry_number', 30);
            $table->date('entry_date')->index();

            $table->enum('type', [
                'manual',      // يدوي
                'sale',        // من فاتورة بيع
                'purchase',    // من فاتورة شراء
                'payment',     // تحصيل/سداد
                'payroll',     // رواتب
                'depreciation',// إهلاك
                'opening',     // قيد افتتاحي
                'closing',     // قيد إقفال
            ])->default('manual');

            $table->string('reference_type', 60)->nullable()->comment('Polymorphic ref');
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('description');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);

            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('posted');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();
            $table->unique(['tenant_id', 'entry_number']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ============================================================
        // 3) سطور القيود اليوميّة
        // ============================================================
        Schema::create('sahab_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('sahab_journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('sahab_accounts');
            $table->foreignId('cost_center_id')->nullable();

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->text('description')->nullable();

            $table->integer('line_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'account_id']);
        });

        // ============================================================
        // 4) مراكز التكلفة (Cost Centers)
        // ============================================================
        Schema::create('sahab_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('sahab_cost_centers')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable();
            $table->decimal('budget_monthly', 12, 2)->default(0);
            $table->decimal('budget_annual', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        // ============================================================
        // 5) الشيكات (مستوحى من دفترة)
        // ============================================================
        Schema::create('sahab_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();

            $table->enum('direction', ['received', 'issued']);
            $table->string('check_number', 30);
            $table->string('bank_name', 60);
            $table->string('account_number', 30)->nullable();
            $table->date('check_date');
            $table->date('due_date')->index();

            $table->decimal('amount', 12, 2);
            $table->string('payee_name')->nullable()->comment('للصادر: المستفيد');
            $table->string('issuer_name')->nullable()->comment('للوارد: المُصدِر');

            $table->enum('status', [
                'pending',     // معلّق (لم يستحقّ بعد)
                'deposited',   // مُودَع
                'cleared',     // محصّل
                'bounced',     // مرتجع
                'cancelled',   // ملغى
                'replaced',    // مستبدل
            ])->default('pending');

            $table->date('cleared_date')->nullable();
            $table->date('bounced_date')->nullable();
            $table->text('bounce_reason')->nullable();

            $table->string('attachment')->nullable();
            $table->text('notes')->nullable();

            // الحسابات المرتبطة
            $table->foreignId('account_id')->nullable()->constrained('sahab_accounts');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'status', 'due_date']);
        });

        // ============================================================
        // 6) الأصول الثابتة (Fixed Assets) — مستوحى من دفترة
        // ============================================================
        Schema::create('sahab_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('sahab_cost_centers')->nullOnDelete();

            $table->string('code', 30);
            $table->string('name');
            $table->string('serial_number', 80)->nullable();
            $table->string('barcode', 80)->nullable();

            $table->enum('category', [
                'building', 'land', 'machinery', 'vehicle',
                'equipment', 'furniture', 'computer', 'software', 'other'
            ]);

            $table->decimal('purchase_cost', 12, 2);
            $table->date('purchase_date');
            $table->decimal('salvage_value', 12, 2)->default(0);
            $table->integer('useful_life_years')->default(5);

            $table->enum('depreciation_method', [
                'straight_line', 'declining_balance', 'units_of_production', 'none'
            ])->default('straight_line');

            $table->decimal('accumulated_depreciation', 12, 2)->default(0);
            $table->decimal('current_value', 12, 2);

            $table->foreignId('assigned_to_employee_id')->nullable()->constrained('sahab_employees')->nullOnDelete();
            $table->date('assigned_date')->nullable();

            $table->enum('status', ['active', 'in_maintenance', 'disposed', 'sold', 'lost'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_amount', 12, 2)->nullable();

            $table->string('photo')->nullable();
            $table->string('warranty_until')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 7) سجلّ الإهلاكات الشهريّة
        // ============================================================
        Schema::create('sahab_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('sahab_assets')->cascadeOnDelete();
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('depreciation_amount', 12, 2);
            $table->decimal('book_value_after', 12, 2);
            $table->foreignId('journal_entry_id')->nullable()->constrained('sahab_journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'asset_id', 'year', 'month']);
        });

        // ============================================================
        // 8) الحسابات البنكيّة
        // ============================================================
        Schema::create('sahab_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->string('account_name');
            $table->string('bank_name', 60);
            $table->string('account_number', 30);
            $table->string('iban', 30)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->enum('currency', ['SAR', 'USD', 'EUR', 'AED', 'KWD', 'BHD'])->default('SAR');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->foreignId('account_id')->nullable()->constrained('sahab_accounts');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // 9) التسويات البنكيّة (Bank Reconciliation)
        // ============================================================
        Schema::create('sahab_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('sahab_bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('reference', 60)->nullable();
            $table->text('description');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('running_balance', 12, 2);
            $table->boolean('is_reconciled')->default(false);
            $table->date('reconciled_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'bank_account_id', 'transaction_date']);
        });

        // FK للـ cost_center في journal_lines
        Schema::table('sahab_journal_lines', function (Blueprint $table) {
            $table->foreign('cost_center_id')->references('id')->on('sahab_cost_centers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sahab_journal_lines', fn(Blueprint $t) => $t->dropForeign(['cost_center_id']));
        Schema::dropIfExists('sahab_bank_transactions');
        Schema::dropIfExists('sahab_bank_accounts');
        Schema::dropIfExists('sahab_asset_depreciations');
        Schema::dropIfExists('sahab_assets');
        Schema::dropIfExists('sahab_checks');
        Schema::dropIfExists('sahab_cost_centers');
        Schema::dropIfExists('sahab_journal_lines');
        Schema::dropIfExists('sahab_journal_entries');
        Schema::dropIfExists('sahab_accounts');
    }
};
