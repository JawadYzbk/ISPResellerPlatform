<?php

namespace App\Domain\Radius;

use App\Models\Router;
use DomainException;
use RuntimeException;

final class CoaClient
{
    public function __construct(private RadiusTransport $transport) {}

    public function disconnect(Router $router, string $username, ?string $sessionId = null): CoaResult
    {
        return $this->request($router, 40, 41, $username, $sessionId);
    }

    /** @param list<array{type: int, value: string}> $attributes */
    public function changeOfAuthorization(Router $router, string $username, ?string $sessionId, array $attributes = []): CoaResult
    {
        return $this->request($router, 43, 44, $username, $sessionId, $attributes);
    }

    /** @param list<array{type: int, value: string}> $attributes */
    private function request(Router $router, int $requestCode, int $ackCode, string $username, ?string $sessionId, array $attributes = []): CoaResult
    {
        $secret = (string) $router->radius_secret_encrypted;
        if ($secret === '') {
            throw new DomainException('The router has no RADIUS shared secret configured.');
        }
        $encodedAttributes = $this->attribute(1, $username);
        if ($sessionId !== null) {
            $encodedAttributes .= $this->attribute(44, $sessionId);
        }
        foreach ($attributes as $attribute) {
            $encodedAttributes .= $this->attribute($attribute['type'], $attribute['value']);
        }

        $identifier = random_int(0, 255);
        $authenticator = random_bytes(16);
        $packet = pack('CCn', $requestCode, $identifier, 20 + strlen($encodedAttributes)).$authenticator.$encodedAttributes;
        $response = $this->transport->send((string) $router->host, (int) $router->coa_port, $packet);
        $responseCode = $this->validateResponse($response, $identifier, $authenticator, $secret);
        if ($responseCode !== $ackCode) {
            return new CoaResult('nak', $requestCode, $responseCode);
        }

        return new CoaResult('ack', $requestCode, $responseCode);
    }

    private function validateResponse(?string $response, int $identifier, string $requestAuthenticator, string $secret): ?int
    {
        if ($response === null || strlen($response) < 20) {
            return null;
        }
        $responseCode = ord($response[0]);
        $responseIdentifier = ord($response[1]);
        $responseLength = unpack('n', substr($response, 2, 2))[1] ?? 0;
        if ($responseIdentifier !== $identifier || $responseLength !== strlen($response)) {
            throw new RuntimeException('The RADIUS response header is invalid.');
        }
        $responseAuthenticator = substr($response, 4, 16);
        $attributes = substr($response, 20);
        $expected = md5(pack('CCn', $responseCode, $responseIdentifier, $responseLength).$requestAuthenticator.$attributes.$secret, true);
        if (! hash_equals($expected, $responseAuthenticator)) {
            throw new RuntimeException('The RADIUS response authenticator is invalid.');
        }

        return $responseCode;
    }

    private function attribute(int $type, string $value): string
    {
        $length = strlen($value) + 2;
        if ($length > 255) {
            throw new RuntimeException('RADIUS attribute values cannot exceed 253 bytes.');
        }

        return pack('CC', $type, $length).$value;
    }
}
