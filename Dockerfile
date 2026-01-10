# Usa una imagen oficial de PHP con FPM
FROM php:8.2-fpm

# Instalar dependencias del sistema y extensiones de PHP requeridas por Laravel
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor

# Limpiar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Obtener Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www

# Copiar archivos del proyecto
COPY . /var/www

# Instalar dependencias de composer (optimizadas para prod)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Dar permisos a las carpetas de almacenamiento
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Copiar configuración de Nginx (Crearemos este archivo en el Paso 1.1)
COPY ./docker/nginx/conf.d/app.conf /etc/nginx/conf.d/default.conf
# Copiar configuración de Supervisor (Crearemos este archivo en el Paso 1.2)
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Exponer el puerto 80
EXPOSE 80

# Comando de inicio (Inicia Supervisor que gestiona Nginx y PHP)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]