<?php

declare(strict_types=1);

namespace WhishPay;

use RuntimeException;

final class CurlWhishHttpTransport implements WhishHttpTransport
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $payload
     */
    public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the Whish HTTP client.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, min($timeout, 10)),
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($payload !== null) {
            try {
                $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (\JsonException $exception) {
                curl_close($handle);
                throw new RuntimeException('Unable to encode the Whish request.', previous: $exception);
            }
        }

        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('Whish HTTP request failed: '.$error);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new WhishHttpResponse($statusCode, $body);
    }
}
