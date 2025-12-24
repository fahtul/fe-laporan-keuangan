<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Http;

class FinanceApiClient
{
    public function get(string $path, array $query = [])
    {
        $token = FinanceBffToken::make(auth()->user());

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get(rtrim(config('finance.base_url'), '/') . $path, $query);
    }

    public function post(string $path, array $payload = [])
    {
        $token = FinanceBffToken::make(auth()->user());

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post(rtrim(config('finance.base_url'), '/') . $path, $payload);
    }
}
