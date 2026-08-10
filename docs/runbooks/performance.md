# Performance benchmark runbook

This runbook records the repeatable repository-side evidence for ISP-084/101. The acceptance dataset is isolated from demo and production data: 50,000 services with 90 days of daily usage rows, plus a representative live-session population.

## Run the benchmark

Use a PostgreSQL 17 environment with the benchmark dataset loaded and the same indexes/migrations as the release under test:

```powershell
php artisan migrate --force
php artisan platform:seed-usage-benchmark --tenant=benchmark --count=50000 --usage-days=90 --yes
php artisan platform:benchmark-usage --tenant=benchmark --from=2026-05-13 --to=2026-08-10 --repetitions=10 --json > storage/app/benchmark-usage.json
```

For the repository's Docker topology, use the isolated acceptance overlay so an existing local PostgreSQL or Redis service is not touched:

```powershell
$composeArgs = @('-p', 'isp-platform-acceptance', '-f', 'docker-compose.yml', '-f', 'docker-compose.acceptance.yml')
docker compose @composeArgs up -d postgres redis
docker compose @composeArgs build app
docker compose @composeArgs run --rm --no-deps app composer install --no-interaction
docker compose @composeArgs run --rm --no-deps app php artisan migrate:fresh --force
docker compose @composeArgs run --rm --no-deps app php artisan tinker --execute="App\Models\Tenant::factory()->create(['name' => 'Benchmark', 'slug' => 'benchmark']);"
docker compose @composeArgs run --rm --no-deps app php artisan platform:seed-usage-benchmark --tenant=benchmark --count=50000 --usage-days=90 --yes
docker compose @composeArgs run --rm --no-deps app php artisan platform:benchmark-usage --tenant=benchmark --from=2026-05-13 --to=2026-08-10 --repetitions=10 --json
docker compose @composeArgs down -v
```

The overlay maps PostgreSQL to `127.0.0.1:55433` and Redis to `127.0.0.1:6380`; the application connects to the service names on the Compose network.

The seed command is additive and deterministic: it creates the named benchmark records only when they are missing, and requires `--yes` as an explicit write confirmation. Run it only in an isolated tenant or disposable database.

The command measures these production query shapes inside the tenant scope:

- `live_sessions`: the current-session list, limited to the most recent 50 active sessions;
- `usage_chart`: one service’s daily usage over the requested date range.

It emits p50/p95 timings and the database `EXPLAIN` output. The command exits non-zero if p95 exceeds 200 ms for live sessions or 500 ms for the usage chart. The JSON artifact belongs in the release evidence, not in Git.

## Local scale evidence

On 2026-08-10, the benchmark was run in a disposable PostgreSQL 18.4 cluster with 50,000 services and 4,500,000 daily usage rows:

- `live_sessions`: p50 0.84 ms, p95 2.69 ms, 50 rows; passed the 200 ms threshold.
- `usage_chart`: p50 1.30 ms, p95 2.62 ms, 90 rows; passed the 500 ms threshold.
- `EXPLAIN` selected `sessions_current_tenant_id_last_seen_at_index` and `usage_daily_tenant_id_service_id_usage_date_unique`; neither query used a full table scan.

## PostgreSQL 17 acceptance evidence

On 2026-08-10, the same dataset was loaded into the repository's disposable PostgreSQL 17 Docker service using the acceptance overlay:

- Dataset: 50,000 services, 90 usage days and 4,500,000 daily usage rows.
- Warm run: `live_sessions` p50 0.87 ms, p95 6.99 ms; `usage_chart` p50 1.17 ms, p95 2.23 ms; passed both thresholds.
- Post-restart run: `live_sessions` p50 0.91 ms, p95 6.71 ms; `usage_chart` p50 1.23 ms, p95 2.24 ms; passed both thresholds.
- `EXPLAIN` selected `sessions_current_tenant_id_last_seen_at_index` and `usage_daily_tenant_id_service_id_usage_date_unique`; neither query used a full table scan.

This closes the repository-side PostgreSQL 17 data, query-plan and process-restart evidence. A production-shaped connection pool and an infrastructure-level cold-cache run remain external acceptance gates.

## Evidence review

The reviewer must confirm:

1. The dataset counts are 50,000 services and 90 days of usage history.
2. The query plan uses the tenant/service/date and tenant/service/stopped indexes rather than a full table scan.
3. Both p95 thresholds pass after at least one warm-cache and one cold-cache run; the repository evidence above uses a post-restart process run as the local cold-process check.
4. The result was captured on the release commit with PostgreSQL 17 and the production-shaped connection pool.

Do not run the benchmark against a developer’s SQLite database and call that the scale acceptance. A small local pass is useful for validating the command wiring, but only the isolated PostgreSQL dataset can close the scale gate.
