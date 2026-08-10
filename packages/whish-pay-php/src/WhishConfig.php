<?php

declare(strict_types=1);

namespace WhishPay;

use WhishPay\Exceptions\WhishConfigurationException;

final readonly class WhishConfig
{
    public const SANDBOX_BASE_URL = 'https://lb.sandbox.whish.money/itel-service/api';

    public const PRODUCTION_BASE_URL = 'https://whish.money/itel-service/api';

    private string $channel;

    private string $secret;

    private string $websiteUrl;

    private string $environment;

    private ?string $baseUrl;

    private int $timeout;

    public function __construct(
        string $channel,
        string $secret,
        string $websiteUrl,
        string $environment = 'sandbox',
        ?string $baseUrl = null,
        int $timeout = 15,
    ) {
        $this->channel = $channel;
        $this->secret = $secret;
        $this->websiteUrl = $websiteUrl;
        $this->environment = $environment;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        if (trim($this->channel) === '' || trim($this->secret) === '' || trim($this->websiteUrl) === '') {
            throw new WhishConfigurationException('Whish channel, secret, and website URL are required.');
        }
        if (! in_array($this->environment, ['sandbox', 'production'], true)) {
            throw new WhishConfigurationException('Whish environment must be sandbox or production.');
        }
        if ($this->baseUrl !== null && (trim($this->baseUrl) === '' || filter_var($this->baseUrl, FILTER_VALIDATE_URL) === false)) {
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

    public function channel(): string
    {
        return $this->channel;
    }

    public function secret(): string
    {
        return $this->secret;
    }

    public function websiteUrl(): string
    {
        return $this->websiteUrl;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }
}
