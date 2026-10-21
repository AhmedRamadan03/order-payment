# API Reference

Base URL: `http://127.0.0.1:8000/api`

Protected requests must include:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

## Standard response envelope

Every API response uses the same six top-level keys. Only the values and `data` / `errors` contents vary.

Successful response:
```json
{
  "success": true,
  "type": "success",
  "message": "Order retrieved successfully.",
  "data": {},
  "errors": {},
  "code": null
}
```

Warning response (the request was handled, but the business outcome needs attention, such as a declined simulated payment):
```json
{
  "success": true,
  "type": "warning",
  "message": "Payment was processed but not successful.",
  "data": {},
  "errors": {},
  "code": "PAYMENT_FAILED"
}
```

Error response:
```json
{
  "success": false,
  "type": "error",
  "message": "The given data was invalid.",
  "data": null,
  "errors": {
    "email": ["The email field is required."]
  },
  "code": "VALIDATION_ERROR"
}
```

- `success`: whether the API operation was completed successfully. A declined payment attempt is returned as a warning because the attempt was recorded, while its payment status is `failed`.
- `type`: `success`, `warning`, or `error`.
- `message`: human-readable summary.
- `data`: response resource or payload; `null` on errors.
- `errors`: field validation errors, or an empty object when not applicable.
- `code`: stable machine-readable error/warning code, or `null` for ordinary success.

Validation, authentication, missing-resource, rate-limit, and unexpected server errors are formatted centrally. Internal exception details are not returned to API clients.

Successful resource data is shaped by API Resource classes under `app/Http/Resources`. List endpoints return a paginated object inside `data`, with the resource items in `data.data` and pagination metadata such as `current_page`, `per_page`, and `total` alongside them. All list endpoints accept `per_page` (1–100); order payments also accept the `status` filter.

## Authentication

### Register — `POST /auth/register`

Request:
```json
{
  "name": "Jane Developer",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Success (`201 Created`): `data` contains `user`, `access_token`, `token_type`, and `expires_in`.

### Login — `POST /auth/login`

Request:
```json
{"email": "jane@example.com", "password": "password123"}
```

Invalid credentials return `401 Unauthorized` with code `INVALID_CREDENTIALS`.

Authenticated endpoints: `GET /auth/me`, `POST /auth/refresh`, and `POST /auth/logout`.

## Orders

### Create — `POST /orders`

Request:
```json
{
  "items": [
    {"product_name": "Keyboard", "quantity": 2, "price": "25.50"},
    {"product_name": "Mouse", "quantity": 1, "price": "10.00"}
  ]
}
```

Success (`201 Created`): the order starts as `pending`; the server calculates the total as `61.00`. The created order is returned in `data`:

```json
{
  "success": true,
  "type": "success",
  "message": "Order created successfully.",
  "data": {
    "id": 12,
    "status": "pending",
    "total_amount": "61.00",
    "items": [
      {"id": 31, "product_name": "Keyboard", "quantity": 2, "price": "25.50", "line_total": "51.00"},
      {"id": 32, "product_name": "Mouse", "quantity": 1, "price": "10.00", "line_total": "10.00"}
    ]
  },
  "errors": {},
  "code": null
}
```

List/filter/paginate: `GET /orders?status=confirmed&per_page=15`.
Read one order: `GET /orders/{id}`.
Update items: `PUT /orders/{id}`.
Delete: `DELETE /orders/{id}`.
Change status: `PATCH /orders/{id}/status` with `{"status":"confirmed"}` before processing payment.

Order status is limited to `pending`, `confirmed`, and `cancelled`. An order is only visible to its owner. New orders start `pending`; the client confirms an order through the status endpoint before payment.

Delete conflict (`409 Conflict`) if the order has any payment; code: `ORDER_HAS_PAYMENTS`.
A request for an order owned by another user returns `404 Not Found` with code `RESOURCE_NOT_FOUND` to avoid disclosing its existence.

## Payments

### Process — `POST /orders/{id}/payments`

Request:
```json
{"method": "credit_card", "simulate_failure": false}
```

`simulate_failure` is optional and defaults to `false`. Set it to `true` to simulate a gateway decline:

```json
{"method": "credit_card", "simulate_failure": true}
```

Available methods are configured in `config/payment.php`, currently `credit_card` and `paypal`. Payment is only allowed after the order is confirmed. An order may have only one pending or successful payment; failed payments may be retried. A successful payment does not change the order status. If a gateway call throws or has an unknown outcome, the payment remains pending and blocks status changes and another attempt. This task does not provide a reconciliation endpoint.

A successful payment result returns `201 Created`, `type: success`, and the payment with its confirmed order summary in `data`:

```json
{
  "success": true,
  "type": "success",
  "message": "Payment completed successfully.",
  "data": {
    "id": 8,
    "order_id": 12,
    "status": "successful",
    "method": "credit_card",
    "amount": "61.00",
    "transaction_id": "sim_cc_...",
    "order": {"id": 12, "status": "confirmed", "total_amount": "61.00"}
  },
  "errors": {},
  "code": null
}
```

If payment is attempted before confirmation, the API responds `422 Unprocessable Content` and does not create a payment:

```json
{
  "success": false,
  "type": "error",
  "message": "The given data was invalid.",
  "data": null,
  "errors": {"order": ["Payments can only be processed for confirmed orders."]},
  "code": "VALIDATION_ERROR"
}
```

With `simulate_failure: true`, the API records a failed payment and returns `201 Created`, `type: warning`, code `PAYMENT_FAILED`, and the failed payment in `data`:

```json
{
  "success": true,
  "type": "warning",
  "message": "Payment was processed but not successful.",
  "data": {
    "id": 9,
    "order_id": 12,
    "status": "failed",
    "method": "credit_card",
    "amount": "61.00",
    "transaction_id": "cc_declined_...",
    "order": {"id": 12, "status": "confirmed", "total_amount": "61.00"}
  },
  "errors": {},
  "code": "PAYMENT_FAILED"
}
```

Retry the same confirmed order with `{"method":"credit_card"}` (or `simulate_failure: false`) to simulate a successful payment. If a gateway throws, the payment remains pending. This task does not include a reconciliation endpoint or real provider integration.

Payment listing/detail endpoints: `GET /payments?status=successful&per_page=15`, `GET /payments/{id}`, and `GET /orders/{id}/payments?status=failed&per_page=15`. Every query is scoped to the authenticated user. Payment responses expose selected fields through `PaymentResource`; raw gateway response data is not returned.

## Error codes and HTTP status codes

| HTTP status | Code | Meaning |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Missing or invalid authentication |
| 401 | `INVALID_CREDENTIALS` | Invalid email/password |
| 403 | `FORBIDDEN` | Action is not allowed |
| 404 | `RESOURCE_NOT_FOUND` | Resource does not exist or is not owned by the user |
| 405 | `METHOD_NOT_ALLOWED` | Unsupported HTTP method |
| 409 | `ORDER_HAS_PAYMENTS` | Cannot delete an order with payment records |
| 409 | `PAID_ORDER_STATUS_LOCKED` | Cannot change an order with pending or successful payment away from confirmed |
| 422 | `VALIDATION_ERROR` | Invalid request fields or business-rule validation |
| 429 | `TOO_MANY_REQUESTS` | Rate limit exceeded |
| 500 | `INTERNAL_SERVER_ERROR` | Unexpected server error; internal details are hidden |

The bundled gateways are deterministic simulations, not real payment integrations. Production gateways should verify provider responses and use provider-specific idempotency and reconciliation before being enabled.
