<?php

namespace Database\Seeders;

use App\Models\Sahab\Plan;
use App\Models\Sahab\Tenant;
use App\Models\Sahab\Outlet;
use App\Models\Sahab\Category;
use App\Models\Sahab\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * SahabSeeder — البيانات الأوّليّة للنظام
 *  - 3 باقات
 *  - الأدوار والصلاحيّات
 *  - مستأجر تجريبي
 *  - منتجات نموذجيّة
 */
class SahabSeeder extends Seeder
{
    public function run(): void
    {
        $this->createPlans();
        $this->createRoles();
        $this->createDemoTenant();
    }

    // ============================================================
    //  1) الباقات الـ3
    // ============================================================
    protected function createPlans(): void
    {
        // باقة البداية — 69 ر.س
        Plan::updateOrCreate(['slug' => 'starter'], [
            'name_ar'        => 'البداية',
            'name_en'        => 'Starter',
            'description_ar' => 'لمحلّ صغير أو بدء جديد',
            'price_monthly'  => 69,
            'price_annual'   => 69 * 12 * 0.85,
            'max_outlets'    => 1,
            'max_users'      => 2,
            'max_products'   => 0,
            'max_invoices_per_month' => 0,
            'ai_quota_monthly' => 0,
            'ocr_quota_monthly' => 0,
            'features' => [
                // المبيعات
                'pos',                          // كاشير POS
                'invoices',                     // الفواتير
                'zatca_phase1',                 // الفاتورة الإلكترونيّة (مرحلة 1)
                'thermal_print',                // طباعة حراريّة

                // العملاء
                'customers',                    // إدارة العملاء
                'customer_followup',            // متابعة العملاء (الديون والأرصدة)

                // المخزون
                'products',                     // إدارة المنتجات
                'inventory',                    // إدارة المخزون
                'inventory_basic',              // الجرد الأساسي
                'suppliers',                    // الموردون
                'purchases_basic',              // المشتريات الأساسيّة

                // الحسابات الأساسيّة
                'expenses',                     // المصروفات

                // الدعم
                'whatsapp_support',             // دعم على واتساب
                // ملاحظة: التقارير الذكيّة، AI، تطبيقات التوصيل، متجر الواتساب → غير متاحة
            ],
            'is_popular'    => false,
            'is_active'     => true,
            'display_order' => 1,
        ]);

        // باقة الاحترافيّة — 159 ر.س ⭐
        Plan::updateOrCreate(['slug' => 'pro'], [
            'name_ar'        => 'الاحترافيّة',
            'name_en'        => 'Professional',
            'description_ar' => 'للمطاعم والمحلّات النامية',
            'price_monthly'  => 159,
            'price_annual'   => 159 * 12 * 0.85,
            'max_outlets'    => 3,
            'max_users'      => 10,
            'max_products'   => 0,
            'max_invoices_per_month' => 0,
            'ai_quota_monthly' => 1000,
            'ocr_quota_monthly' => 100,
            'features' => [
                // كل ميزات البداية
                'pos', 'invoices', 'zatca_phase1', 'thermal_print',
                'customers', 'customer_followup',
                'products', 'inventory', 'inventory_basic', 'suppliers', 'purchases_basic',
                'expenses', 'whatsapp_support',

                // المبيعات المتقدّمة
                'delivery_apps_integration',    // ربط 8 تطبيقات توصيل
                'kds',                          // شاشة المطبخ
                'price_offers',                 // العروض
                'sales_targets',                // المبيعات المستهدفة والعمولات
                'installments',                 // الأقساط

                // العملاء المتقدّمة
                'loyalty_program',              // نقاط الولاء
                'memberships',                  // الاشتراكات والعضويّات
                'customer_credits',             // النقاط والأرصدة

                // المخزون المتقدّم
                'inventory_advanced',           // الجرد المتقدّم
                'stock_permits',                // الأذون المخزنيّة
                'purchase_cycle',               // دورة المشتريات

                // الحسابات
                'chart_of_accounts',            // دليل الحسابات
                'journal_entries',              // قيود اليوميّة
                'cost_centers',                 // مراكز التكلفة
                'cheque_cycle',                 // دورة الشيكات

                // الموارد البشريّة
                'employees_management',         // شؤون الموظّفين
                'attendance',                   // الحضور والانصراف
                'gps_attendance',               // حضور بـ GPS
                'employee_contracts',           // العقود
                'employee_requests',            // الطلبات (إجازات، استئذان)
                'document_tracking',            // متابعة الوثائق

                // التشغيل
                'work_orders',                  // أوامر الشغل
                'time_tracking',                // تتبّع الوقت
                'bookings',                     // الحجوزات

                // الذكاء والتقارير
                'smart_reports',                // ⭐ التقارير الذكيّة
                'smart_reports_whatsapp',       // إرسال على واتساب
                'ai_assistant',                 // المساعد الذكي
                'ocr_invoices',                 // قراءة فواتير

                // الأمان
                'fraud_detection',              // كاشف احتيال
                'multi_outlet',                 // فروع متعدّدة
                'otp_login',                    // دخول بكود واتساب
            ],
            'is_popular'    => true,
            'is_active'     => true,
            'display_order' => 2,
        ]);

        // باقة المؤسّسيّة — 199 ر.س
        Plan::updateOrCreate(['slug' => 'enterprise'], [
            'name_ar'        => 'المؤسّسيّة',
            'name_en'        => 'Enterprise',
            'description_ar' => 'للسلاسل والشركات الكبيرة + متجر واتساب',
            'price_monthly'  => 199,
            'price_annual'   => 199 * 12 * 0.85,
            'max_outlets'    => 0,
            'max_users'      => 0,
            'max_products'   => 0,
            'max_invoices_per_month' => 0,
            'ai_quota_monthly' => 5000,
            'ocr_quota_monthly' => 0,
            'features' => [
                // ✓ كل ميزات الباقتين السابقتين
                'all', 'pos', 'invoices', 'zatca_phase1', 'thermal_print',
                'customers', 'customer_followup', 'products', 'inventory',
                'suppliers', 'expenses', 'whatsapp_support',
                'delivery_apps_integration', 'kds',
                'price_offers', 'sales_targets', 'installments',
                'loyalty_program', 'memberships', 'customer_credits',
                'inventory_advanced', 'stock_permits', 'purchase_cycle',
                'chart_of_accounts', 'journal_entries', 'cost_centers', 'cheque_cycle',
                'employees_management', 'attendance', 'gps_attendance',
                'employee_contracts', 'employee_requests', 'document_tracking',
                'work_orders', 'time_tracking', 'bookings',
                'smart_reports', 'smart_reports_whatsapp', 'ai_assistant', 'ocr_invoices',
                'fraud_detection', 'multi_outlet', 'otp_login',

                // 🌟 الميزات الحصريّة للمؤسّسيّة:
                'zatca_phase2',                 // الفاتورة الإلكترونيّة (مرحلة 2)
                'whatsapp_store',               // 🛍 متجر الواتساب
                'custom_domain',                // دومين مخصّص
                'apple_wallet',                 // Apple Wallet للولاء
                'geofencing',                   // Geofencing
                'surprise_tests',               // اختبار مفاجئ للموظّفين

                // أوامر الشغل المتقدّمة
                'manufacturing',                // إدارة التصنيع
                'service_workflow',             // دورة العمل (Workflow)

                // الإيجارات
                'rental_units',                 // إدارة الإيجارات والوحدات
                'rental_contracts',             // عقود الإيجار

                // الموارد البشريّة الكاملة
                'organizational_structure',     // الهيكل التنظيمي
                'payroll_full',                 // إدارة المرتبات الكاملة
                'employee_documents',           // وثائق الموظّفين
                'medical_insurance',            // التأمين الطبي للعملاء

                // الحسابات الكاملة
                'fixed_assets',                 // إدارة الأصول
                'asset_depreciation',           // إهلاك الأصول
                'bank_reconciliation',          // التسويات البنكيّة

                // التحليلات والـ AI
                'predictive_analytics',         // توقّعات ذكيّة
                'monthly_reports',              // تقارير شهريّة PDF
                'custom_reports',               // تقارير مخصّصة

                // الـ ERP الكامل
                'erp_full', 'accounting_full', 'hrm_full', 'crm',

                // التكامل
                'api_access',                   // وصول API
                'custom_integrations',          // تكاملات مخصّصة
                'webhooks',                     // Webhooks

                // الدعم
                'dedicated_manager',            // مدير حساب مخصّص
                '24x7_support',                 // دعم 24/7
                'onboarding',                   // جلسة تأسيس
                'priority_support',             // دعم بالأولويّة
            ],
            'is_popular'    => false,
            'is_active'     => true,
            'display_order' => 3,
        ]);

        $this->command->info('✓ 3 باقات أُنشئت');
    }

