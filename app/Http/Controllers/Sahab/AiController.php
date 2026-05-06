<?php

namespace App\Http\Controllers\Sahab;

use App\Http\Controllers\Controller;
use App\Services\Sahab\AiAssistantService;
use Illuminate\Http\Request;

/**
 * AiController — واجهات API للذكاء الاصطناعي
 */
class AiController extends Controller
{
    public function __construct(
        protected AiAssistantService $ai,
    ) {}

    /**
     * اسأل المساعد الذكي
     */
    public function ask(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:2000',
            'context'  => 'nullable|array',
        ]);

        $tenant = $request->user()->tenant;

        // تحقّق من حصّة AI الشهريّة
        $monthlyUsed = \App\Models\Sahab\AiUsage::where('tenant_id', $tenant->id)
            ->where('feature', 'assistant')
            ->whereMonth('created_at', now()->month)
            ->count();

        $quota = $tenant->plan?->ai_quota_monthly ?? 0;
        if ($quota > 0 && $monthlyUsed >= $quota) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => 'استنفذت حصّتك الشهريّة من المساعد الذكي. ترقيتك للباقة الأعلى للاستفادة من حصّة أكبر.',
            ], 429);
        }

        try {
            $answer = $this->ai->ask($tenant, $validated['question'], $validated['context'] ?? []);
            return response()->json([
                'success' => true,
                'answer'  => $answer,
                'remaining_quota' => $quota > 0 ? max(0, $quota - $monthlyUsed - 1) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ai_error',
                'message' => 'حدث خطأ في المساعد. حاول لاحقاً.',
            ], 500);
        }
    }

    /**
     * قراءة فاتورة بالـ OCR (Vision API)
     */
    public function ocrInvoice(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $tenant = $request->user()->tenant;

        // التحقّق من حصّة OCR
        $monthlyUsed = \App\Models\Sahab\AiUsage::where('tenant_id', $tenant->id)
            ->where('feature', 'ocr')
            ->whereMonth('created_at', now()->month)
            ->count();

        $quota = $tenant->plan?->ocr_quota_monthly ?? 0;
        if ($quota > 0 && $monthlyUsed >= $quota) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => 'استنفذت حصّتك الشهريّة من OCR.',
            ], 429);
        }

        try {
            $imagePath = $request->file('image')->store('ocr-uploads/' . $tenant->code, 'public');
            $extracted = $this->ai->ocrInvoice($tenant, storage_path('app/public/' . $imagePath));

            // Log
            \App\Models\Sahab\OcrLog::create([
                'tenant_id'         => $tenant->id,
                'user_id'           => $request->user()->id,
                'document_type'     => 'invoice',
                'original_image_path' => $imagePath,
                'extracted_data'    => $extracted,
                'confidence_score'  => $extracted['confidence'] ?? 0,
            ]);

            return response()->json([
                'success'   => true,
                'extracted' => $extracted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ocr_failed',
                'message' => 'فشل قراءة الفاتورة: ' . $e->getMessage(),
            ], 500);
        }
    }
}
