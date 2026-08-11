# Field-app and mobile API handover

The mobile client uses the versioned API under `/api/v1`. The server returns public IDs for customer, service, work-order, invoice, payment, and ticket references; clients must not depend on internal database IDs.

## Authentication

Issue a device-specific Sanctum token through `POST /api/v1/tokens` with `email`, `password`, `device_name`, and the smallest required ability. Use `Authorization: Bearer <token>` on subsequent requests. If the user policy requires 2FA, include `otp`; token issuance returns `423` without a valid code.

Supported token abilities are `staff:collector`, `staff:technician`, `staff:operator`, and `customer`. The API enforces both the token ability and the user’s tenant-scoped permissions.

## Collector flow

1. Open a shift with `POST /api/v1/collector/shift/open`, optionally sending `opening_float` by currency. A collector cannot create a new payment without an open shift.
2. `GET /api/v1/collector/sync/bootstrap?zone=<zone>` downloads the initial customer/service/payment snapshot and a signed cursor.
3. Store the cursor securely on the device. Request changes with `GET /api/v1/collector/sync/delta?since=<cursor>&zone=<zone>`.
4. Queue offline payments locally with a stable idempotency key per payment attempt.
5. Push at most 100 queued items at a time with `POST /api/v1/collector/sync/push` or `POST /api/v1/collector/payments/batch`.
6. Persist each item’s `created`, `replayed`, or `rejected` result. A replay is not a new payment; surface a rejected item for operator resolution.
7. Read `GET /api/v1/collector/shift`, `GET /api/v1/collector/payments?date=YYYY-MM-DD`, and `GET /api/v1/collector/summary?date=YYYY-MM-DD` for reconciliation. Close with `POST /api/v1/collector/shift/close`; a variance requires `variance_note`.

## Browser field desk

Collectors can use the authenticated `/field` workspace from a phone-sized browser without issuing a separate API token. It loads the same tenant-scoped customer snapshot and currency catalog, requires an open cash shift before enabling collection, and sends queued payments through the signed server-side sync actions.

When the connection is unavailable, the browser keeps the latest tenant-scoped customer snapshot and currency catalog together with the payment queue in IndexedDB (with a local-storage fallback for restricted browsing contexts). Each queued item keeps its UUID idempotency key and rejected reason. When connectivity returns, or when the desk is reopened online, the client automatically attempts the persisted queue; accepted or replayed items are removed and rejected items stay visible for review. Use **Clear device data** in the field desk's queue card when a device is reassigned; it removes the cached snapshot and any queued payments after an explicit confirmation, without changing server records. The storage key includes the tenant and user because the snapshot contains customer data. The server response remains authoritative whenever the page can reconnect. This browser fallback is not a substitute for encrypted native mobile storage or an offline authenticated app shell.

For a Whish Pay collection, send `POST /api/v1/collector/payments/whish` with `customer_id`, integer `amount`, supported `currency` (`USD`, `LBP`, or `AED`), and an `X-Idempotency-Key`. The response contains the provider collect URL and an SVG QR data URI for the customer. Poll `GET /api/v1/collector/payments/whish/{attempt}` when the callback is delayed; the server verifies Whish status and settles through the normal ledger path. Do not mark the attempt paid from the QR redirect or callback query parameters alone.

Use `GET /api/v1/collector/customers?q=&zone=&status=due,overdue` for the field collection queue, then open `GET /api/v1/collector/customers/{customer}` for balance, location, service expiry, and last-payment context. Receipt resend is `POST /api/v1/collector/payments/{payment}/receipt` with `channel` set to `whatsapp`, `sms`, or `email`; keep the idempotency key when retrying.

The online single-payment contract is:

```http
POST /api/v1/payments
Authorization: Bearer <token>
X-Idempotency-Key: payment-device-2026-08-10-00042
Content-Type: application/json

{
  "customer_id": "01J...",
  "invoice_id": "01J...",
  "amount": 2500,
  "currency": "USD",
  "method": "cash"
}
```

