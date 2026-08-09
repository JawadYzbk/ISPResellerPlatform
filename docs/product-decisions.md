# Product decisions

This file records implementation decisions that are narrower than the handoff pack's architectural ADRs.

| Date | Decision | Rationale |
|---|---|---|
| 2026-08-10 | Start with a usable staff slice: login → dashboard → customer index → customer show. | It validates the navigation, tenancy and customer/service model before finance or router work is layered on. |
| 2026-08-10 | Use SQLite for the quick local loop and PostgreSQL 17 in Docker/CI production shape. | Developers can run the first slice without infrastructure; PostgreSQL remains the supported deployment datastore. |
| 2026-08-10 | Do not install Horizon until a Laravel 13-compatible stable release is available. | The package registry currently exposes Horizon 5 stable with Laravel 12 constraints; queue contracts remain Laravel-native for now. |
