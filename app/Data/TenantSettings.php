<?php

namespace App\Data;

use App\Models\Tenant;

final readonly class TenantSettings
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        public string $locale = 'en',
        public string $timezone = 'UTC',
        public string $baseCurrency = 'USD',
        public string $collectionCurrency = 'USD',
        public string $dateFormat = 'Y-m-d',
        public string $timeFormat = 'H:i',
        public bool $rtl = false,
        public array $settings = [],
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $settings = $tenant->settings ?? [];

        return new self(
            locale: (string) ($settings['locale'] ?? $tenant->locale ?? 'en'),
            timezone: (string) ($settings['timezone'] ?? $tenant->timezone ?? 'UTC'),
            baseCurrency: (string) ($settings['base_currency'] ?? $tenant->base_currency ?? 'USD'),
            collectionCurrency: (string) ($settings['collection_currency'] ?? $tenant->collection_currency ?? 'USD'),
            dateFormat: (string) ($settings['date_format'] ?? 'Y-m-d'),
            timeFormat: (string) ($settings['time_format'] ?? 'H:i'),
            rtl: (bool) ($settings['rtl'] ?? in_array($settings['locale'] ?? $tenant->locale, ['ar', 'fa', 'he', 'ur'], true)),
            settings: $settings,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->settings,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'base_currency' => $this->baseCurrency,
            'collection_currency' => $this->collectionCurrency,
            'date_format' => $this->dateFormat,
            'time_format' => $this->timeFormat,
            'rtl' => $this->rtl,
        ];
    }
}
