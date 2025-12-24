<?php

namespace App\Helpers;

use App\Services\Finance\FinanceBffToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class FinanceApiHelper
{
    public static function get(string $endpoint, array $params = []): array
    {
        return self::send('GET', $endpoint, ['query' => $params]);
    }

    public static function post(string $endpoint, array $data = []): array
    {
        return self::send('POST', $endpoint, ['json' => $data]);
    }

    public static function put(string $endpoint, array $data = []): array
    {
        return self::send('PUT', $endpoint, ['json' => $data]);
    }

    public static function delete(string $endpoint, array $data = []): array
    {
        return self::send('DELETE', $endpoint, ['json' => $data]);
    }

    private static function send(string $method, string $endpoint, array $options = []): array
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return [
                    'success' => false,
                    'status' => 401,
                    'message' => 'Not authenticated',
                    'api_status' => null,
                    'error_code' => null,
                    'errors' => null,
                    'data' => null,
                ];
            }

            // fresh read untuk role/is_active terbaru
            $user->refresh();

            $token = FinanceBffToken::make($user);
            $base  = rtrim((string) config('finance.base_url'), '/');
            $url   = $base . '/' . ltrim($endpoint, '/');

            /** @var Response $res */
            $res = Http::timeout((int) config('finance.timeout'))
                ->acceptJson()
                ->withToken($token)
                ->send($method, $url, $options);

            $status = $res->status();
            $ok = $res->successful();

            // --- Parse body safely ---
            $json = null;
            try {
                // kalau body kosong atau bukan json, ini bisa throw / return null
                $json = $res->json();
            } catch (\Throwable $e) {
                $json = null;
            }

            // --- Build robust message ---
            $message =
                data_get($json, 'message')
                ?? data_get($json, 'data.message')
                ?? data_get($json, 'error.message')
                ?? ($ok ? 'OK' : 'Request failed');

            // --- Lift common fields to top-level for convenience ---
            $apiStatus = data_get($json, 'status') // biasanya "success"/"fail"
                ?? data_get($json, 'data.status');

            $errorCode = data_get($json, 'error_code')
                ?? data_get($json, 'errorCode')
                ?? data_get($json, 'code')
                ?? data_get($json, 'data.error_code')
                ?? data_get($json, 'data.errorCode');

            $errors = data_get($json, 'errors')
                ?? data_get($json, 'error.details')
                ?? data_get($json, 'data.errors');

            // log minimal tapi informatif
            Log::info("📡 FINANCE {$method} {$url}", [
                'http_status' => $status,
                'success' => $ok,
                'api_status' => $apiStatus,
                'error_code' => $errorCode,
            ]);

            return [
                'success' => $ok,
                'status' => $status,
                'message' => $message,
                'api_status' => $apiStatus,
                'error_code' => $errorCode,
                'errors' => $errors,
                'data' => $json, // keep full raw json response
            ];
        } catch (\Throwable $e) {
            Log::error("❌ FINANCE API error", [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'api_status' => null,
                'error_code' => null,
                'errors' => null,
                'data' => null,
            ];
        }
    }
}
