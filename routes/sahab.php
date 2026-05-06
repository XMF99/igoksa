<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sahab\TenantController;
use App\Http\Controllers\Sahab\PosController;
use App\Http\Controllers\Sahab\HrController;
use App\Http\Controllers\Sahab\DeliveryController;

/*
|--------------------------------------------------------------------------
|  Sahab Routes
|--------------------------------------------------------------------------
|  المسارات تنقسم لثلاثة أجزاء:
|    1. Public landing (صفحات الإعلانات والاشتراك)
|    2. Authenticated UI (لوحة التحكّم بعد الدخول)
|    3. API (للتطبيقات والـ Webhooks)
*/

// =====================================================================
//  PUBLIC LANDING
// =====================================================================
Route::middleware('web')->group(function () {
    Route::get('/', [TenantController::class, 'landing'])->name('home');
    Route::get('/plans', [TenantController::class, 'plans'])->name('plans');
    Route::post('/signup', [TenantController::class, 'signup'])->name('signup');

    // صفحات المصادقة (Blade views)
    Route::get('/register', fn() => view('sahab.auth.register'))->name('register');
    Route::get('/login', fn() => view('sahab.auth.login'))->name('login');
    Route::get('/forgot-password', fn() => view('sahab.auth.forgot-password'))->name('password.forgot');

    Route::view('/about', 'sahab.landing.about')->name('about');
    Route::view('/contact', 'sahab.landing.contact')->name('contact');
    Route::view('/privacy', 'sahab.landing.privacy')->name('privacy');
    Route::view('/terms', 'sahab.landing.terms')->name('terms');
});

// =====================================================================
//  AUTH API — تسجيل + دخول + OTP + كلمة المرور
// =====================================================================
Route::prefix('api/auth')->middleware('web')->group(function () {

    // التسجيل
    Route::post('/register/start',  [\App\Http\Controllers\Sahab\AuthController::class, 'registerStart']);
    Route::post('/register/verify', [\App\Http\Controllers\Sahab\AuthController::class, 'registerVerify']);

    // الدخول التقليدي
    Route::post('/login', [\App\Http\Controllers\Sahab\AuthController::class, 'login']);

    // الدخول بالـ OTP
    Route::post('/login/otp/start',  [\App\Http\Controllers\Sahab\AuthController::class, 'loginOtpStart']);
    Route::post('/login/otp/verify', [\App\Http\Controllers\Sahab\AuthController::class, 'loginOtpVerify']);

    // إعادة تعيين كلمة المرور
    Route::post('/password/forgot', [\App\Http\Controllers\Sahab\AuthController::class, 'passwordForgot']);
    Route::post('/password/verify', [\App\Http\Controllers\Sahab\AuthController::class, 'passwordVerify']);

    // دخول الموظّف
    Route::post('/employee/login/start',  [\App\Http\Controllers\Sahab\AuthController::class, 'employeeLoginStart']);
    Route::post('/employee/login/verify', [\App\Http\Controllers\Sahab\AuthController::class, 'employeeLoginVerify']);

    // إعادة إرسال OTP
    Route::post('/otp/resend', [\App\Http\Controllers\Sahab\AuthController::class, 'resendOtp']);

    // معلومات الحساب الحالي / الخروج
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',     [\App\Http\Controllers\Sahab\AuthController::class, 'me']);
        Route::post('/logout',[\App\Http\Controllers\Sahab\AuthController::class, 'logout']);
    });
});

