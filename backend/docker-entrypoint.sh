#!/bin/sh
set -e

mkdir -p /data
if [ ! -f /data/database.sqlite ]; then
    touch /data/database.sqlite
fi
chmod 666 /data/database.sqlite || true
chmod 777 /data || true

php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000
