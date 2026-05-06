# دليل تركيب منصّة سحاب على ERPGo

> هذا الدليل العملي لتركيب منصّة سحاب فوق ERPGo (Laravel 12) واختبار كل الميزات.

---

## 📦 ما هو سحاب؟

سحاب هو **حزمة (Module)** تُضاف فوق ERPGo، تُحوّله من نظام ERP عام إلى منصّة كاشير ذكيّة سعوديّة متكاملة:

- ✅ **POS متعدّد الفروع** مع QR زاتكا
- ✅ **ربط 8 تطبيقات توصيل** (هنقرستيشن، جاهز، توصيل، إلخ)
- ✅ **مساعد ذكي AI** بـ Claude
- ✅ **OCR للفواتير** بالكاميرا
- ✅ **HR كامل بدفترة-style** (وثائق + GPS + رواتب)
- ✅ **محاسبة متقدّمة** (دليل حسابات + شيكات + أصول)
- ✅ **3 باقات SaaS** جاهزة (149 / 349 / 899 ر.س)

ERPGo يبقى كما هو — ميزاته (المحاسبة الأساسيّة، CRM، Project) شغّالة. سحاب يضيف فوقه طبقة كاشير + ميزات مميّزة.

---

## ⚡ التركيب — 30 دقيقة

### الخطوة 1: استخراج ERPGo (10 دقائق)

```bash
# استخراج الملف اللي اشتريته
unzip codecanyon-aUXulY8N-erpgo-saas-all-in-one-business-erp.zip -d /var/www/

# الانتقال لمجلّد ERPGo
cd /var/www/erpgo-main-file

# تثبيت الـ dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# إعداد .env
cp .env.example .env
php artisan key:generate

# قاعدة البيانات
nano .env  # عدّل بيانات DB

php artisan migrate --force
php artisan db:seed --force  # seeders ERPGo الأصليّة
```

### الخطوة 2: نسخ ملفّات سحاب (5 دقائق)

```bash
cd /var/www/erpgo-main-file

# انسخ كل ملفّات سحاب فوق ERPGo
cp -r /path/to/sahab/database/migrations/* database/migrations/
cp -r /path/to/sahab/app/Models/Sahab app/Models/
cp -r /path/to/sahab/app/Http/Controllers/Sahab app/Http/Controllers/
cp -r /path/to/sahab/app/Services/Sahab app/Services/
cp -r /path/to/sahab/database/seeders/SahabSeeder.php database/seeders/
cp -r /path/to/sahab/resources/views/sahab resources/views/
cp /path/to/sahab/routes/sahab.php routes/
```

### الخطوة 3: تسجيل المسارات (دقيقتين)

افتح `routes/web.php` وأضف في الأعلى:

```php
require __DIR__.'/sahab.php';
```

### الخطوة 4: تشغيل المهاجرات (3 دقائق)

```bash
php artisan migrate --force
php artisan db:seed --class=SahabSeeder --force
```

### الخطوة 5: ضبط .env (5 دقائق)

أضف لـ `.env`:

```bash
# Anthropic (للذكاء الاصطناعي والـ OCR)
ANTHROPIC_API_KEY=sk-ant-api03-...

# Unifonic (للواتساب والـ SMS)
UNIFONIC_APP_SID=your-app-sid
UNIFONIC_SENDER_ID=Sahab

# Moyasar (بوّابة الدفع)
MOYASAR_API_KEY=sk_test_...

# Cron token
CRON_TOKEN=$(openssl rand -hex 32)
```

### الخطوة 6: Cron Jobs (5 دقائق)

أضف لـ `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // تنبيهات الوثائق - يومياً صباحاً
    $schedule->call(function () {
        Http::post(url('/cron/document-alerts'), [
            'token' => env('CRON_TOKEN'),
        ]);
    })->dailyAt('09:00');

    // التقارير اليوميّة - مساءً
    $schedule->command('sahab:send-daily-reports')->dailyAt('21:00');

    // التقارير الأسبوعيّة - الأحد
    $schedule->command('sahab:send-weekly-reports')->weeklyOn(0, '21:00');

    // التقارير الشهريّة - أوّل الشهر
    $schedule->command('sahab:send-monthly-reports')->monthlyOn(1, '09:00');
}
```

ثم في crontab:

```bash
* * * * * cd /var/www/erpgo-main-file && php artisan schedule:run >> /dev/null 2>&1
```

---

## ✅ التحقّق من التركيب

افتح المتصفّح:

```
https://your-domain.com/        → صفحة الإعلانات
https://your-domain.com/login   → الدخول
```

ادخل بـ:
- البريد: `demo@sahab.sa`
- كلمة المرور: `password`

ستفتح **لوحة التحكّم**. جرّب:

