<?php

namespace App\Domain\Radius;

final readonly class CoaResult
{
    public function __construct(public string $status, public int $requestCode, public ?int $responseCode) {}
}
