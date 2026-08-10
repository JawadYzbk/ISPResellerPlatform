<?php

declare(strict_types=1);

namespace WhishPay\Exceptions;

final class WhishApiException extends WhishException
{
    public function __construct(string $message, public readonly ?string $providerCode = null)
    {
        parent::__construct($message);
    }
}