    // ============================================================
    //  2) الأدوار والصلاحيّات
    // ============================================================
    protected function createRoles(): void
    {
        if (!class_exists(Role::class)) {
            $this->command->warn('Spatie\Permission غير مثبّت');
            return;
        }

        $permissions = [
            // POS
            'pos.access', 'pos.create_invoice', 'pos.refund',
            'pos.discount', 'pos.open_shift', 'pos.close_shift',
            // Products & Inventory
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'inventory.adjust', 'inventory.view',
            // Customers
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            // HR
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'attendance.view', 'attendance.manage',
            'documents.view', 'documents.manage',
            'leaves.approve', 'payroll.process',
            // Reports
            'reports.view', 'reports.export', 'reports.subscribe',
            // Accounting
            'accounting.view', 'accounting.entries', 'accounting.reports',
            // Delivery
            'delivery.configure', 'delivery.manage_orders',
            // Settings
            'settings.view', 'settings.edit',
            'tenant.manage', 'users.manage',
            // Admin
            'admin.access', 'admin.manage_tenants',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // الأدوار
        $owner = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner->syncPermissions($permissions);

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions(array_filter($permissions, fn($p) => !str_starts_with($p, 'admin.') && $p !== 'tenant.manage'));

        $cashier = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier->syncPermissions([
            'pos.access', 'pos.create_invoice', 'pos.open_shift', 'pos.close_shift',
            'products.view', 'customers.view', 'customers.create',
        ]);

        $accountant = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);
        $accountant->syncPermissions([
            'accounting.view', 'accounting.entries', 'accounting.reports',
            'reports.view', 'reports.export', 'documents.view',
        ]);

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $this->command->info('✓ الأدوار والصلاحيّات أُنشئت');
    }

