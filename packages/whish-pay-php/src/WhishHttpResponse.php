<?php

declare(strict_types=1);

namespace WhishPay;

final readonly class WhishHttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
    ) {}
}
