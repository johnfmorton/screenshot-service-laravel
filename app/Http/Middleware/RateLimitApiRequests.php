<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitApiRequests
{
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

        $cacheKey = "rate_limit:{$apiKey->id}";
        $windowSeconds = 3600; // 1 hour

        $currentCount = Cache::get($cacheKey, 0);
        $remaining = max(0, $apiKey->rate_limit - $currentCount);

        // Check if rate limit exceeded
        if ($currentCount >= $apiKey->rate_limit) {
            $ttl = Cache::getStore()->get($cacheKey . ':ttl');
            $retryAfter = $ttl ? max(1, $ttl - time()) : $windowSeconds;

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "You have exceeded your rate limit of {$apiKey->rate_limit} requests per hour.",
                'retry_after' => $retryAfter,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $apiKey->rate_limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $retryAfter,
                'Retry-After' => $retryAfter,
            ]);
        }

        // Increment the counter
        if ($currentCount === 0) {
            // First request in the window
            Cache::put($cacheKey, 1, $windowSeconds);
            Cache::put($cacheKey . ':ttl', time() + $windowSeconds, $windowSeconds);
        } else {
            Cache::increment($cacheKey);
        }

        $response = $next($request);

        // Add rate limit headers to successful responses
        $response->headers->set('X-RateLimit-Limit', $apiKey->rate_limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining - 1));

        $ttl = Cache::get($cacheKey . ':ttl', time() + $windowSeconds);
        $response->headers->set('X-RateLimit-Reset', $ttl);

        return $response;
    }
}
