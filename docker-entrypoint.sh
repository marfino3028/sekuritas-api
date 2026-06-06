#!/bin/sh
set -e

# ============================================================
# Docker Entrypoint — Sekuritas Demo API
# ============================================================

echo "Starting Sekuritas Demo API..."

cd /var/www/html

# Copy .env jika belum ada
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate app key jika belum ada
if [ -z "$(grep '^APP_KEY=base64:' .env)" ]; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# Generate JWT secret jika belum ada
if [ -z "$(grep '^JWT_SECRET=' .env | grep -v 'JWT_SECRET=$')" ]; then
    echo "Generating JWT_SECRET..."
    php artisan jwt:secret --force
fi

# Jalankan migrasi database
echo "Running database migrations..."
php artisan migrate --force

# Seed database jika tabel users kosong
USER_COUNT=$(php artisan tinker --execute="echo App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "Seeding database..."
    php artisan db:seed --force
fi

# Buat symlink storage
php artisan storage:link --force 2>/dev/null || true

# Optimize untuk production
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Application ready!"

# Jalankan command utama (supervisord)
exec "$@"
