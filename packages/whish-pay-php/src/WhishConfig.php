<?php

declare(strict_types=1);

namespace WhishPay;

use WhishPay\Exceptions\WhishConfigurationException;

final readonly class WhishConfig
{
    public const SANDBOX_BASE_URL = 'https://lb.sandbox.whish.money/itel-service/api';
    public const PRODUCTION_BASE_URL = 'https://whish.money/itel-service/api';

    public function __construct(
        public string $channel,
        public string $secret,
        public string $websiteUrl,
        public string $environment = 'sandbox',
        public ?string $baseUrl = null,
        public int $timeout = 15,
    ) {
        if (trim($this->channel) === '' || trim($this->secret) === '' || trim($this->websiteUrl) === '') {
            throw new WhishConfigurationException('Whish channel, secret, and website URL are required.');
        }
        if (! in_array($this->environment, ['sandbox', 'production'], true)) {
            throw new WhishConfigurationException('Whish environment must be sandbox or production.');
        }
        if ($this->baseUrl !== null && trim($this->baseUrl) === '') {
            throw new WhishConfigurationException('Whish base URL cannot be blank.');
        }
        if ($this->timeout < 1) {
            throw new WhishConfigurationException('Whish timeout must be positive.');
        }
    }

    public function resolvedBaseUrl(): string
    {
        return rtrim($this->baseUrl ?? ($this->environment === 'production' ? self::PRODUCTION_BASE_URL : self::SANDBOX_BASE_URL), '/');
    }
}
