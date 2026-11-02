# Inventory and POS Backend (Laravel + MySQL)

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env (MySQL), then:
php artisan migrate --seed
php artisan serve
```
API base URL: `http://127.0.0.1:8000/api`

## Test logins (password: password123)
- admin@example.com (admin)
- manager@example.com (manager)
- sales@example.com (sales)

## Postman
Import `docs/postman_collection.json`. Run **Login** first; the token is saved automatically.

## Response format
Success: `{"success": true, "message": "...", "data": ...}`
Error: `{"success": false, "message": "...", "errors": {...}}`

## Modules
Auth and roles, catalog (categories, brands, units, products, variants, tax rates, images),
suppliers, customers and addresses, stores, stock locations, POS registers, payment methods,
purchasing (orders, approval, goods receipts, supplier payments), inventory ledger and balances,
stock adjustments and transfers, cashier shifts, POS checkout, credit payments and statements,
returns and refunds, online orders with reservations and shipments.

## Design notes
- Enums are stored as string columns and enforced by PHP enum classes.
- `sessions`, `cache`, `jobs`, `password_reset_tokens` are Laravel framework tables.
- Every stock change goes through `InventoryService`, which writes the ledger and updates balances in one DB transaction.
- Money is decimal(14,2); quantities are decimal(14,3).
- Roles: admin and manager can approve, post, refund, pay suppliers and close shifts; sales can sell.