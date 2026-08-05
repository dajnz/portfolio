<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * `log_context` middleware alias (error-handling.md:257-291) — runs first in
 * the `web` and `api` groups so every log entry carries the request context:
 *
 * - `request_id`: a UUID per request, propagated as the `X-Request-ID`
 *   response header and a request attribute (read by `HandleInertiaRequests`
 *   and the error pages).
 * - `Log::withContext()`: request_id, anonymized IP (last octet zeroed),
 *   path, method.
 * - Slow-query logs: ORM queries slower than 500 ms are logged at `warning`
 *   with their SQL and bindings; binding values whose column name matches
 *   `SENSITIVE_FIELDS` are replaced with `[REDACTED]`. The same constant is
 *   the single source of truth for the log-channel redaction configured in
 *   step 2.5 (config/logging.php).
 */
final class LogContextMiddleware
{
    public const SENSITIVE_FIELDS = [
        'password', 'password_confirmation', 'current_password',
        'totp_code', 'totp_secret', 'remember_token',
        'github_token', 'email', 'ip_address', 'fingerprint',
    ];

    private const SLOW_QUERY_THRESHOLD_MS = 500;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'ip' => $this->anonymizeIp($request->ip()),
            'url' => $request->path(),
            'method' => $request->method(),
        ]);

        $this->registerSlowQueryListener();

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    /**
     * Registers the slow-query listener exactly once per process
     * (`once()` memoizes the closure result in the container).
     */
    private function registerSlowQueryListener(): void
    {
        once(function (): void {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time < self::SLOW_QUERY_THRESHOLD_MS) {
                    return;
                }

                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $this->redactBindings($query->sql, $query->bindings),
                    'time_ms' => $query->time,
                ]);
            });
        });
    }

    /**
     * Best-effort column-name pairing: quoted identifiers (PostgreSQL
     * style) that are not table references are matched to the bindings in
     * order; sensitive columns are replaced with `[REDACTED]`.
     *
     * @param  mixed[]  $bindings
     * @return mixed[]
     */
    private function redactBindings(string $sql, array $bindings): array
    {
        $columns = $this->columnNamesFromSql($sql);

        foreach ($bindings as $index => $binding) {
            if (in_array($columns[$index] ?? null, self::SENSITIVE_FIELDS, true)) {
                $bindings[$index] = '[REDACTED]';
            }
        }

        return $bindings;
    }

    /**
     * @return string[] column names in the order they appear as the target
     *                  of an assignment or comparison (best effort)
     */
    private function columnNamesFromSql(string $sql): array
    {
        preg_match_all('/"([a-z_][a-z0-9_]*)"/i', $sql, $matches, PREG_OFFSET_CAPTURE);

        $columns = [];
        foreach ($matches[1] as [$column, $offset]) {
            $prefix = strtolower(substr($sql, max(0, $offset - 12), 12));
            if (str_contains($prefix, 'from ') || str_contains($prefix, 'join ')
                || str_contains($prefix, ' into ') || str_contains($prefix, 'update ')
                || str_contains($prefix, 'table ') || str_contains($prefix, ' as ')) {
                continue;
            }
            $columns[] = $column;
        }

        return $columns;
    }

    private function anonymizeIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return $ip;
        }

        if (str_contains($ip, '.')) {
            return (string) preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6 — zero the trailing hextets.
        return (string) preg_replace('/(:[0-9a-f]{1,4}){2}$/i', ':0:0', $ip);
    }
}
