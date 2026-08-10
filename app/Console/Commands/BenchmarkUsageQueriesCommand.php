<?php

namespace App\Console\Commands;

use App\Models\CurrentSession;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BenchmarkUsageQueriesCommand extends Command
{
    protected $signature = 'platform:benchmark-usage
        {--tenant= : Tenant slug to benchmark}
        {--service= : Service public ID to use for the usage query}
        {--from= : Inclusive usage date, defaults to 89 days ago}
        {--to= : Inclusive usage date, defaults to today}
        {--repetitions=5 : Number of executions per query}
        {--json : Emit machine-readable JSON instead of a table}';

    protected $description = 'Benchmark the live-session and portal usage query shapes with EXPLAIN evidence.';

    public function handle(Tenancy $tenancy): int
    {
        $tenant = Tenant::query()
            ->when($this->option('tenant'), fn ($query, string $slug) => $query->where('slug', $slug))
            ->first();

        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('No matching tenant was found. Pass --tenant=<slug>.');
        }

        $repetitions = filter_var($this->option('repetitions'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if ($repetitions === false) {
            throw new RuntimeException('--repetitions must be an integer between 1 and 50.');
        }

        $to = $this->dateOption('to', CarbonImmutable::today());
        $from = $this->dateOption('from', $to->subDays(89));
        if ($from->isAfter($to)) {
            throw new RuntimeException('--from must be on or before --to.');
        }

        $result = $tenancy->run($tenant, function () use ($from, $to, $repetitions): array {
            $serviceId = $this->usageServiceId();
            $queries = [
                'live_sessions' => [
                    'threshold_ms' => 200,
                    'query' => fn (): Builder => CurrentSession::query()
                        ->select(['service_id', 'username', 'acct_session_id', 'nasname', 'framed_ip', 'last_seen_at', 'input_octets', 'output_octets'])
                        ->whereNull('stopped_at')
                        ->orderByDesc('last_seen_at')
                        ->limit(50),
                ],
                'usage_chart' => [
                    'threshold_ms' => 500,
                    'query' => fn (): Builder => UsageDaily::query()
                        ->select(['service_id', 'usage_date', 'input_octets', 'output_octets', 'total_octets'])
                        ->where('service_id', $serviceId)
                        ->whereBetween('usage_date', [$from->toDateString(), $to->toDateString()])
                        ->orderBy('usage_date'),
                ],
            ];

            $measurements = [];
            foreach ($queries as $name => $definition) {
                $durations = [];
                $rowCount = 0;
                for ($iteration = 0; $iteration < $repetitions; $iteration++) {
                    $started = hrtime(true);
                    $rowCount = count($definition['query']()->get()->all());
                    $durations[] = (hrtime(true) - $started) / 1_000_000;
                }

                sort($durations);
                $p95 = $durations[max(0, (int) ceil(count($durations) * 0.95) - 1)];
                $measurements[$name] = [
                    'threshold_ms' => $definition['threshold_ms'],
                    'rows' => $rowCount,
                    'p50_ms' => round($durations[(int) floor((count($durations) - 1) / 2)], 2),
                    'p95_ms' => round($p95, 2),
                    'passed' => $p95 < (float) $definition['threshold_ms'],
                    'explain' => $this->explain($definition['query']()),
                ];
            }

            return [
                'driver' => DB::connection()->getDriverName(),
                'tenant' => (string) Tenant::query()->findOrFail(app(Tenancy::class)->requireId())->slug,
                'service_id' => $serviceId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'repetitions' => $repetitions,
                'measurements' => $measurements,
                'passed' => collect($measurements)->every(fn (array $measurement): bool => $measurement['passed']),
            ];
        });

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Query', 'Rows', 'p50 (ms)', 'p95 (ms)', 'Limit (ms)', 'Status'], collect($result['measurements'])->map(
                fn (array $measurement, string $name): array => [$name, $measurement['rows'], $measurement['p50_ms'], $measurement['p95_ms'], $measurement['threshold_ms'], $measurement['passed'] ? 'PASS' : 'FAIL'],
            )->all());
            $this->line('Tenant: '.$result['tenant'].' · Driver: '.$result['driver'].' · Service: '.($result['service_id'] ?? 'none'));
        }

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function usageServiceId(): int
    {
        $publicId = $this->option('service');
        $query = UsageDaily::query()->select('service_id');

        if (is_string($publicId) && $publicId !== '') {
            $query->whereHas('service', fn (Builder $service) => $service->where('public_id', $publicId));
        } else {
            $query->orderByDesc('total_octets');
        }

        $serviceId = $query->value('service_id');
        if ($serviceId === null) {
            throw new RuntimeException('No usage rows were found. Load the isolated 50k-service benchmark dataset first.');
        }

        return (int) $serviceId;
    }

    /** @return array{format: string, rows: list<mixed>} */
    private function explain(Builder $query): array
    {
        $sql = $query->toBase()->toSql();
        $bindings = $query->toBase()->getBindings();
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $rows = DB::select('EXPLAIN (FORMAT JSON) '.$sql, $bindings);

            return ['format' => 'json', 'rows' => array_map(static fn (object $row): mixed => $row->{'QUERY PLAN'} ?? $row, $rows)];
        }

        if ($driver === 'sqlite') {
            return ['format' => 'query-plan', 'rows' => DB::select('EXPLAIN QUERY PLAN '.$sql, $bindings)];
        }

        return ['format' => 'text', 'rows' => DB::select('EXPLAIN '.$sql, $bindings)];
    }

    private function dateOption(string $name, CarbonImmutable $default): CarbonImmutable
    {
        $value = $this->option($name);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable $exception) {
            throw new RuntimeException('--'.$name.' must be a valid date.', previous: $exception);
        }
    }
}
