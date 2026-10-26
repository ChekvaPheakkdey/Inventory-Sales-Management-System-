<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ApiToken::query()->with('user')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ! $token->user || ! $token->user->is_active || $token->expires_at?->isPast()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('api_token', $token);
        $request->setUserResolver(fn () => $token->user);

        return $next($request);
    }
}
