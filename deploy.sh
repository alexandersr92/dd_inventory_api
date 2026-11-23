#!/bin/bash
# Script de Despliegue para Laravel en Producción
# Recuerda: Este script NUNCA borra datos de tus clientes (no usa migrate:fresh ni seeders destructivos).
#
# Opción documentada:
# Pasar el flag --publish-release-notes o definir la variable de entorno PUBLISH_RELEASE_NOTES=true
# para ejecutar ÚNICAMENTE ProductReleasesSeeder después de las migraciones, una vez que los frontends
# (admin, pos, app) estén disponibles y desplegados para los usuarios.

set -e

PUBLISH_RELEASE_NOTES="${PUBLISH_RELEASE_NOTES:-false}"
for arg in "$@"; do
    case "$arg" in
        --publish-release-notes)
            PUBLISH_RELEASE_NOTES=true
            ;;
    esac
done

echo "🚀 Iniciando proceso de despliegue en producción..."

# 1. Entrar en modo de mantenimiento para evitar inconsistencias con clientes activos
echo "🛑 Activando modo de mantenimiento..."
php artisan down || true

# 2. Actualizar dependencias de Composer sin dependencias de desarrollo
echo "📦 Instalando dependencias de Composer (producción)..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 3. Ejecutar migraciones de base de datos central y tenants
echo "🗄️ Ejecutando migraciones de base de datos (tenants:migrate)..."
php artisan tenants:migrate

# Opcional: sembrar únicamente notas de versión si se solicitó explícitamente tras frontends disponibles
if [ "$PUBLISH_RELEASE_NOTES" = "true" ]; then
    echo "📢 Publicando notas de versión (ProductReleasesSeeder)..."
    php artisan db:seed --class=ProductReleasesSeeder --force
fi

# 4. Limpiar y reconstruir la caché de Laravel (configuraciones, rutas y optimizaciones)
echo "⚡ Optimizando y reconstruyendo caché..."
php artisan optimize
php artisan view:cache

# 5. Reiniciar los servicios en segundo plano (Workers / Reverb) si se están usando
echo "🔄 Reiniciando workers de colas..."
php artisan queue:restart || true

# 6. Salir del modo de mantenimiento para que los clientes vuelvan a ingresar
echo "🟢 Desactivando modo de mantenimiento (App en línea)..."
php artisan up

echo "✅ ¡Despliegue finalizado con éxito!"
