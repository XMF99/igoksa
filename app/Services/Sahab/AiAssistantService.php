<?php

namespace App\Services\Sahab;

use App\Models\Sahab\AiUsage;
use App\Models\Sahab\Invoice;
use App\Models\Sahab\Product;
use App\Models\Sahab\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiAssistantService - مساعد ذكي للتاجر
 * مميزات دفترة المُضافة:
 *  ✓ تحليل المبيعات والمشتريات
 *  ✓ توقّعات ذكيّة
 *  ✓ توصيات مخصّصة
 *  ✓ OCR للفواتير الواردة
 *  ✓ ملخّصات يوميّة/أسبوعيّة/شهريّة
 */
class AiAssistantService
{
    protected string $apiUrl = 'https://api.anthropic.com/v1/messages';
    protected string $model = 'claude-opus-4-7';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', env('ANTHROPIC_API_KEY'));
    }

    /**
     * المساعد الذكي يجاوب على أسئلة التاجر
     */
    public function ask(Tenant $tenant, string $question, ?int $userId = null): array
    {
        $context = $this->buildBusinessContext($tenant);

        $systemPrompt = <<<PROMPT
أنت مساعد ذكي لصاحب محلّ "{$tenant->name}" في السعوديّة. مهمّتك مساعدته في تحليل بياناته وإعطائه إجابات دقيقة وتوصيات عمليّة.

# سياق المحلّ:
{$context}

# قواعدك:
1. أجب باللغة العربيّة بأسلوب ودود وعملي
2. استخدم الأرقام الفعليّة من السياق المُعطى
3. لو ما عندك بيانات كافية، اطلب من المستخدم تحديد ما يريده
4. أعطِ توصيات عمليّة قابلة للتنفيذ
5. استخدم الإيموجي بشكل لطيف لتسهيل القراءة
6. كن مختصراً — رسالة قصيرة وواضحة أفضل من شرح طويل
7. لا تخترع أرقاماً أو إحصائيّات غير موجودة
PROMPT;

        $start = microtime(true);
        $response = $this->callClaude($systemPrompt, [
            ['role' => 'user', 'content' => $question],
        ]);
        $latency = (int) ((microtime(true) - $start) * 1000);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        // تسجيل الاستخدام
        AiUsage::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'feature' => 'assistant',
            'model' => $this->model,
            'tokens_in' => $response['usage']['input_tokens'] ?? 0,
            'tokens_out' => $response['usage']['output_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost(
                $response['usage']['input_tokens'] ?? 0,
                $response['usage']['output_tokens'] ?? 0
            ),
            'latency_ms' => $latency,
        ]);

        return [
            'success' => true,
            'answer' => $response['text'],
            'tokens_used' => ($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * بناء سياق الأعمال للـ AI
     */
    protected function buildBusinessContext(Tenant $tenant): string
    {
        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        // إحصائيّات اليوم
        $todayStats = DB::table('sahab_invoices')
            ->where('tenant_id', $tenant->id)
            ->whereDate('invoice_date', $today)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount),0) as total, COALESCE(AVG(total_amount),0) as avg')
            ->first();

        $weekStats = DB::table('sahab_invoices')
            ->where('tenant_id', $tenant->id)
            ->where('invoice_date', '>=', $weekStart)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount),0) as total')
            ->first();

        $monthStats = DB::table('sahab_invoices')
            ->where('tenant_id', $tenant->id)
            ->where('invoice_date', '>=', $monthStart)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount),0) as total')
            ->first();

        // أكثر المنتجات مبيعاً
        $topProducts = DB::table('sahab_invoice_items')
            ->join('sahab_invoices', 'sahab_invoices.id', '=', 'sahab_invoice_items.invoice_id')
            ->where('sahab_invoices.tenant_id', $tenant->id)
            ->where('sahab_invoices.invoice_date', '>=', $monthStart)
            ->select('sahab_invoice_items.product_name', DB::raw('SUM(sahab_invoice_items.quantity) as qty'))
            ->groupBy('sahab_invoice_items.product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // مخزون منخفض
        $lowStock = Product::where('tenant_id', $tenant->id)
            ->where('track_inventory', true)
            ->whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->limit(10)
            ->get(['name', 'current_stock', 'low_stock_threshold']);

        // وثائق منتهية الصلاحيّة
        $expiringDocs = DB::table('sahab_employee_documents')
            ->where('tenant_id', $tenant->id)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        $ctx = "## مبيعات اليوم ({$today}):\n";
        $ctx .= "- عدد الفواتير: {$todayStats->count}\n";
        $ctx .= "- إجمالي المبيعات: " . number_format($todayStats->total, 2) . " ر.س\n";
        $ctx .= "- متوسّط الفاتورة: " . number_format($todayStats->avg, 2) . " ر.س\n\n";

        $ctx .= "## هذا الأسبوع:\n";
        $ctx .= "- عدد الفواتير: {$weekStats->count}\n";
        $ctx .= "- الإجمالي: " . number_format($weekStats->total, 2) . " ر.س\n\n";

        $ctx .= "## هذا الشهر:\n";
        $ctx .= "- عدد الفواتير: {$monthStats->count}\n";
        $ctx .= "- الإجمالي: " . number_format($monthStats->total, 2) . " ر.س\n\n";

        if ($topProducts->count() > 0) {
            $ctx .= "## أكثر المنتجات مبيعاً (هذا الشهر):\n";
            foreach ($topProducts as $p) {
                $ctx .= "- {$p->product_name}: {$p->qty} وحدة\n";
            }
            $ctx .= "\n";
        }

        if ($lowStock->count() > 0) {
            $ctx .= "## ⚠ منتجات قارب نفاد مخزونها:\n";
            foreach ($lowStock as $p) {
                $ctx .= "- {$p->name}: متبقّي {$p->current_stock} (الحدّ الأدنى {$p->low_stock_threshold})\n";
            }
            $ctx .= "\n";
        }

        if ($expiringDocs > 0) {
            $ctx .= "## ⚠ وثائق موظّفين تنتهي خلال 30 يوم: {$expiringDocs}\n";
        }

        return $ctx;
    }

    /**
     * OCR للفواتير الواردة (من المورّدين)
     */
    public function ocrInvoice(Tenant $tenant, string $imagePath, ?int $userId = null): array
    {
        $imageData = file_get_contents($imagePath);
        if (!$imageData) return ['success' => false, 'error' => 'فشل قراءة الصورة'];

        $base64 = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

        $systemPrompt = <<<PROMPT
أنت متخصّص في قراءة فواتير المورّدين السعوديّة. مهمّتك استخراج البيانات بدقّة عالية.

أرجع JSON فقط بهذا الشكل:
{
  "supplier_name": "اسم المورّد",
  "supplier_vat_number": "الرقم الضريبي للمورّد إن وجد",
  "invoice_number": "رقم الفاتورة",
  "invoice_date": "YYYY-MM-DD",
  "items": [
    {"name": "اسم المنتج", "quantity": رقم, "unit_price": رقم, "total": رقم}
  ],
  "subtotal": رقم,
  "vat_amount": رقم,
  "total": رقم,
  "confidence": 0-100
}

⚠ مهم:
- لو ما تقدر تقرأ شي معيّن، ضع null
- التواريخ بصيغة ISO (YYYY-MM-DD) فقط
- الأرقام بدون فواصل أو رمز عملة
- confidence: نسبة ثقتك بدقّة الاستخراج
PROMPT;

        $start = microtime(true);

        $response = Http::timeout(45)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 2000,
                'system' => $systemPrompt,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => [
                            'type' => 'base64',
                            'media_type' => $mimeType,
                            'data' => $base64,
                        ]],
                        ['type' => 'text', 'text' => 'استخرج بيانات هذه الفاتورة وأرجعها كـ JSON.'],
                    ],
                ]],
            ]);

        $latency = (int) ((microtime(true) - $start) * 1000);

        if (!$response->successful()) {
            Log::error('Claude OCR failed', ['response' => $response->body()]);
            return ['success' => false, 'error' => 'فشل قراءة الفاتورة'];
        }

        $data = $response->json();
        $text = $data['content'][0]['text'] ?? '';

        // استخراج JSON من الردّ
        if (preg_match('/\{[\s\S]+\}/', $text, $matches)) {
            $extracted = json_decode($matches[0], true);
        } else {
            $extracted = null;
        }

        // تسجيل الاستخدام
        AiUsage::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'feature' => 'ocr',
            'model' => $this->model,
            'tokens_in' => $data['usage']['input_tokens'] ?? 0,
            'tokens_out' => $data['usage']['output_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost(
                $data['usage']['input_tokens'] ?? 0,
                $data['usage']['output_tokens'] ?? 0
            ),
            'latency_ms' => $latency,
        ]);

        return [
            'success' => $extracted !== null,
            'data' => $extracted,
            'raw_text' => $text,
        ];
    }

    /**
     * تقرير ذكي لليوم/الأسبوع/الشهر
     */
    public function generateSmartReport(Tenant $tenant, string $period = 'daily'): array
    {
        $context = $this->buildBusinessContext($tenant);

        $instruction = match ($period) {
            'weekly' => 'اكتب ملخّص أسبوعي يحوي: مقارنة الأيّام، اتجاه المبيعات، أداء الموظّفين، نسبة النموّ، وتوصيات لتحسين الأسبوع القادم.',
            'monthly' => 'اكتب تقرير شهري شامل يحوي: الأرباح الصافيّة، التحليل المالي، توقّعات الشهر القادم، وتقييم شامل للموظّفين.',
            default => 'اكتب ملخّص يومي يحوي: المبيعات، أكثر منتج مبيعاً، أفضل موظّف، وملاحظات عامّة.',
        };

        $systemPrompt = <<<PROMPT
أنت محلّل بيانات أعمال محترف. اكتب تقريراً مهنيّاً ومفيداً لصاحب محلّ "{$tenant->name}".

# سياق البيانات:
{$context}

# المطلوب:
{$instruction}

# الأسلوب:
- استخدم العناوين والإيموجي لتنظيم التقرير
- الأرقام بالعربيّة مع وحدة العملة (ر.س)
- اختم بـ 3 توصيات عمليّة
PROMPT;

        $response = $this->callClaude($systemPrompt, [
            ['role' => 'user', 'content' => 'أصدر التقرير الآن.'],
        ]);

        if (!$response['success']) return ['success' => false, 'error' => $response['error']];

        AiUsage::create([
            'tenant_id' => $tenant->id,
            'feature' => 'reports',
            'model' => $this->model,
            'tokens_in' => $response['usage']['input_tokens'] ?? 0,
            'tokens_out' => $response['usage']['output_tokens'] ?? 0,
            'cost_usd' => $this->calculateCost(
                $response['usage']['input_tokens'] ?? 0,
                $response['usage']['output_tokens'] ?? 0
            ),
        ]);

        return ['success' => true, 'report' => $response['text']];
    }

    /**
     * استدعاء Claude API
     */
    protected function callClaude(string $system, array $messages, int $maxTokens = 1500): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => $response->body()];
            }

            $data = $response->json();
            return [
                'success' => true,
                'text' => $data['content'][0]['text'] ?? '',
                'usage' => $data['usage'] ?? [],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * حساب تكلفة الاستخدام بالدولار
     * Claude Opus 4.7: $15/1M input, $75/1M output
     */
    protected function calculateCost(int $inputTokens, int $outputTokens): float
    {
        return ($inputTokens * 15 + $outputTokens * 75) / 1_000_000;
    }
}
