<?php

namespace App\Http\Middleware\Sahab;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: tenant.scope
 *  يضمن أن المستخدم الحالي مرتبط بـ tenant نشط
 *  ويحظر الوصول للمستأجرين المعلّقين/المنتهية تجربتهم
 */
class TenantScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (!$user->tenant_id) {
            return response()->json(['error' => 'no_tenant'], 403);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['error' => 'tenant_not_found'], 404);
        }

        // التحقّق من حالة الاشتراك
        if (in_array($tenant->subscription_status, ['suspended', 'cancelled'])) {
            return response()->json([
                'error' => 'subscription_suspended',
                'message' => 'حسابك معلّق. يرجى التواصل مع الدعم.',
            ], 403);
        }

        // التجربة المنتهية
        if ($tenant->subscription_status === 'trial' && $tenant->trial_ends_at?->isPast()) {
            return response()->json([
                'error' => 'trial_expired',
                'message' => 'انتهت تجربتك المجّانيّة. اختر باقة لمتابعة العمل.',
                'redirect' => '/app/subscription',
            ], 402);
        }

        // ربط المستأجر بالـ container للوصول السريع
        App::instance('current_tenant', $tenant);

        // تحديث آخر نشاط
        $tenant->update(['last_active_at' => now()]);

        return $next($request);
    }
}
