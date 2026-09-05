# Island Thrift

Island Thrift is a Laravel-based retail point-of-sale and inventory management application. It includes multi-company and multi-branch access, product and inventory management, purchasing, sales, customer and supplier ledgers, cashier shifts, receipt printing, reporting, and English/Dhivehi localization.

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
