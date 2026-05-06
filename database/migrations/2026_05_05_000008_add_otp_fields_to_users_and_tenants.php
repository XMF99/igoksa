<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مهاجرة سحاب 008 — إضافة حقول التحقّق من الجوّال
 *  للمستأجرين والمستخدمين والموظّفين
 */
return new class extends Migration
{
    public function up(): void
    {
        // sahab_tenants
        Schema::table('sahab_tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('sahab_tenants', 'mobile_verified_at')) {
                $table->timestamp('mobile_verified_at')->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('sahab_tenants', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable();
            }
        });

        // users (Laravel default — قد يحتاج إضافة)
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'mobile')) {
                    $table->string('mobile', 20)->nullable()->after('email');
                }
                if (!Schema::hasColumn('users', 'mobile_verified_at')) {
                    $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
                }
                if (!Schema::hasColumn('users', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('users', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->after('tenant_id');
                }
            });
        }

        // sahab_employees — إضافة email لو ما هو موجود
        Schema::table('sahab_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('sahab_employees', 'role')) {
                $table->string('role', 30)->default('cashier')->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahab_tenants', function (Blueprint $table) {
            $table->dropColumn(['mobile_verified_at', 'last_active_at']);
        });

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['mobile', 'mobile_verified_at', 'tenant_id', 'employee_id']);
            });
        }
    }
};