    // ============================================================
    //  3) مستأجر تجريبي
    // ============================================================
    protected function createDemoTenant(): void
    {
        $proPlan = Plan::where('slug', 'pro')->first();

        $tenant = Tenant::firstOrCreate(
            ['mobile' => '966500000000'],
            [
                'name'         => 'مطعم البيت السعودي',
                'name_en'      => 'Saudi Home Restaurant',
                'owner_name'   => 'أبو محمد',
                'email'        => 'demo@sahab.sa',
                'cr_number'    => '1010101010',
                'vat_number'   => '300000000000003',
                'industry'     => 'restaurant',
                'city'         => 'جدّة',
                'plan_id'      => $proPlan?->id,
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addYear(),
                'billing_cycle' => 'annual',
            ]
        );

        // مستخدم المالك
        $owner = User::firstOrCreate(
            ['email' => 'demo@sahab.sa'],
            [
                'name'      => 'أبو محمد',
                'password'  => Hash::make('password'),
                'tenant_id' => $tenant->id,
            ]
        );

        if (method_exists($owner, 'assignRole')) {
            $owner->assignRole('owner');
        }

        // أصناف المنتجات
        $categoryNames = [
            ['name' => 'وجبات رئيسيّة', 'icon' => '🍛'],
            ['name' => 'مقبّلات', 'icon' => '🥗'],
            ['name' => 'مشروبات', 'icon' => '🥤'],
            ['name' => 'حلويّات', 'icon' => '🍰'],
        ];

        $categories = [];
        foreach ($categoryNames as $i => $cat) {
            $categories[] = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $cat['name']],
                ['icon' => $cat['icon'], 'sort_order' => $i, 'is_active' => true]
            );
        }

        // منتجات نموذجيّة
        $sampleProducts = [
            ['name' => 'كبسة لحم', 'price' => 45, 'cat' => 0, 'stock' => 50],
            ['name' => 'مندي دجاج', 'price' => 38, 'cat' => 0, 'stock' => 40],
            ['name' => 'برياني روبيان', 'price' => 52, 'cat' => 0, 'stock' => 30],
            ['name' => 'مقلوبة', 'price' => 35, 'cat' => 0, 'stock' => 25],
            ['name' => 'سلطة فتوش', 'price' => 18, 'cat' => 1, 'stock' => 100],
            ['name' => 'حمّص بالطحينة', 'price' => 14, 'cat' => 1, 'stock' => 80],
            ['name' => 'متبّل', 'price' => 12, 'cat' => 1, 'stock' => 60],
            ['name' => 'تبّولة', 'price' => 16, 'cat' => 1, 'stock' => 50],
            ['name' => 'ليموناضة', 'price' => 12, 'cat' => 2, 'stock' => 200],
            ['name' => 'عصير برتقال', 'price' => 14, 'cat' => 2, 'stock' => 150],
            ['name' => 'شاي', 'price' => 6, 'cat' => 2, 'stock' => 500],
            ['name' => 'قهوة عربيّة', 'price' => 8, 'cat' => 2, 'stock' => 400],
            ['name' => 'كنافة', 'price' => 22, 'cat' => 3, 'stock' => 30],
            ['name' => 'بقلاوة', 'price' => 18, 'cat' => 3, 'stock' => 50],
            ['name' => 'أم علي', 'price' => 16, 'cat' => 3, 'stock' => 40],
            ['name' => 'كيكة', 'price' => 24, 'cat' => 3, 'stock' => 20],
        ];

        foreach ($sampleProducts as $i => $p) {
            Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $p['name']],
                [
                    'category_id'   => $categories[$p['cat']]->id,
                    'sku'           => 'SKU-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'cost_price'    => $p['price'] * 0.4,
                    'sale_price'    => $p['price'],
                    'unit'          => 'piece',
                    'vat_included'  => true,
                    'vat_rate'      => 15,
                    'track_inventory'=> true,
                    'current_stock' => $p['stock'],
                    'low_stock_threshold' => 10,
                    'is_active'     => true,
                    'available_in_delivery_apps' => true,
                ]
            );
        }

        $this->command->info("✓ مستأجر تجريبي: {$tenant->name} ({$tenant->code})");
        $this->command->info("  الدخول: demo@sahab.sa / password");
    }
}
