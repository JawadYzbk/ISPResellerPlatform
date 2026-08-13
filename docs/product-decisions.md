# Product decisions

This file records implementation decisions that are narrower than the handoff pack's architectural ADRs.

| Date | Decision | Rationale |
|---|---|---|
| 2026-08-10 | Start with a usable staff slice: login → dashboard → customer index → customer show. | It validates the navigation, tenancy and customer/service model before finance or router work is layered on. |
| 2026-08-10 | Use SQLite for the quick local loop and PostgreSQL 17 in Docker/CI production shape. | Developers can run the first slice without infrastructure; PostgreSQL remains the supported deployment datastore. |
| 2026-08-10 | Do not install Horizon until a Laravel 13-compatible stable release is available. | The package registry currently exposes Horizon 5 stable with Laravel 12 constraints; queue contracts remain Laravel-native for now. |
| 2026-08-13 | Target verified FiberBills parity for the Lebanon-first web platform, prioritizing Jebaya field operations, billing flexibility, expenses and physical ISP topology; defer native mobile and Bluetooth printing until after a real tenant web pilot. | The current platform already covers core CRM, finance, reseller, support and network foundations. Closing daily field-operation gaps before starting a second client keeps one production workflow authoritative while preserving mobile-ready APIs. |
