#!/bin/bash
# Prepara permisos y aplica migraciones y catálogos antes de publicar la nueva versión.
set -euo pipefail

cd /var/app/staging

mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R webapp:webapp storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

artisan() {
  runuser -u webapp -- php artisan "$@"
}

artisan migrate --force
artisan db:seed --class=ProductionSeeder --force
