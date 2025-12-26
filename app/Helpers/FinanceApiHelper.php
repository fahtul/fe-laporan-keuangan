<?php

namespace App\Helpers;

use App\Services\Finance\FinanceBffToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class FinanceApiHelper
{
    public static function get(string $endpoint, array $params = [], array $headers = []): array
    {
        return self::send('GET', $endpoint, ['query' => $params], $headers);
    }

   public static function post(string $endpoint, array $data = [], array $headers = []): array
    {
        // ✅ kalau kosong, paksa jadi object {} saat dikirim
        $json = empty($data) ? (object)[] : $data;

        return self::send('POST', $endpoint, ['json' => $json], $headers);
    }

 public static function postObject(string $endpoint, array $data = [], array $headers = []): array
    {
        $json = (object) $data; // array kosong => {}
        return self::send('POST', $endpoint, ['json' => $json], $headers);
    }

    public static function put(string $endpoint, array $data = [], array $headers = []): array
    {
        return self::send('PUT', $endpoint, ['json' => $data], $headers);
    }

    public static function delete(string $endpoint, array $data = [], array $headers = []): array
    {
        return self::send('DELETE', $endpoint, ['json' => $data], $headers);
    }

    private static function send(string $method, string $endpoint, array $options = [], array $headers = []): array
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

            $user->refresh();

            $token = FinanceBffToken::make($user);
            $base  = rtrim((string) config('finance.base_url'), '/');
            $url   = $base . '/' . ltrim($endpoint, '/');

            $client = Http::timeout((int) config('finance.timeout'))
                ->acceptJson()
                ->withToken($token);

            if (!empty($headers)) {
                $client = $client->withHeaders($headers);
            }

            /** @var Response $res */
            $res = $client->send($method, $url, $options);

            $status = $res->status();
            $ok = $res->successful();

            $json = null;
            try {
                $json = $res->json();
            } catch (\Throwable $e) {
                $json = null;
            }

            $message =
                data_get($json, 'message')
                ?? data_get($json, 'data.message')
                ?? data_get($json, 'error.message')
                ?? ($ok ? 'OK' : 'Request failed');

            $apiStatus = data_get($json, 'status') ?? data_get($json, 'data.status');

            $errorCode = data_get($json, 'error_code')
                ?? data_get($json, 'errorCode')
                ?? data_get($json, 'code')
                ?? data_get($json, 'data.error_code')
                ?? data_get($json, 'data.errorCode');

            $errors = data_get($json, 'errors')
                ?? data_get($json, 'error.details')
                ?? data_get($json, 'data.errors');

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
                'data' => $json,
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
