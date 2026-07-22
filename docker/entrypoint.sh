#!/bin/sh
# Arranque del contenedor de producción (Dokploy).
# Se ejecuta antes de supervisord: prepara el estado que un contenedor efímero
# no trae por sí solo. Cualquier paso opcional no debe tumbar el arranque.
set -e

cd /var/www

echo "[entrypoint] Preparando almacenamiento..."
# Asegurar la estructura de storage cuando se monta un volumen vacío.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Enlace público (logos, imágenes de productos, assets); idempotente.
php artisan storage:link || true

echo "[entrypoint] Corriendo migraciones (central + tenants dedicados)..."
# tenants:migrate ya pasa --force internamente. No debe abortar el arranque si
# la BD tarda unos segundos en aceptar conexiones.
php artisan tenants:migrate || echo "[entrypoint] AVISO: tenants:migrate falló; revisa la BD."

echo "[entrypoint] Cacheando configuración..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "[entrypoint] Listo. Iniciando supervisord."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