// =====================================================================
//  WHATSAPP STORE — العام (للعملاء، بدون auth)
// =====================================================================
Route::middleware('web')->group(function () {
    Route::get('/store/{slug}', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'show'])->name('store.show');
    Route::get('/store/{slug}/product/{id}', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'product']);
    Route::post('/store/{slug}/cart/add', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'addToCart']);
    Route::post('/store/{slug}/cart/coupon', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'applyCoupon']);
    Route::post('/store/{slug}/checkout', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'checkout']);

    // الدفع الإلكتروني للمتجر (Apple Pay / Google Pay / Mada / etc)
    Route::post('/store/{slug}/payment/initiate', [\App\Http\Controllers\Sahab\StorePaymentController::class, 'initiate']);
    Route::post('/store/{slug}/payment/complete/{transactionId}', [\App\Http\Controllers\Sahab\StorePaymentController::class, 'complete']);
    Route::any('/store/{slug}/payment/callback/{transactionId}', [\App\Http\Controllers\Sahab\StorePaymentController::class, 'callback']);
    Route::get('/store/{slug}/order/{invoiceId}/success', [\App\Http\Controllers\Sahab\StorePaymentController::class, 'success']);
    Route::get('/store/{slug}/order/{invoiceId}/failed', [\App\Http\Controllers\Sahab\StorePaymentController::class, 'failed']);
});

// =====================================================================
//  AUTHENTICATED — لوحة التحكّم
// =====================================================================
Route::middleware(['web', 'auth'])->prefix('app')->group(function () {

    Route::get('/dashboard', [TenantController::class, 'dashboard'])->name('dashboard');
    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::get('/settings', fn() => view('sahab.settings.index'))->name('settings');
    Route::get('/kds', fn() => view('sahab.kds.display'))->name('kds');

    // متجر الواتساب (للباقة المؤسّسيّة)
    Route::get('/store', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'settings'])->name('store.settings');
    Route::get('/store/menu', [\App\Http\Controllers\Sahab\StoreMenuController::class, 'index'])->name('store.menu');
    Route::get('/themes', [\App\Http\Controllers\Sahab\StoreThemeController::class, 'marketplace'])->name('themes.marketplace');
    Route::get('/themes/my', [\App\Http\Controllers\Sahab\StoreThemeController::class, 'myThemes'])->name('themes.my');

    // Reports
    Route::get('/reports', fn() => view('sahab.reports.index'))->name('reports');
    Route::get('/reports/sales', fn() => view('sahab.reports.sales'))->name('reports.sales');

    // HR (Daftra-style)
    Route::get('/employees', fn() => view('sahab.hr.employees'))->name('employees');
    Route::get('/attendance', fn() => view('sahab.hr.attendance'))->name('attendance');
    Route::get('/leaves', fn() => view('sahab.hr.leaves'))->name('leaves');
    Route::get('/documents', fn() => view('sahab.hr.documents'))->name('documents');
    Route::get('/payroll', fn() => view('sahab.hr.payroll'))->name('payroll');

    // Accounting (Daftra-style)
    Route::get('/accounting/chart', fn() => view('sahab.accounting.chart'))->name('accounting.chart');
    Route::get('/accounting/journal', fn() => view('sahab.accounting.journal'))->name('accounting.journal');
    Route::get('/accounting/checks', fn() => view('sahab.accounting.checks'))->name('accounting.checks');
    Route::get('/accounting/assets', fn() => view('sahab.accounting.assets'))->name('accounting.assets');

    // Delivery
    Route::get('/delivery', fn() => view('sahab.delivery.integrations'))->name('delivery');
    Route::get('/delivery/orders', fn() => view('sahab.delivery.orders'))->name('delivery.orders');

    // Customers, Suppliers, Products
    Route::get('/customers', fn() => view('sahab.customers.index'))->name('customers');
    Route::get('/suppliers', fn() => view('sahab.suppliers.index'))->name('suppliers');
    Route::get('/products', fn() => view('sahab.products.index'))->name('products');
    Route::get('/inventory', fn() => view('sahab.inventory.index'))->name('inventory');
    Route::get('/purchases', fn() => view('sahab.purchases.index'))->name('purchases');

    // Daftra-style features (مع Controllers حقيقيّة)
    Route::get('/work-orders',   [\App\Http\Controllers\Sahab\WorkOrderController::class, 'index'])->name('work_orders');
    Route::get('/bookings',      [\App\Http\Controllers\Sahab\BookingController::class, 'index'])->name('bookings');
    Route::get('/time-tracking', [\App\Http\Controllers\Sahab\TimeTrackingController::class, 'index'])->name('time_tracking');
    Route::get('/installments',  [\App\Http\Controllers\Sahab\InstallmentController::class, 'index'])->name('installments');
    Route::get('/sales-targets', [\App\Http\Controllers\Sahab\SalesTargetController::class, 'index'])->name('sales_targets');

    // Stub views (UI جاهز - Controllers قادمة)
    Route::get('/memberships', fn() => view('sahab.memberships.index'))->name('memberships');
    Route::get('/rentals', fn() => view('sahab.rentals.index'))->name('rentals');
    Route::get('/stock-permits', fn() => view('sahab.stock_permits.index'))->name('stock_permits');
    Route::get('/departments', fn() => view('sahab.departments.index'))->name('departments');
    Route::get('/expenses', fn() => view('sahab.expenses.index'))->name('expenses');
    Route::get('/offers', fn() => view('sahab.offers.index'))->name('offers');

    // AI
    Route::get('/ai', fn() => view('sahab.ai.chat'))->name('ai');
    Route::get('/ocr', fn() => view('sahab.ocr.upload'))->name('ocr');

    // Subscription
    Route::get('/subscription', fn() => view('sahab.subscription.index'))->name('subscription');
    Route::post('/subscription/change-plan', [TenantController::class, 'changePlan']);
});

