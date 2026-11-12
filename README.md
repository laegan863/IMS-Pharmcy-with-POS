# Pharmacy IMS with POS

Inventory management and point of sale for a Philippine drugstore: batch and
expiry tracking, first-expiry-first-out dispensing, senior citizen and PWD
discounts with VAT exemption, prescriptions and the dangerous drug register,
and BIR-style invoices, X and Z readings.

Laravel 13, Inertia 3, React 19 + TypeScript, Tailwind 4 / shadcn/ui, MySQL 8.4.

## How stock works

Every stock change — receipt, sale, void, refund, adjustment, count,
transfer, disposal — goes through `App\Services\Stock\StockService`, which
writes an immutable movement per batch and branch and updates a locked
balance row in the same transaction. On-hand stock is always the sum of the
movements; `php artisan stock:verify-ledger` checks this nightly. The
database refuses negative balances.

Prices, VAT and discounts are computed only on the server
(`App\Services\Sales\PriceCalculator`); the POS screen displays what it
returns. A senior citizen buying a ₱100.00 VATable medicine pays ₱71.43
(100 ÷ 1.12 = 89.29, less 20% = 17.86).

## Run it locally

```
composer install
npm install
cp .env.example .env   # set REQUIRE_TWO_FACTOR=false for local work
php artisan key:generate
php artisan migrate --seed
composer run dev
```

Demo users (password `password`): `owner`, `pharmacist`, `cashier`, `clerk`.
The local seed also loads a demo catalog with stock.

## Tests

```
php artisan test                      # SQLite
DB_CONNECTION=mysql DB_DATABASE=pharmacy_ims_test php artisan test   # also runs the two-terminal race test
composer run ci:check                 # lint, static analysis, types and tests
```

## Documentation

- `docs/operations/go-live-checklist.md` — server, backups, store setup, acceptance tests, parallel run
- `docs/operations/backup-and-restore.md` — backups and the restore drill
- `docs/guides/` — one-page guides for cashier, pharmacist, inventory clerk and owner
