<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKeyHeader = $request->header('X-API-Key');

        if (!$apiKeyHeader) {
            return response()->json([
                'error' => 'API key is required',
                'message' => 'Please provide an API key in the X-API-Key header',
            ], 401);
        }

        $apiKey = ApiKey::where('key', $apiKeyHeader)->first();

        if (!$apiKey) {
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401);
        }

        if (!$apiKey->is_active) {
            return response()->json([
                'error' => 'API key is inactive',
                'message' => 'This API key has been deactivated',
            ], 403);
        }

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
