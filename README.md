# Island Thrift

Island Thrift is a Laravel-based ecommerce, point-of-sale, and inventory management application. The public storefront, guest checkout, Filament admin, purchasing, sales, stock, customer and supplier ledgers, cashier shifts, receipts, and reporting all use the same database.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- PHP SQLite extension for the default local setup

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/island_thrift.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

The default local database is `database/island_thrift.sqlite`. To use MySQL or PostgreSQL instead, replace the `DB_*` values in `.env` and run `php artisan migrate --seed` against the new database.

The demo seeder creates these local accounts:

- Administrator: `admin@islandthrift.local`
- Cashier: `cashier@islandthrift.local`
- Password for both: `password`

Change or remove the demo credentials before deploying the application.

After starting Laravel, open the storefront at `http://127.0.0.1:8000` and the administration panel at `http://127.0.0.1:8000/admin`.

## Storefront management

- Use **Products** in the admin panel to set the online sale price, visibility, featured status, short description, and product images.
- Use **Categories** to upload category images.
- Use **Storefront Settings** to select the online branch and warehouse and manage WhatsApp, social links, delivery methods, and payment methods.
- Website checkouts appear in **Sales** with the `Website` channel. Updating a website order to paid records its customer payment; cancelling an unpaid website order restores inventory.
- Checkout currently supports configurable offline payment methods. No online card gateway is bundled.

## Development

Run the Laravel server, queue worker, log viewer, and Vite development server together:

```bash
composer run dev
```

## Quality checks

```bash
composer test
npm run test:frontend
npm run build
vendor/bin/pint --test
```

## Product import

```bash
php artisan island-thrift:import-products COMPANY_UUID products.csv
```

## License

This project is proprietary unless a separate license is provided by the repository owner.
