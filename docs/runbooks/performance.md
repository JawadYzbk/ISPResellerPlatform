# Performance benchmark runbook

This runbook records the repeatable repository-side evidence for ISP-084/101. The acceptance dataset is isolated from demo and production data: 50,000 services with 90 days of daily usage rows, plus a representative live-session population.

## Run the benchmark

Use a PostgreSQL 17 environment with the benchmark dataset loaded and the same indexes/migrations as the release under test:

```powershell
php artisan migrate --force
php artisan platform:seed-usage-benchmark --tenant=benchmark --count=50000 --usage-days=90 --yes
php artisan platform:benchmark-usage --tenant=benchmark --from=2026-05-13 --to=2026-08-10 --repetitions=10 --json > storage/app/benchmark-usage.json
```

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

This closes the repository-side scale smoke test only. PostgreSQL 17, a production-shaped connection pool, and a documented cold-cache run are still required before the performance acceptance gate is closed.

## Evidence review

The reviewer must confirm:

1. The dataset counts are 50,000 services and 90 days of usage history.
2. The query plan uses the tenant/service/date and tenant/service/stopped indexes rather than a full table scan.
3. Both p95 thresholds pass after at least one warm-cache and one cold-cache run.
4. The result was captured on the release commit with PostgreSQL 17 and the production-shaped connection pool.

Do not run the benchmark against a developer’s SQLite database and call that the scale acceptance. A small local pass is useful for validating the command wiring, but only the isolated PostgreSQL dataset can close the scale gate.
