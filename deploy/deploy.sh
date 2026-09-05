#!/usr/bin/env bash

set -Eeuo pipefail

ISLAND_THRIFT_DIR="${ISLAND_THRIFT_DIR:-/var/www/islandthrift}"

if [[ ! -f "${ISLAND_THRIFT_DIR}/artisan" ]]; then
    echo "Laravel application not found at ${ISLAND_THRIFT_DIR}."
    echo "Set ISLAND_THRIFT_DIR to the deployed application directory and run again."
    exit 1
fi

cd "${ISLAND_THRIFT_DIR}"

if [[ ! -f .env ]]; then
    echo "Missing ${ISLAND_THRIFT_DIR}/.env. Copy .env.production.example and enter the production values first."
    exit 1
fi

if grep -Eq 'YOUR_DOMAIN|YOUR_DATABASE_NAME|DB_PASSWORD=password|APP_KEY=GENERATE_ON_THE_SERVER' .env; then
    echo "Production placeholders remain in .env. Set the domain, database name, database password, and APP_KEY first."
    exit 1
fi

git fetch origin main
git checkout main
git pull --ff-only origin main

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan config:clear

php artisan down --retry=60
trap 'php artisan up' EXIT

php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan queue:restart

php artisan up
trap - EXIT
