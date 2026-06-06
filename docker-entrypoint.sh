#!/bin/sh
set -e

# ============================================================
# Docker Entrypoint — Sekuritas Demo API
# ============================================================

echo "Starting Sekuritas Demo API..."

cd /var/www/html

# Railway menyuntikkan $PORT — pastikan nginx mendengarkan di port tsb.
# Jika $PORT tidak ada, tetap pakai 80 (sesuai EXPOSE di Dockerfile).
if [ -n "$PORT" ]; then
    echo "Configuring nginx to listen on port $PORT..."
    sed -i "s/listen 80;/listen ${PORT};/" /etc/nginx/nginx.conf
fi

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

# Jalankan migrasi database — tunggu DB siap dulu (maks ~30 detik).
# NON-FATAL: kalau DB tidak terjangkau, web server tetap start agar /api/health hidup.
echo "Running database migrations..."
DB_READY=0
i=1
while [ "$i" -le 15 ]; do
    if php artisan migrate --force 2>/dev/null; then
        DB_READY=1
        echo "Migrations applied."
        break
    fi
    echo "Database not ready (attempt $i/15), retrying in 2s..."
    sleep 2
    i=$((i + 1))
done

if [ "$DB_READY" = "1" ]; then
    # Seed database jika belum ada data (cek tabel users & mutual_funds).
    # Ambil hanya angka terakhir dari output tinker agar tahan terhadap
    # banner/warning/deprecation yang kadang ikut tercetak (penyebab cek lama gagal).
    USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | grep -oE '[0-9]+' | tail -n1)
    FUND_COUNT=$(php artisan tinker --execute="echo \App\Models\MutualFund::count();" 2>/dev/null | grep -oE '[0-9]+' | tail -n1)
    USER_COUNT=${USER_COUNT:-0}
    FUND_COUNT=${FUND_COUNT:-0}
    echo "Data saat ini: users=$USER_COUNT, mutual_funds=$FUND_COUNT"
    if [ "$USER_COUNT" = "0" ] || [ "$FUND_COUNT" = "0" ]; then
        echo "Seeding database..."
        php artisan db:seed --force || echo "WARNING: seeding gagal, lanjut."
    else
        echo "Database sudah berisi data — lewati seeding."
    fi
else
    echo "WARNING: database tidak terjangkau — web server tetap start."
    echo "         Set env DB_* di Railway + tambahkan service MySQL, lalu redeploy."
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
