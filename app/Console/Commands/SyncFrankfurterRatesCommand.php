<?php

namespace App\Console\Commands;

use App\Actions\ImportFrankfurterExchangeRates;
use App\Models\Tenant;
use Illuminate\Console\Command;

final class SyncFrankfurterRatesCommand extends Command
{
    protected $signature = 'fx:sync-frankfurter {--quotes= : Comma-separated quote currencies; defaults to FRANKFURTER_QUOTES plus each tenant collection currency}';

    protected $description = 'Import effective-dated FX rates from Frankfurter without rewriting existing rates.';

    public function handle(ImportFrankfurterExchangeRates $import): int
    {
        if (! (bool) config('services.frankfurter.enabled', false)) {
            $this->line('Frankfurter sync is disabled.');

            return self::SUCCESS;
        }

        $configured = $this->option('quotes');
        $configuredQuotes = is_string($configured) && trim($configured) !== ''
            ? explode(',', $configured)
            : config('services.frankfurter.quotes', []);
        $failed = false;

        Tenant::query()->each(function (Tenant $tenant) use ($configuredQuotes, $import, &$failed): void {
            try {
                $quotes = array_values(array_unique([
                    ...array_map('trim', array_filter($configuredQuotes, is_string(...))),
                    (string) $tenant->collection_currency,
                ]));
                $count = $import->handle($tenant, $quotes);
                $this->line($tenant->slug.': '.$count.' Frankfurter rate(s) imported');
            } catch (\Throwable $exception) {
                $failed = true;
                $this->error($tenant->slug.': '.$exception->getMessage());
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
