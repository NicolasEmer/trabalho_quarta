<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiRequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        if (!Str::startsWith($request->path(), 'api/')) {
            return $next($request);
        }

        $start = microtime(true);

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        try {
            $durationMs = (microtime(true) - $start) * 1000;

            $user = Auth::user();
            $userId = $user?->id;

            $body = $request->all();

            foreach (['password', 'password_confirmation', 'token', 'current_password'] as $field) {
                if (array_key_exists($field, $body)) {
                    $body[$field] = '***';
                }
            }

            $responseBody = null;
            if (method_exists($response, 'getContent')) {
                $raw = $response->getContent();

                if (is_string($raw) && strlen($raw) > 2000) {
                    $raw = substr($raw, 0, 2000) . '...';
                }

                $decoded = json_decode($raw, true);
                $responseBody = $decoded ?? ['raw' => $raw];
            }

            DB::table('api_logs')->insert([
                'direction' => 'in',
                'service' => 'laravel',
                'method' => $request->getMethod(),
                'path' => '/' . ltrim($request->path(), '/'),
                'status_code' => $response->getStatusCode(),
                'user_id' => $userId,
                'ip' => $request->ip(),
                'request_body' => json_encode($body),
                'response_body' => $responseBody ? json_encode($responseBody) : null,
                'duration_ms' => $durationMs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
        }

        return $response;
    }
}
