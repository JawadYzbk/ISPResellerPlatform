<?php

namespace App\Domain\Payments;

use DomainException;
use WhishPay\WhishClient;
use WhishPay\WhishConfig;
use WhishPay\WhishHttpTransport;

final readonly class WhishClientFactory
{
    public function __construct(private WhishHttpTransport $transport) {}

    public function make(): WhishClient
    {
        if (! config('services.whish.enabled')) {
            throw new DomainException('Whish Pay is not enabled.');
        }

        $channel = config('services.whish.channel');
        $secret = config('services.whish.secret');
        $websiteUrl = config('services.whish.website_url');
        $environment = (string) config('services.whish.environment', 'sandbox');
        if (! is_string($channel) || trim($channel) === '' || ! is_string($secret) || trim($secret) === '' || ! is_string($websiteUrl) || trim($websiteUrl) === '') {
            throw new DomainException('Whish Pay credentials and website URL are not configured.');
        }

        $endpoint = config('services.whish.endpoint');

        return new WhishClient(new WhishConfig(
            channel: $channel,
            secret: $secret,
            websiteUrl: $websiteUrl,
            environment: $environment,
            baseUrl: is_string($endpoint) && trim($endpoint) !== '' ? $endpoint : null,
            timeout: (int) config('services.whish.timeout', 15),
        ), $this->transport);
    }
}