| الرابط | الميزة |
|--------|---------|
| `/app/dashboard` | لوحة التحكّم الرئيسيّة |
| `/app/pos` | الكاشير |
| `/app/delivery` | تكاملات التوصيل |
| `/app/employees` | الموظّفون |
| `/app/documents` | وثائق الموظّفين والمحلّ |
| `/app/accounting/chart` | دليل الحسابات |
| `/app/accounting/checks` | الشيكات |
| `/app/ai` | المساعد الذكي |
| `/app/ocr` | قراءة الفواتير |

---

## 🎯 الخطوة التالية: الاختبار العملي

### اختبار 1: إنشاء فاتورة

1. افتح `/app/pos`
2. اختر منتج (كبسة لحم)
3. اضغط "نقدي"
4. ✓ يطلع إيصال مع QR زاتكا

### اختبار 2: ربط هنقرستيشن

1. افتح `/app/delivery`
2. اضغط "ربط الآن" بجانب هنقرستيشن
3. الصق المفاتيح (إذا عندك)
4. اضغط "حفظ"
5. ✓ Webhook URL يُعرض

### اختبار 3: المساعد الذكي

1. افتح `/app/ai`
2. اكتب: "كم بعت اليوم؟"
3. ✓ يجاوبك بأرقام محلّك الحقيقيّة

### اختبار 4: قراءة فاتورة

1. افتح `/app/ocr`
2. ارفع صورة فاتورة مورد
3. ✓ يستخرج البيانات تلقائياً

---

## 💰 التكلفة المتوقّعة

| البند | التكلفة |
|-------|---------|
| سيرفر VPS (Hetzner CPX21) | 33 ر.س/شهر |
| دومين | 5 ر.س/شهر |
| Anthropic API (Claude) | ~80 ر.س لكل 100 مستأجر |
| Unifonic (واتساب + SMS) | ~100 ر.س لكل 5000 رسالة |
| Cloudflare + SSL | مجاني |
| **المجموع** | **~218 ر.س/شهر للبداية** |

**الإيراد المتوقّع** (10 مستأجرين متوسّطين بالاحترافيّة):
- 10 × 349 = **3,490 ر.س/شهر**
- الربح الصافي: **~3,272 ر.س/شهر** (هامش 94%)

عند 50 مستأجر: **~17,000 ر.س/شهر صافي**.

---

## 🛡 الأمان

- ✅ كل البيانات مشفّرة في النقل (HTTPS إجباري)
- ✅ مفاتيح API لتطبيقات التوصيل مشفّرة بـ AES-256
- ✅ Multi-tenant isolation (TenantScoped trait يفلتر تلقائياً)
- ✅ Spatie Permissions للصلاحيّات (5 أدوار جاهزة)
- ✅ Rate limiting على الـ API
- ✅ Webhook signature verification (HMAC SHA256)
- ✅ كل المدفوعات عبر Moyasar (PCI compliant)

---

## 🆘 استكشاف المشاكل

### المشكلة: 500 Internal Server Error
```bash
tail -100 storage/logs/laravel.log
chmod -R 775 storage bootstrap/cache
```

### المشكلة: المهاجرات فشلت
```bash
php artisan migrate:status
php artisan migrate:fresh --seed   # ⚠ يمسح البيانات
```

### المشكلة: Webhook التوصيل لا يستقبل
```bash
# تحقّق من السجلّ
SELECT * FROM sahab_webhook_log ORDER BY id DESC LIMIT 10;
```

### المشكلة: AI لا يردّ
```bash
# تحقّق من المفتاح
php artisan tinker
>>> config('services.anthropic.api_key')

# تحقّق من الاستهلاك
SELECT SUM(cost_usd) FROM sahab_ai_usage WHERE tenant_id = X;
```

---

## 📞 خطّة الدعم

عند نشر النظام للعملاء:

1. **تواصل مع كل عميل عند التسجيل** (واتساب)
2. **جلسة Onboarding 30 دقيقة** للباقة الاحترافيّة
3. **جلسة كاملة 90 دقيقة** للباقة المؤسّسيّة
4. **متابعة بعد 7 أيام** للتأكّد إنه يستخدم النظام

---

## 🚀 الخطوة الأخيرة قبل الإطلاق

```bash
# Cache كل شي للأداء
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize composer
composer install --optimize-autoloader --no-dev

# تأكّد من النسخ الاحتياطي اليومي
crontab -e
0 3 * * * mysqldump -u root -p sahab_db > /backups/sahab-$(date +\%F).sql.gz
```

---

🌟 **النظام جاهز للإطلاق التجاري.**

كل ميزة بنيتها مستوحاة من احتياج فعلي:
- مميزات دفترة (وثائق + شيكات + أصول) ← لأن دفترة ناجح في السوق السعودي
- ربط تطبيقات التوصيل ← لأن هذا اللي يفصل سحاب عن المنافسين
- زاتكا جاهز ← لأنه إلزامي سعودياً
- AI + OCR ← لأن هذي القيمة المضافة اللي تستحقّ السعر

*منصّة سحاب — الإصدار 3.0 — مايو 2026*
