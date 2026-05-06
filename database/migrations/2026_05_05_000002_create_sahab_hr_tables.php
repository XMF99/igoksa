<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 002 — الموارد البشريّة (HR — مستوحاة من دفترة)
 * --------------------------------------------------------------------
 *  المميزات المُضافة من دفترة:
 *  ✓ متابعة الوثائق الرسميّة (إقامة، رخصة عمل، جواز سفر، تأمين)
 *  ✓ متابعة وثائق المحلّ (سجل تجاري، رخصة بلديّة، إيجار، دفاع مدني)
 *  ✓ تنبيهات قبل انتهاء كل وثيقة (60 / 30 / 7 أيام)
 *  ✓ الحضور والانصراف بـ GPS
 *  ✓ اختبار مفاجئ للتحقّق من الموظف
 *  ✓ نظام إجازات + موافقات
 *  ✓ نظام رواتب مرتبط بالحضور
 * --------------------------------------------------------------------
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // 1) الموظّفون
        // ============================================================
        Schema::create('sahab_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->comment('FK للـ users في ERPGo');

            // البيانات الشخصيّة
            $table->string('employee_code', 20);
            $table->string('full_name');
            $table->string('full_name_en')->nullable();
            $table->string('national_id', 20)->nullable()->comment('الهويّة/الإقامة');
            $table->string('mobile', 20)->index();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('nationality', 30)->nullable();

            // الوظيفيّة
            $table->string('position');
            $table->string('department', 60)->nullable();
            $table->date('hire_date');
            $table->date('contract_end_date')->nullable();
            $table->enum('contract_type', ['permanent', 'temporary', 'part_time', 'intern'])->default('permanent');

            // المالية
            $table->decimal('basic_salary', 10, 2)->default(0);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);

            // البنك (للراتب)
            $table->string('bank_name', 60)->nullable();
            $table->string('iban', 30)->nullable();

            // الصورة والتوقيع
            $table->string('photo')->nullable();
            $table->string('signature')->nullable();

            // الحضور
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->boolean('gps_required')->default(true);

            // الحالة
            $table->enum('status', ['active', 'on_leave', 'suspended', 'terminated'])->default('active');
            $table->date('termination_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'employee_code']);
            $table->index(['tenant_id', 'status']);
        });

        // ============================================================
        // 2) وثائق الموظّفين
        // ============================================================
        Schema::create('sahab_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();

            $table->enum('document_type', [
                'iqama',           // الإقامة
                'work_permit',     // رخصة العمل
                'passport',        // جواز السفر
                'health_insurance',// التأمين الصحّي
                'driver_license',  // رخصة القيادة
                'gosi',            // التأمينات الاجتماعيّة
                'medical_check',   // الفحص الطبي
                'training_cert',   // شهادة تدريب
                'food_handler',    // شهادة سلامة غذائيّة
                'contract',        // العقد
                'other',
            ]);

            $table->string('document_number', 60)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('issuing_authority')->nullable();
            $table->string('file_path')->nullable();

            // التنبيهات
            $table->boolean('alert_60_days')->default(true);
            $table->boolean('alert_30_days')->default(true);
            $table->boolean('alert_7_days')->default(true);
            $table->timestamp('last_alert_sent_at')->nullable();

            // حالة الموافقة (لمّا الموظّف يرفع تحديث)
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('approved');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expiry_date']);
        });

        // ============================================================
        // 3) وثائق المحلّ
        // ============================================================
        Schema::create('sahab_business_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('sahab_outlets')->cascadeOnDelete();

            $table->enum('document_type', [
                'commercial_registration', // السجل التجاري
                'municipal_license',        // الرخصة البلديّة
                'rent_contract',            // عقد الإيجار
                'civil_defense',            // الدفاع المدني
                'zakat_cert',               // شهادة الزكاة
                'vat_cert',                 // شهادة ضريبة القيمة المضافة
                'health_cert',              // الشهادة الصحّيّة
                'chamber_membership',       // عضويّة الغرفة التجاريّة
                'gosi_cert',                // شهادة التأمينات
                'saudization_cert',         // شهادة السعودة
                'food_license',             // رخصة الأغذية
                'other',
            ]);

            $table->string('document_number', 60)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('issuing_authority')->nullable();
            $table->string('file_path')->nullable();
            $table->decimal('renewal_cost', 10, 2)->nullable();

            $table->boolean('alert_60_days')->default(true);
            $table->boolean('alert_30_days')->default(true);
            $table->boolean('alert_7_days')->default(true);
            $table->timestamp('last_alert_sent_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'expiry_date']);
        });

        // ============================================================
        // 4) سجلّ الحضور والانصراف
        // ============================================================
        Schema::create('sahab_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();
            $table->date('date')->index();

            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();

            // GPS عند تسجيل الدخول
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->integer('check_in_distance_meters')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();

            $table->enum('status', [
                'present', 'late', 'absent', 'half_day', 'on_leave', 'holiday'
            ])->default('present');
            $table->integer('late_minutes')->default(0);
            $table->decimal('worked_hours', 4, 2)->default(0);
            $table->decimal('overtime_hours', 4, 2)->default(0);

            // اختبار مفاجئ
            $table->boolean('surprise_test_required')->default(false);
            $table->boolean('surprise_test_passed')->nullable();
            $table->timestamp('surprise_test_at')->nullable();

            // طريقة التسجيل
            $table->enum('check_in_method', [
                'mobile_app', 'biometric', 'qr_code', 'manual', 'card'
            ])->default('mobile_app');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'date']);
        });

        // ============================================================
        // 5) الإجازات
        // ============================================================
        Schema::create('sahab_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();

            $table->enum('leave_type', [
                'annual',      // سنويّة
                'sick',        // مرضيّة
                'emergency',   // طارئة
                'unpaid',      // بدون راتب
                'maternity',   // أمومة
                'hajj',        // حجّ
                'bereavement', // وفاة
                'marriage',    // زواج
                'other',
            ]);

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days_count');
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        // ============================================================
        // 6) رصيد الإجازات السنوي
        // ============================================================
        Schema::create('sahab_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();
            $table->year('year');
            $table->decimal('annual_total', 5, 2)->default(30);
            $table->decimal('annual_used', 5, 2)->default(0);
            $table->decimal('sick_total', 5, 2)->default(30);
            $table->decimal('sick_used', 5, 2)->default(0);
            $table->decimal('emergency_total', 5, 2)->default(5);
            $table->decimal('emergency_used', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'year']);
        });

        // ============================================================
        // 7) كشوفات الرواتب
        // ============================================================
        Schema::create('sahab_payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('sahab_tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('sahab_employees')->cascadeOnDelete();
            $table->year('year');
            $table->tinyInteger('month');

            // الإيرادات
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->decimal('total_earnings', 10, 2);

            // الخصومات
            $table->decimal('absence_deduction', 10, 2)->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->decimal('loan_deduction', 10, 2)->default(0);
            $table->decimal('insurance_deduction', 10, 2)->default(0);
            $table->decimal('gosi_deduction', 10, 2)->default(0)->comment('التأمينات');
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2);

            // الصافي
            $table->decimal('net_salary', 10, 2);

            // الحضور
            $table->integer('working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);

            // الحالة
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->date('payment_date')->nullable();
            $table->string('payment_reference', 80)->nullable();

            $table->timestamps();
            $table->unique(['tenant_id', 'employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sahab_payrolls');
        Schema::dropIfExists('sahab_leave_balances');
        Schema::dropIfExists('sahab_leaves');
        Schema::dropIfExists('sahab_attendance');
        Schema::dropIfExists('sahab_business_documents');
        Schema::dropIfExists('sahab_employee_documents');
        Schema::dropIfExists('sahab_employees');
    }
};
