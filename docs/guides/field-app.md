# Field-app and mobile API handover

The mobile client uses the versioned API under `/api/v1`. The server returns public IDs for customer, service, work-order, invoice, payment, and ticket references; clients must not depend on internal database IDs.

## Authentication

Issue a device-specific Sanctum token through `POST /api/v1/tokens` with `email`, `password`, `device_name`, and the smallest required ability. Use `Authorization: Bearer <token>` on subsequent requests. If the user policy requires 2FA, include `otp`; token issuance returns `423` without a valid code.

Supported token abilities are `staff:collector`, `staff:technician`, `staff:operator`, and `customer`. The API enforces both the token ability and the user’s tenant-scoped permissions.

## Collector flow

1. `GET /api/v1/collector/sync/bootstrap?zone=<zone>` downloads the initial customer/service/payment snapshot and a signed cursor.
2. Store the cursor securely on the device. Request changes with `GET /api/v1/collector/sync/delta?since=<cursor>&zone=<zone>`.
3. Queue offline payments locally with a stable idempotency key per payment attempt.
4. Push at most 100 queued items at a time with `POST /api/v1/collector/sync/push` or `POST /api/v1/collector/payments/batch`.
5. Persist each item’s `created`, `replayed`, or `rejected` result. A replay is not a new payment; surface a rejected item for operator resolution.

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

`amount` is an integer minor-unit amount. Currency must match the customer ledger currency. Do not retry with a new key after a network timeout.

## Technician flow

- `GET /api/v1/technician/work-orders?date=YYYY-MM-DD&status=assigned` lists work orders assigned to the authenticated technician only.
- `GET /api/v1/technician/work-orders/{workOrder}` loads the checklist, metadata, customer, service, event history, and safe media metadata.
- `GET /api/v1/technician/services/{service}/diagnostics` loads service diagnostics.
- `GET /api/v1/technician/inventory` loads the technician’s assigned inventory.
- `POST /api/v1/technician/uploads` accepts one JPEG, PNG, or WebP image up to 20 MB as multipart field `file`.
- `POST /api/v1/technician/work-orders/{workOrder}/media` accepts one assigned-work-order JPEG, PNG, or WebP image up to 20 MB as multipart `file`, with optional `purpose` of `evidence` or `other`.
- `POST /api/v1/technician/work-orders/{workOrder}/complete` completes the assigned order. Send `X-Idempotency-Key` so an offline retry cannot complete it twice.

Only the assigned technician can read, upload evidence to, or complete a work order. Completing an installation may activate the linked service and enqueue a network command; show the returned status as a pending operational action when appropriate. The API returns media IDs and metadata, never storage paths or hashes.

## Client safety rules

- Treat `401` as an expired/revoked token and require sign-in again.
- Treat `403` as an ability or assignment problem; do not retry blindly.
- Treat `409` or an idempotency replay as a reconciliation result, not a failure.
- Treat `422` as a user-correctable validation problem and preserve the local item for editing.
- Use exponential backoff for `429`, `5xx`, and transport failures, while preserving idempotency keys.
- Encrypt local customer/payment data, protect the device, and clear cached data on logout or device reassignment.

The complete machine-readable contract is [openapi/isp-platform-v1.yaml](../../openapi/isp-platform-v1.yaml). The mobile client should generate types from that document and cover the collector and technician contract tests before pilot release.
