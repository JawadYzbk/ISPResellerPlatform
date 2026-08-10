<?php

namespace App\Domain\Payments;

use Illuminate\Support\Facades\Http;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;

final class LaravelWhishTransport implements WhishHttpTransport
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $payload
     */
    public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
    {
        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->timeout($timeout);
        $response = strtoupper($method) === 'GET'
            ? $request->get($url)
            : $request->post($url, $payload ?? []);

        return new WhishHttpResponse($response->status(), $response->body());
    }
}