// =====================================================================
//  API
// =====================================================================
Route::prefix('api/sahab')->group(function () {

    // Webhooks (لا تحتاج auth)
    Route::post('/delivery/webhook/{platform}/{tenant_code}', [DeliveryController::class, 'webhook']);

    // Authenticated API
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/tenant', fn() => auth()->user()->tenant);
        Route::get('/dashboard', [TenantController::class, 'dashboard']);

        // POS
        Route::post('/pos/invoices', [PosController::class, 'createInvoice']);
        Route::post('/pos/invoices/{invoice}/refund', [PosController::class, 'refund']);
        Route::post('/pos/shifts/open', [PosController::class, 'openShift']);
        Route::post('/pos/shifts/{shift}/close', [PosController::class, 'closeShift']);

        // HR
        Route::get('/hr/employees', [HrController::class, 'employees']);
        Route::post('/hr/employees', [HrController::class, 'createEmployee']);
        Route::post('/hr/employees/{employee}/documents', [HrController::class, 'uploadEmployeeDocument']);
        Route::get('/hr/documents/expiring', [HrController::class, 'expiringDocuments']);
        Route::post('/hr/attendance/check-in', [HrController::class, 'checkIn']);
        Route::post('/hr/attendance/check-out', [HrController::class, 'checkOut']);
        Route::post('/hr/attendance/surprise-test', [HrController::class, 'triggerSurpriseTest']);
        Route::post('/hr/leaves', [HrController::class, 'requestLeave']);
        Route::post('/hr/leaves/{leave}/approve', [HrController::class, 'approveLeave']);

        // Delivery
        Route::get('/delivery/integrations', [DeliveryController::class, 'integrations']);
        Route::post('/delivery/integrations/{platform}/configure', [DeliveryController::class, 'configure']);
        Route::post('/delivery/orders/{order}/accept', [DeliveryController::class, 'acceptOrder']);
        Route::post('/delivery/orders/{order}/reject', [DeliveryController::class, 'rejectOrder']);
        Route::post('/delivery/orders/{order}/status', [DeliveryController::class, 'updateStatus']);
        Route::post('/delivery/menu/sync', [DeliveryController::class, 'syncMenu']);

        // متجر الواتساب (إدارة)
        Route::post('/store/update', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'update']);
        Route::post('/store/publish', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'publish']);
        Route::get('/store/analytics', [\App\Http\Controllers\Sahab\WhatsappStoreController::class, 'analytics']);

        // محرّر المنيو
        Route::post('/store/menu/categories',                       [\App\Http\Controllers\Sahab\StoreMenuController::class, 'storeCategory']);
        Route::put ('/store/menu/categories/{category}',            [\App\Http\Controllers\Sahab\StoreMenuController::class, 'updateCategory']);
        Route::delete('/store/menu/categories/{category}',          [\App\Http\Controllers\Sahab\StoreMenuController::class, 'deleteCategory']);
        Route::post('/store/menu/categories/reorder',               [\App\Http\Controllers\Sahab\StoreMenuController::class, 'reorderCategories']);
        Route::post('/store/menu/items',                            [\App\Http\Controllers\Sahab\StoreMenuController::class, 'addItem']);
        Route::put ('/store/menu/items/{item}',                     [\App\Http\Controllers\Sahab\StoreMenuController::class, 'updateItem']);
        Route::delete('/store/menu/items/{item}',                   [\App\Http\Controllers\Sahab\StoreMenuController::class, 'removeItem']);
        Route::post('/store/menu/items/reorder',                    [\App\Http\Controllers\Sahab\StoreMenuController::class, 'reorderItems']);
        Route::post('/store/menu/import',                           [\App\Http\Controllers\Sahab\StoreMenuController::class, 'bulkImport']);

        // متجر الثيمات
        Route::get ('/themes/{theme}',           [\App\Http\Controllers\Sahab\StoreThemeController::class, 'show']);
        Route::post('/themes/{theme}/purchase',  [\App\Http\Controllers\Sahab\StoreThemeController::class, 'purchase']);
        Route::post('/themes/{theme}/activate',  [\App\Http\Controllers\Sahab\StoreThemeController::class, 'activate']);

        // ميزات دفترة: المبيعات المستهدفة
        Route::get ('/sales-targets',          [\App\Http\Controllers\Sahab\SalesTargetController::class, 'index']);
        Route::post('/sales-targets',          [\App\Http\Controllers\Sahab\SalesTargetController::class, 'store']);
        Route::get ('/sales-targets/{target}', [\App\Http\Controllers\Sahab\SalesTargetController::class, 'show']);

        // الأقساط
        Route::get ('/installments',                       [\App\Http\Controllers\Sahab\InstallmentController::class, 'index']);
        Route::post('/installments',                       [\App\Http\Controllers\Sahab\InstallmentController::class, 'store']);
        Route::post('/installment-payments/{payment}/pay', [\App\Http\Controllers\Sahab\InstallmentController::class, 'payInstallment']);

        // أوامر الشغل
        Route::get ('/work-orders',                 [\App\Http\Controllers\Sahab\WorkOrderController::class, 'index']);
        Route::post('/work-orders',                 [\App\Http\Controllers\Sahab\WorkOrderController::class, 'store']);
        Route::post('/work-orders/{order}/status',  [\App\Http\Controllers\Sahab\WorkOrderController::class, 'updateStatus']);

        // الحجوزات
        Route::get ('/bookings',                  [\App\Http\Controllers\Sahab\BookingController::class, 'index']);
        Route::get ('/bookings/calendar',         [\App\Http\Controllers\Sahab\BookingController::class, 'calendar']);
        Route::post('/bookings',                  [\App\Http\Controllers\Sahab\BookingController::class, 'store']);
        Route::post('/bookings/{booking}/status', [\App\Http\Controllers\Sahab\BookingController::class, 'updateStatus']);

        // تتبّع الوقت
        Route::get ('/time-entries',                [\App\Http\Controllers\Sahab\TimeTrackingController::class, 'index']);
        Route::post('/time-entries/start',          [\App\Http\Controllers\Sahab\TimeTrackingController::class, 'startTimer']);
        Route::post('/time-entries/{entry}/stop',   [\App\Http\Controllers\Sahab\TimeTrackingController::class, 'stopTimer']);
    });
});

// =====================================================================
//  CRON
// =====================================================================
Route::prefix('cron')->middleware('cron.token')->group(function () {
    Route::post('/document-alerts', [HrController::class, 'sendExpiryAlerts']);
});
