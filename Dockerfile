# Usa una imagen oficial de PHP con FPM
FROM php:8.2-fpm

# Instalar dependencias del sistema y extensiones de PHP requeridas por Laravel
# default-mysql-client provee mysqldump, requerido por spatie/laravel-backup.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    default-mysql-client

# Limpiar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Límites de subida (comprobantes/capturas). Sobrescribe los defaults de PHP.
COPY ./docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

# Obtener Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www

# Copiar archivos del proyecto
COPY . /var/www

# Instalar dependencias de composer SOLO de producción (sin dev: fuera Telescope,
# Pint, Sail, etc. que no deben correr ni exponerse en producción).
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Dar permisos a las carpetas de almacenamiento
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Copiar configuración de Nginx. En Debian, 'apt install nginx' deja activo un
# sitio por defecto (sites-enabled/default) marcado default_server que serviría
# la página "Welcome to nginx!" en lugar de la app: hay que quitarlo.
COPY ./docker/nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default
# Copiar configuración de Supervisor
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint de arranque (storage:link, migraciones, caches) antes de supervisord.
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Exponer el puerto 80
EXPOSE 80

# El entrypoint prepara el estado y luego hace exec de supervisord.
CMD ["/usr/local/bin/entrypoint.sh"]