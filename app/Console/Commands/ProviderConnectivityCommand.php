<?php

namespace App\Console\Commands;

use App\Actions\CheckProviderConnectivity;
use Illuminate\Console\Command;

final class ProviderConnectivityCommand extends Command
{
    protected $signature = 'platform:provider-check {--json : Print machine-readable JSON output}';

    protected $description = 'Probe enabled external providers without creating payments or messages';

    public function handle(CheckProviderConnectivity $check): int
    {
        $results = $check->handle();

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(
                ['Provider', 'Status', 'Detail'],
                collect($results)->map(fn (array $result, string $provider): array => [$provider, $result['status'], $result['detail']])->values()->all(),
            );
        }

        return collect($results)->contains(fn (array $result): bool => in_array($result['status'], ['failed', 'not_configured'], true))
            ? self::FAILURE
            : self::SUCCESS;
    }
}
