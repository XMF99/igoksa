<?php

namespace App\Http\Middleware\Sahab;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: cron.token
 *  يحمي مسارات الـ /cron/* — تتطلّب توكن سري
 *
 *  استعمال:
 *   - أضف في bootstrap/app.php (Laravel 11+):
 *     ->withMiddleware(function ($middleware) {
 *         $middleware->alias(['cron.token' => CronTokenMiddleware::class]);
 *     })
 */
class CronTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Cron-Token')
              ?? $request->input('token');

        $expectedToken = config('services.sahab.cron_token', env('CRON_TOKEN'));

        if (!$expectedToken) {
            // إذا لم يُضبط التوكن، لا نسمح
            return response()->json(['error' => 'cron_token_not_configured'], 503);
        }

        if (!hash_equals($expectedToken, (string) $token)) {
            return response()->json(['error' => 'invalid_cron_token'], 401);
        }

        return $next($request);
    }
}
