<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SeedUsageBenchmarkCommand extends Command
{
    protected $signature = 'platform:seed-usage-benchmark
        {--tenant=benchmark : Existing tenant slug to populate}
        {--count=50000 : Target number of benchmark services}
        {--usage-days=90 : Number of daily usage rows per service}
        {--yes : Confirm that this command writes benchmark data}';

    protected $description = 'Seed an isolated tenant with reproducible service, session and usage benchmark data.';

    public function handle(Tenancy $tenancy): int
    {
        if (! $this->option('yes')) {
            $this->error('This writes benchmark data. Re-run with --yes in an isolated tenant.');

            return self::FAILURE;
        }

        $count = $this->integerOption('count', 1, 100_000);
        $usageDays = $this->integerOption('usage-days', 1, 365);
        $slug = (string) $this->option('tenant');
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('No matching tenant was found. Create the isolated tenant first.');
        }

        return $tenancy->run($tenant, function () use ($count, $usageDays): int {
            $plan = Plan::query()->firstOrCreate(
                ['slug' => 'benchmark-100'],
                ['name' => 'Benchmark 100', 'download_kbps' => 100_000, 'upload_kbps' => 25_000, 'duration_days' => 30, 'amount_minor' => 6500, 'currency' => 'USD', 'status' => 'active'],
            );
            $this->seedCustomers($count);
            $services = $this->seedServices($count, $plan->id);
            $this->seedSessions($services);
            $this->seedUsage($services, $usageDays);

            $this->info(sprintf('Benchmark tenant ready: %d services, %d usage days, %d usage rows.', $services->count(), $usageDays, $services->count() * $usageDays));

            return self::SUCCESS;
        });
    }

    private function seedCustomers(int $count): void
    {
        $tenantId = app(Tenancy::class)->requireId();
        $existing = DB::table('customers')->where('tenant_id', $tenantId)->where('code', 'like', 'BENCH-CUS-%')->pluck('code')->flip();
        $rows = [];
        $now = now()->toDateTimeString();

        for ($index = 1; $index <= $count; $index++) {
            $code = 'BENCH-CUS-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
            if ($existing->has($code)) {
                continue;
            }
            $phone = '96170'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
            $rows[] = [
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'code' => $code,
                'first_name' => 'Benchmark',
                'last_name' => 'Customer '.$index,
                'phone' => '+'.$phone,
                'phone_normalized' => $phone,
                'email' => 'benchmark-'.$index.'@example.test',
                'status' => 'active',
                'balance_amount' => 0,
                'balance_currency' => 'USD',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) === 1000) {
                DB::table('customers')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('customers')->insert($rows);
        }
    }

    /** @return Collection<int, \stdClass> */
    private function seedServices(int $count, int $planId): Collection
    {
        $tenantId = app(Tenancy::class)->requireId();
        $existing = DB::table('services')->where('tenant_id', $tenantId)->where('username', 'like', 'bench-%')->pluck('username')->flip();
        $rows = [];
        $now = now()->toDateTimeString();

        for ($index = 1; $index <= $count; $index++) {
            $username = 'bench-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
            if ($existing->has($username)) {
                continue;
            }
            $customerCode = 'BENCH-CUS-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
            $customerId = DB::table('customers')->where('tenant_id', $tenantId)->where('code', $customerCode)->value('id');
            if ($customerId === null) {
                throw new RuntimeException('Benchmark customer '.$customerCode.' is missing.');
            }
            $rows[] = [
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'plan_id' => $planId,
                'username' => $username,
                'status' => 'active',
                'provisioning_mode' => 'manual',
                'network_state' => 'in_sync',
                'desired_state_version' => 1,
                'activated_at' => $now,
                'expires_at' => now()->addDays(30)->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) === 1000) {
                DB::table('services')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('services')->insert($rows);
        }

        return DB::table('services')->where('tenant_id', $tenantId)->where('username', 'like', 'bench-%')->orderBy('id')->get(['id', 'public_id', 'username']);
    }

    /** @param Collection<int, \stdClass> $services */
    private function seedSessions(Collection $services): void
    {
        $tenantId = app(Tenancy::class)->requireId();
        $serviceIds = $services->pluck('id')->all();
        $existing = DB::table('sessions_current')->where('tenant_id', $tenantId)->whereIn('service_id', $serviceIds)->pluck('service_id')->flip();
        $now = now();
        $rows = [];

        foreach ($services as $index => $service) {
            if ($existing->has($service->id)) {
                continue;
            }
            $rows[] = [
                'tenant_id' => $tenantId,
                'service_id' => $service->id,
                'username' => $service->username,
                'acct_session_id' => 'bench-'.$service->public_id,
                'nasname' => 'benchmark-nas-'.(($index % 10) + 1),
                'framed_ip' => '10.'.(($index % 250) + 1).'.'.(($index % 250) + 1).'.'.(($index % 240) + 10),
                'acct_start_time' => $now->copy()->subHours(2)->toDateTimeString(),
                'last_seen_at' => $now->toDateTimeString(),
                'input_octets' => 5_000_000,
                'output_octets' => 15_000_000,
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ];
            if (count($rows) === 1000) {
                DB::table('sessions_current')->insertOrIgnore($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('sessions_current')->insertOrIgnore($rows);
        }
    }

    /** @param Collection<int, \stdClass> $services */
    private function seedUsage(Collection $services, int $usageDays): void
    {
        $tenantId = app(Tenancy::class)->requireId();
        $now = now()->toDateTimeString();
        for ($day = 0; $day < $usageDays; $day++) {
            $date = CarbonImmutable::today()->subDays($day)->toDateString();
            $rows = [];
            foreach ($services as $index => $service) {
                $input = 1_000_000 + (($index + 1) * 1000) + ($day * 10_000);
                $output = 3_000_000 + (($index + 1) * 2000) + ($day * 20_000);
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'service_id' => $service->id,
                    'usage_date' => $date,
                    'input_octets' => $input,
                    'output_octets' => $output,
                    'total_octets' => $input + $output,
                    'rolled_up_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($rows) === 1000) {
                    DB::table('usage_daily')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                DB::table('usage_daily')->insertOrIgnore($rows);
            }
        }
    }

    private function integerOption(string $name, int $min, int $max): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new RuntimeException('--'.$name.' must be an integer between '.$min.' and '.$max.'.');
        }

        return $value;
    }
}
