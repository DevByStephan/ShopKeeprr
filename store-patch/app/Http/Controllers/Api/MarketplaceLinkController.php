<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceSiteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketplaceLinkController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'uuid'],
            'site_url' => ['required', 'string', 'max:2048'],
            'site_name' => ['nullable', 'string', 'max:160'],
            'platform_version' => ['nullable', 'string', 'max:32'],
        ]);

        $siteUrl = $this->normaliseSiteUrl($data['site_url']);
        $deviceCode = Str::random(80);
        $userCode = $this->uniqueUserCode();
        $expiresAt = now()->addMinutes(15);

        MarketplaceSiteLink::query()->create([
            'site_id' => $data['site_id'],
            'site_url' => $siteUrl,
            'site_name' => $data['site_name'] ?? null,
            'platform_version' => $data['platform_version'] ?? null,
            'device_code_hash' => hash('sha256', $deviceCode),
            'user_code' => $userCode,
            'expires_at' => $expiresAt,
            'meta' => ['requested_at' => now()->toIso8601String()],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'device_code' => $deviceCode,
                'user_code' => $userCode,
                'verification_url' => URL::route('marketplace.link', ['code' => $userCode]),
                'expires_at' => $expiresAt->toIso8601String(),
                'interval' => 3,
            ],
        ], 201);
    }

    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_code' => ['required', 'string', 'max:200'],
        ]);

        $link = MarketplaceSiteLink::query()
            ->with('user')
            ->where('device_code_hash', hash('sha256', $data['device_code']))
            ->first();

        if (! $link || $link->revoked_at !== null) {
            return $this->error('This connection request is no longer valid.', 410, 'link_expired');
        }

        if ($link->isExpired()) {
            return $this->error('This connection request has expired. Start again from the store admin.', 410, 'link_expired');
        }

        if (! $link->approved_at) {
            return response()->json([
                'success' => true,
                'data' => ['status' => 'pending'],
            ], 202);
        }

        if (! $link->token) {
            $token = 'sk_site_'.Str::random(64);

            $link->forceFill([
                'token' => $token,
                'token_hash' => hash('sha256', $token),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'connected',
                'token' => $link->token,
                'account' => [
                    'name' => $link->user?->name,
                    'email' => $link->user?->email,
                ],
                'site' => [
                    'site_id' => $link->site_id,
                    'site_url' => $link->site_url,
                    'site_name' => $link->site_name,
                ],
            ],
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        /** @var MarketplaceSiteLink $link */
        $link = $request->attributes->get('marketplace_link');

        $link->forceFill(['revoked_at' => now()])->save();

        return response()->json(['success' => true, 'message' => 'This site was disconnected.']);
    }

    private function normaliseSiteUrl(string $value): string
    {
        $value = trim($value);
        $parts = parse_url($value);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            throw ValidationException::withMessages(['site_url' => 'Enter a valid site URL.']);
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (! in_array($scheme, ['http', 'https'], true) || ($scheme === 'http' && ! $local)) {
            throw ValidationException::withMessages(['site_url' => 'A live Store connection must use HTTPS. Localhost may use HTTP for development.']);
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages(['site_url' => 'The site URL cannot contain credentials, a query string or a fragment.']);
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.($path === '' ? '' : $path);
    }

    private function uniqueUserCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (MarketplaceSiteLink::query()->where('user_code', $code)->whereNull('revoked_at')->exists());

        return $code;
    }

    private function error(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
