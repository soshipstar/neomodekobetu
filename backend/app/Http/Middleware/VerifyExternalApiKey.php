<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey || !hash_equals(config('services.external_api.key', ''), $apiKey)) {
            return response()->json(['message' => 'Invalid API key.'], 403);
        }

        return $next($request);
    }
}
