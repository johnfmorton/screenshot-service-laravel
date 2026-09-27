<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApiRequests
{
    private const WINDOW_SECONDS = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey instanceof ApiKey) {
            return $next($request);
        }

        // No rate limit set - allow request
        if (!$apiKey->rate_limit) {
            return $next($request);
        }

        $limiterKey = "api-key:{$apiKey->id}";

        // Count first and decide on the result. The increment is atomic, so
        // concurrent requests each get a distinct count; reading the counter
        // and writing it back separately let a burst through together.
        $hits = RateLimiter::increment($limiterKey, self::WINDOW_SECONDS);
        $resetsIn = max(1, RateLimiter::availableIn($limiterKey));

        if ($hits > $apiKey->rate_limit) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "You have exceeded your rate limit of {$apiKey->rate_limit} requests per hour.",
                'retry_after' => $resetsIn,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $apiKey->rate_limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $resetsIn,
                'Retry-After' => $resetsIn,
            ]);
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $apiKey->rate_limit);
        $response->headers->set('X-RateLimit-Remaining', $apiKey->rate_limit - $hits);
        $response->headers->set('X-RateLimit-Reset', time() + $resetsIn);

        return $response;
    }
}