`amount` is an integer minor-unit amount in the selected payment currency. When collecting in a different currency, use the current rate from the collector snapshot or send an approved `fx_override` with positive numerator/denominator values and an `fx_override_reason`; an optional `reference` is stored on the receipt. The response includes source, ledger, and base equivalents. Do not retry with a new key after a network timeout.

Operator clients can preview and apply service plan changes with `POST /api/v1/services/{service}/plan-change-previews` and `POST /api/v1/services/{service}/change-plan`. Send the target public plan ID and `effective` as `immediate` or `next_cycle`; the latter changes the plan during renewal without altering the current period. Immediate changes return the proration amounts and a queued network command.

Operator clients can renew a service for one to twelve plan periods through the invoice-first flow:

1. `POST /api/v1/services/{service}/renewal-previews` with optional `periods` returns the amount, currency, current expiry, new expiry, and a signed preview token.
2. `POST /api/v1/services/{service}/renewals` with the `preview_id`, matching `periods`, and `X-Idempotency-Key` issues or reuses the renewal invoice.
3. Collect the issued invoice through the payment endpoint. The service expiry advances only after the invoice is fully allocated; underpayments leave it open.

Preview tokens expire after ten minutes. Keep the same idempotency key for retries and treat a replay as the original invoice response.

## Technician flow

- `GET /api/v1/technician/work-orders?date=YYYY-MM-DD&status=assigned` lists work orders assigned to the authenticated technician only; use `en_route`, `in_progress`, `failed`, or `completed` for other queues.
- `GET /api/v1/technician/work-orders/{workOrder}` loads the checklist, metadata, customer, service, event history, readings, consumed materials, signature metadata, and safe media metadata.
- `GET /api/v1/technician/services/{service}/diagnostics` loads service diagnostics.
- `GET /api/v1/technician/inventory` loads the technician’s assigned inventory.
- `POST /api/v1/technician/uploads` accepts one JPEG, PNG, or WebP image up to 20 MB as multipart field `file`.
- `POST /api/v1/technician/work-orders/{workOrder}/media` accepts one assigned-work-order JPEG, PNG, or WebP image up to 20 MB as multipart `file`, with optional `purpose` of `evidence` or `other`.
- `POST /api/v1/technician/work-orders/{workOrder}/signature` accepts a customer signature as a PNG multipart `file` up to 5 MB plus `signer_name`.
- `POST /api/v1/technician/work-orders/{workOrder}/readings` replaces the work-order readings with a `readings` object containing up to 20 string values.
- `POST /api/v1/technician/work-orders/{workOrder}/materials` consumes a positive three-decimal `quantity` from the technician’s assigned van warehouse for an `inventory_item_id`.
- `POST /api/v1/technician/work-orders/{workOrder}/status` records `en_route` or `in_progress` progress, optionally with the device timestamp in `at`.
- `POST /api/v1/technician/work-orders/{workOrder}/fail` records a required `reason`, optional `notes`, and optional `reschedule_at`; captured readings and media remain attached.
- `POST /api/v1/technician/work-orders/{workOrder}/complete` completes the assigned order. Send `X-Idempotency-Key` so an offline retry cannot complete it twice.

Only the assigned technician can read, upload evidence to, record readings/materials, or complete a work order. Completing an installation may activate the linked service and enqueue a network command; show the returned status as a pending operational action when appropriate. The API returns media IDs and metadata, never storage paths or hashes.

## Client safety rules

- Every versioned API response includes `X-Server-Time` in UTC and `X-Request-ID`; use the former to correct device clock drift and the latter when reporting a support issue.
- Treat `401` as an expired/revoked token and require sign-in again.
- Treat `403` as an ability or assignment problem; do not retry blindly.
- Treat `409` or an idempotency replay as a reconciliation result, not a failure.
- Treat `422` as a user-correctable validation problem and preserve the local item for editing.
- Use exponential backoff for `429`, `5xx`, and transport failures, while preserving idempotency keys.
- Encrypt local customer/payment data, protect the device, and clear cached data on logout or device reassignment.

The complete machine-readable contract is [openapi/isp-platform-v1.yaml](../../openapi/isp-platform-v1.yaml), and the running application serves the same file at `/docs/api`. The mobile client should generate types from that document and cover the collector and technician contract tests before pilot release.
