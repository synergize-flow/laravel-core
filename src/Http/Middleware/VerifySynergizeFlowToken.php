<?php

namespace SynergizeFlow\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySynergizeFlowToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('synergizeflow.security_key');

        if (empty($expectedKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'SynergizeFlow security key is not configured on the server.',
            ], 500);
        }

        // Support Authorization Bearer, X-SynergizeFlow-Key header, or payload keys (key / license)
        $providedKey = $request->bearerToken()
            ?: $request->header('X-SynergizeFlow-Key')
            ?: $request->input('key')
            ?: $request->input('license');

        if (! is_string($providedKey) || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Invalid or missing security key.',
            ], 401);
        }

        return $next($request);
    }
}
