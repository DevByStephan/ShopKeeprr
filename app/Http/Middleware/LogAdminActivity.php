<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user()?->isAdmin() || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $response;
        }

        try {
            $input = $this->redact($request->except(['_token', '_method']));

            AuditLog::query()->create([
                'user_id' => $request->user()->id,
                'action' => $request->route()?->getName() ?? $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'new_values' => $input,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit logging should never break an admin action.
        }

        return $response;
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->redact($value);
                continue;
            }

            if (preg_match('/password|secret|token|api[_-]?key|card|cvv|authorization/i', (string) $key)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
