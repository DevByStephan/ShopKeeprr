<?php

namespace App\Http\Middleware;

use App\Models\MarketplaceSiteLink;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMarketplaceSite
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->unauthorized('A ShopKeeprr site token is required.');
        }

        $link = MarketplaceSiteLink::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->whereNotNull('approved_at')
            ->whereNull('revoked_at')
            ->first();

        if (! $link || ! $link->user) {
            return $this->unauthorized('This site token is invalid or has been revoked. Reconnect the Store account.');
        }

        $link->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('marketplace_link', $link);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'marketplace_unauthorized',
                'message' => $message,
            ],
        ], 401);
    }
}
