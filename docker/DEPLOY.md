# Despliegue en Dokploy — Backend DipleBill

Guía operativa para el servicio **Backend** (contenedor del `Dockerfile`). La app
**Database** (MySQL) corre en su propio servicio/contenedor.

El contenedor ya hace, en el arranque (`docker/entrypoint.sh`):
`storage:link` → `tenants:migrate` (central + tenants dedicados) → `config/route/view:cache` → `supervisord`.
Y `supervisord` levanta: **nginx**, **php-fpm** y **scheduler** (`schedule:work`, para backups y avisos).

---

## 1. Volumen persistente (OBLIGATORIO)

Sin esto, cada redeploy **borra comprobantes de pago, capturas de reportes y backups locales**.

En Dokploy → servicio Backend → **Volumes / Mounts**, montar un volumen en:

```
/var/www/storage
```

> El entrypoint recrea `storage/framework/*` si el volumen viene vacío, así que
> montar todo `storage` es seguro. Si prefieres granular, monta al menos
> `/var/www/storage/app`.

## 2. Variables de entorno (Environment)

Mínimas de producción:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # generar una vez: php artisan key:generate --show
APP_URL=https://api.tudominio.com
APP_TIMEZONE=America/Managua

DB_CONNECTION=mysql
DB_HOST=<host del servicio Database en Dokploy>
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

# mysqldump vive en el contenedor (default-mysql-client). Dejar VACÍO.
DB_DUMP_BINARY_PATH=

QUEUE_CONNECTION=sync         # los correos del sistema van con sendNow; sin worker
SESSION_DRIVER=database
CACHE_STORE=database

# SMTP real (o configúralo desde el panel /admin/email-settings)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@tudominio.com
MAIL_FROM_NAME=DipleBill

# Backups off-site (S3 / Backblaze B2 / DO Spaces)
BACKUP_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=...
AWS_DEFAULT_REGION=...
# B2/Spaces: además
# AWS_ENDPOINT=https://s3.us-west-000.backblazeb2.com
# AWS_USE_PATH_STYLE_ENDPOINT=true

SENTRY_LARAVEL_DSN=...        # opcional, monitoreo
TELESCOPE_ENABLED=false
```

## 3. CORS — no requiere acción

El proyecto no publica `config/cors.php`, así que usa el default de Laravel:
`allowed_origins => ['*']` en las rutas `api/*` con `supports_credentials => false`.
Como las apps se autentican con **token Bearer** (no cookies), `*` funciona para
cualquier dominio y ninguna llamada del navegador falla por CORS — da igual que la
web y la app estén en dominios distintos. **No hay que tocar nada.**

> Buena práctica (opcional, a futuro): publicar `config/cors.php`
> (`php artisan config:publish cors`) y listar los dominios exactos en
> `allowed_origins`. No es necesario para el lanzamiento.

## 4. Deploy

Dokploy reconstruye la imagen desde el `Dockerfile` en cada deploy. Al reconstruir:
las migraciones (incl. tenants) corren solas en el entrypoint. No hace falta paso manual.

## 5. Verificación post-deploy

**Todo se corre en el contenedor Backend** (Dokploy → servicio Backend → Terminal),
NO en el de MySQL. `mysqldump` es el *cliente* instalado en la imagen del Backend;
se conecta por red al servicio MySQL usando `DB_HOST`. Como la BD está en otro
servicio/proyecto, `DB_HOST` debe ser el hostname interno de ese servicio en la red
de Dokploy (o el host:puerto expuesto), y ambos deben compartir red.

```sh
which mysqldump && mysqldump --version     # cliente MySQL presente en el Backend
php artisan tinker --execute="DB::select('select 1');"   # el Backend alcanza la BD
php artisan schedule:list                  # tareas agendadas visibles
ls -la storage/app/public                  # storage:link OK
php artisan backup:run --only-db           # dumpea la BD remota vía mysqldump...
php artisan backup:list                    # ...y aparece en el disco s3
```

> Si `backup:run` falla con "connection refused" o "unknown host", es que el
> Backend no alcanza el MySQL: revisa `DB_HOST`/red compartida entre servicios.

## 6. Prueba de RESTORE (no basta con generar el backup)

Al menos una vez antes del lanzamiento:

1. Descargar el `.zip` más reciente del bucket.
2. Descomprimir → contiene el `.sql` de la base.
3. Restaurar en una BD de prueba: `mysql -u user -p test_db < db-dumps/mysql-*.sql`
4. Verificar que las tablas y datos están completos.

> Un backup que nunca se probó restaurando **no es un backup**.
