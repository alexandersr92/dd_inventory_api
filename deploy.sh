#!/bin/bash
# Script de Despliegue para Laravel en Producción
# Recuerda: Este script NUNCA borra datos de tus clientes (no usa migrate:fresh ni seeders destructivos).

echo "🚀 Iniciando proceso de despliegue en producción..."

# 1. Entrar en modo de mantenimiento para evitar inconsistencias con clientes activos
echo "🛑 Activando modo de mantenimiento..."
php artisan down || true

# 2. Actualizar dependencias de Composer sin dependencias de desarrollo
echo "📦 Instalando dependencias de Composer (producción)..."
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 3. Ejecutar migraciones de base de datos (seguro para datos existentes)
# --force es obligatorio en producción. Ejecuta solo las migraciones nuevas.
echo "🗄️ Ejecutando migraciones de base de datos..."
php artisan migrate --force

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
