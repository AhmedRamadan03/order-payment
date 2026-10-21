# Extendable Order and Payment Management API

Laravel 13 REST API for order management and simulated payments. It uses JWT authentication, server-side total calculation, pagination, and a Strategy Pattern payment gateway layer.

## Requirements and setup

- PHP 8.3+, Composer 2, and PostgreSQL or MySQL.
- Required PHP extensions for Laravel and `php-open-source-saver/jwt-auth`, including `pdo_pgsql` for PostgreSQL or `pdo_mysql` for MySQL. The test suite uses in-memory SQLite and also needs `pdo_sqlite`.

```bash
git clone https://github.com/AhmedRamadan03/order-payment.git
cd order-payment
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

The committed `composer.lock` pins the resolved dependency versions for repeatable installs.

The example environment uses PostgreSQL. Create the database first, then set its connection details in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=order_payment
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

To use MySQL instead, set `DB_CONNECTION=mysql`, port `3306`, and the appropriate database name, username, and password. Both drivers are supported by Laravel's database configuration. Run `php artisan migrate` after configuring the connection. Never commit `.env`, JWT secrets, or real gateway credentials.

## Standard API response format

All API endpoints use a consistent response envelope with `success`, `type`, `message`, `data`, `errors`, and `code`. The response helper lives in `app/Support/ApiResponse.php`; centralized exception rendering in `bootstrap/app.php` applies the same shape to validation, authentication, not-found, rate-limit, and unexpected server errors.

```json
{
    "success": true,
    "type": "success",
    "message": "Orders retrieved successfully.",
    "data": [],
    "errors": {},
    "code": null
}
```

Warnings use `type: "warning"` (for example, a declined simulated payment whose result was recorded). Errors use `success: false`, `type: "error"`, `data: null`, a stable machine-readable `code`, and field details in `errors` when applicable. Internal exception details are never returned to API clients. See [the API reference](docs/API.md) for examples.

## Authentication

All endpoints except registration and login require `Authorization: Bearer <token>`.

| Method | Endpoint             | Description    |
| ------ | -------------------- | -------------- |
| POST   | `/api/auth/register` | Register       |
| POST   | `/api/auth/login`    | Login          |
| GET    | `/api/auth/me`       | Current user   |
| POST   | `/api/auth/refresh`  | Refresh JWT    |
| POST   | `/api/auth/logout`   | Invalidate JWT |

Successful auth responses include `data.access_token`, `data.token_type`, and `data.expires_in`.

## Orders

| Method    | Endpoint                                 | Description                                             |
| --------- | ---------------------------------------- | ------------------------------------------------------- |
| GET       | `/api/orders?status=pending&per_page=15` | Paginated current user's orders; optional status filter |
| POST      | `/api/orders`                            | Create order and calculate total                        |
| GET       | `/api/orders/{id}`                       | Get order, items and payments                           |
| PUT/PATCH | `/api/orders/{id}`                       | Update order items                                      |
| DELETE    | `/api/orders/{id}`                       | Delete only if no payments exist                        |
| PATCH     | `/api/orders/{id}/status`                | Set pending, confirmed, or cancelled                    |

Create request:

```json
{
    "items": [
        { "product_name": "Keyboard", "quantity": 2, "price": "25.50" },
        { "product_name": "Mouse", "quantity": 1, "price": "10.00" }
    ]
}
```

The API calculates totals server-side and ignores any client-supplied total. New orders start pending; confirm an order through the status endpoint before processing its payment. Orders are scoped to their owner. Orders with payment attempts cannot be edited or deleted. An order with a pending or successful payment must remain confirmed.

## Payments

| Method | Endpoint                                      | Description                           |
| ------ | --------------------------------------------- | ------------------------------------- |
| POST   | `/api/orders/{id}/payments`                   | Process simulated payment             |
| GET    | `/api/orders/{id}/payments`                   | Paginated payments for an order       |
| GET    | `/api/payments?status=successful&per_page=15` | Paginated current user's payments     |
| GET    | `/api/payments/{id}`                          | One payment belonging to current user |

Request body: `{"method":"credit_card"}` or `{"method":"paypal"}`. Payments can only be processed for confirmed orders. The optional `simulate_failure: true` flag makes a gateway decline the simulated attempt; retry without the flag to simulate success. The gateways do not contact a real provider. Payment status is `pending`, `successful`, or `failed`; only one pending or successful payment is allowed per order, and a failed payment may be retried. If a gateway call throws or its outcome is unknown, the payment remains pending to prevent a duplicate attempt. This project does not implement payment reconciliation; that would be required when replacing the simulations with a real provider.

Typical errors: `401` for missing/invalid JWT or invalid credentials; `404` for absent resources or another user's resource; `409 ORDER_HAS_PAYMENTS` when deleting an order with payment attempts; `409 PAID_ORDER_STATUS_LOCKED` when trying to change an order with a pending or successful payment away from confirmed; `422` for validation failures or payment attempts against orders that are not confirmed.

## Add a gateway (Strategy Pattern)

1. Create a class under `app/Payments/Gateways` implementing `App\Payments\Contracts\PaymentGatewayInterface`.
2. Implement `charge(Payment $payment): array` returning `successful` (bool), `transaction_id` (string), and `message` (string).
3. Register a key and driver class in `config/payment.php`; read credentials via `env()`.
4. Add empty credential names to `.env.example`, then run `php artisan config:clear`.
5. Test both successful and failed gateway outcomes.

No controller or payment service changes are required to register a new configured gateway. Gateway credentials are not returned in API responses.

## API reference

See [`docs/API.md`](docs/API.md) for request/response examples, common validation errors, and HTTP status codes.

## Testing and Postman

Run `php artisan test` and `vendor/bin/pint`. Feature tests cover authentication, validation, order totals and ownership, payment business rules, and simulated gateway outcomes. The tests use SQLite in memory, so PHP must have `pdo_sqlite` enabled. Import `postman/order-payment.postman_collection.json` into Postman. Set `base_url` and run Register or Login; the collection stores the returned bearer token automatically.

## Assumptions

- Order creation defaults to `pending`; the client confirms it through the status endpoint before payment.
- Gateways are deterministic simulations, not production payment integrations; reconciliation and provider-specific idempotency are outside this task's scope.
- Successful payment does not automatically change the order status.
- Monetary fields use decimal storage; order and line totals are calculated server-side.
