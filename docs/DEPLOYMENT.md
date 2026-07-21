# Despliegue en produccion

Esta guia describe el despliegue de VitaNatural sobre un servidor Linux con
Nginx o Apache, PHP-FPM y MySQL. Adapta usuarios, rutas y comandos al proveedor
elegido. El directorio usado en los ejemplos es:

```text
/var/www/tienda-virtual-natural
```

## Estado funcional antes de publicar

El proyecto puede desplegarse como demo o entorno de staging. Actualmente:

- El checkout crea pedidos pendientes y reserva inventario.
- El scheduler vence reservas y devuelve el stock automaticamente.
- El acceso de clientes, Google OAuth, correo, carrito y checkout son reales.
- Culqi y la confirmacion real del pago corresponden al Sprint 7.
- El historial del cliente y la administracion completa de pedidos siguen en
  fases posteriores del Sprint 6.

No habilites ventas ni cobros reales hasta integrar y validar el proveedor de
pago, completar la identidad legal final y publicar terminos y privacidad
vigentes. Mientras tanto, conserva desactivada la opcion `Permitir ventas
reales` del panel legal.

La topologia productiva actual recomendada es un solo nodo de aplicacion con
disco persistente. Las imagenes se guardan expresamente en el disco `public`
local. Antes de escalar a varios nodos sera necesario usar almacenamiento
compartido o adaptar ese disco a un servicio de objetos, ademas de mantener
cache, sesiones y base de datos compartidos.

## Requisitos del servidor

- Linux de 64 bits actualizado.
- Nginx, Apache o un servicio administrado compatible con Laravel.
- PHP 8.3 o superior.
- Extensiones PHP requeridas por Laravel: Ctype, cURL, DOM, Fileinfo, Filter,
  Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer y XML.
- `pdo_mysql` para MySQL y GD con soporte WebP para los recortes de imagenes.
- Composer 2.
- MySQL 8 o una version compatible probada con el proyecto.
- Node.js `^20.19.0` o `>=22.12.0` y npm para compilar Vite.
- Cron para el scheduler.
- Supervisor, systemd o el gestor de procesos del proveedor para workers.
- HTTPS valido y renovacion automatica del certificado.

El servidor web debe apuntar exclusivamente a `public/`. Nunca expongas la
raiz del repositorio, `.env`, `storage/`, `vendor/` ni los archivos de Git.

## Datos que debes preparar

Antes del primer despliegue reune:

- Dominio y acceso a su DNS.
- Certificado HTTPS o acceso al gestor que lo emitira.
- Base de datos, usuario y contrasena exclusivos para la tienda.
- Credenciales SMTP de Brevo.
- Remitente o dominio verificado en Brevo.
- Cliente OAuth 2.0 Web de Google para produccion.
- Correo y contrasena iniciales del administrador.
- Identidad legal, RUC, domicilio fiscal y Libro de Reclamaciones cuando sean
  definitivos.
- Datos operativos: WhatsApp, horarios, direccion de recojo, tarifas y plazos.
- Destino externo y cifrado para copias de seguridad.

Los secretos minimos son `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD` y
`GOOGLE_CLIENT_SECRET`. Guardalos en el gestor de secretos del proveedor o en
un `.env` protegido. Nunca los publiques en Git, tickets, capturas o logs.

## Preparacion previa

Antes de desplegar una version ejecuta en CI o en un entorno de staging:

```bash
composer validate --no-check-publish
php artisan test
npm ci --include=dev
npm run build
```

Revisa tambien que no existan secretos versionados y que el repositorio use la
rama y el commit aprobados.

## Primer despliegue

### 1. Instalar el codigo

```bash
cd /var/www
git clone https://github.com/JoshuaMLN/tienda-virtual-natural.git
cd tienda-virtual-natural
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --include=dev
npm run build
```

El build necesita las dependencias de desarrollo de npm porque Vite esta en
`devDependencies`. Una vez generado `public/build`, `node_modules` no necesita
ser servido por el servidor web.

### 2. Crear el entorno

```bash
cp .env.example .env
php artisan key:generate
```

`APP_KEY` se genera una sola vez. Conservala en todas las actualizaciones y
copias restauradas. Regenerarla en una aplicacion activa invalida sesiones y
puede impedir descifrar datos existentes.

Usa esta base para el `.env` de produccion:

```dotenv
APP_NAME="Nombre de la tienda"
APP_ENV=production
APP_KEY=base64:VALOR_GENERADO
APP_DEBUG=false
APP_URL=https://tienda.example.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_PE
APP_TIMEZONE=America/Lima
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12
AUTH_REMEMBER_DAYS=30

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tienda_virtual_natural
DB_USERNAME=tienda_app
DB_PASSWORD="CONTRASENA_SEGURA"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME="LOGIN_SMTP_DE_BREVO"
MAIL_PASSWORD="CLAVE_SMTP_DE_BREVO"
MAIL_FROM_ADDRESS=no-reply@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_CLIENT_ID=CLIENTE_WEB_DE_PRODUCCION
GOOGLE_CLIENT_SECRET="SECRETO_GOOGLE_DE_PRODUCCION"
GOOGLE_REDIRECT_URI=https://tienda.example.com/auth/google/callback

VITE_APP_NAME="${APP_NAME}"
```

Reglas importantes:

- `APP_URL` debe usar el dominio HTTPS real y no debe terminar en `/`.
- `APP_DEBUG` siempre debe ser `false` en produccion.
- Si un secreto contiene `#`, espacios u otros caracteres especiales, encierralo
  entre comillas dobles.
- Conserva `SESSION_DOMAIN=null` para un solo host. Configuralo expresamente
  solo si necesitas compartir la sesion entre subdominios.
- En una instalacion con varias instancias cambia el mantenimiento a `database`
  y utiliza un cache compartido.
- No copies el `.env` local ni reutilices credenciales de desarrollo.

Protege el archivo:

```bash
chmod 600 .env
```

### 3. Configurar MySQL

Crea una base `utf8mb4` y un usuario con permisos solo sobre esa base. No uses
la cuenta `root` desde la aplicacion.

Despues de completar las variables `DB_*`:

```bash
php artisan migrate --force
php artisan db:seed --class=DeliveryDistrictSeeder --force
```

El segundo comando carga los 50 distritos canonicos de Lima y Callao. Es
idempotente y conserva las tarifas y estados ya administrados.

No ejecutes `php artisan migrate --seed` ni el `DatabaseSeeder` en produccion:
actualmente crean una cuenta de prueba y un catalogo demostrativo. Los productos,
categorias y marcas reales deben cargarse desde el panel administrativo.

### 4. Configurar almacenamiento y permisos

Laravel necesita escribir en `storage/` y `bootstrap/cache/`. Usa el usuario
real de PHP-FPM o del proveedor en lugar de `www-data` si es distinto.

```bash
sudo chown -R deploy:www-data /var/www/tienda-virtual-natural
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
php artisan storage:link
```

No uses permisos `777`.

El proyecto guarda avatares, categorias, marcas y productos en
`storage/app/public`, expuesto mediante `public/storage`. Los documentos fiscales
privados se guardaran en `storage/app/private` y nunca deben enlazarse al area
publica.

### 5. Optimizar Laravel

Ejecuta las optimizaciones solo despues de completar `.env`, migraciones y
build:

```bash
php artisan optimize
```

Si modificas variables de entorno y la aplicacion sigue leyendo valores
anteriores:

```bash
php artisan config:clear
php artisan optimize
```

No llames `env()` desde codigo de aplicacion fuera de `config/`; con la
configuracion cacheada esos accesos no leen `.env`.

### 6. Configurar el servidor web y HTTPS

- Configura el document root como
  `/var/www/tienda-virtual-natural/public`.
- Envia las rutas inexistentes a `public/index.php`.
- Bloquea archivos ocultos salvo `.well-known` para certificados.
- Activa HTTPS y redirige HTTP a HTTPS.
- Conserva correctamente el protocolo HTTPS si existe un proxy o balanceador.
- No muevas `index.php` a la raiz del proyecto.
- Deshabilita el listado de directorios.

Comprueba que `/up` devuelve HTTP 200 mediante HTTPS:

```bash
curl --fail --silent --show-error https://tienda.example.com/up
```

## Configuracion de Brevo SMTP

1. En Brevo, verifica el remitente o autentica el dominio de envio.
2. Configura SPF y DKIM en DNS; agrega DMARC cuando el dominio este listo.
3. En `SMTP & API`, crea una clave SMTP para produccion.
4. Usa el login SMTP como `MAIL_USERNAME`.
5. Usa la clave SMTP como `MAIL_PASSWORD`. No uses la API key ni la contrasena
   de la cuenta Brevo.
6. Usa `smtp-relay.brevo.com` y el puerto 587.

Con `MAIL_SCHEME=null`, Symfony Mailer negocia STARTTLS automaticamente en el
puerto 587 cuando OpenSSL esta disponible. No desactives la verificacion TLS.

`MAIL_FROM_ADDRESS` debe pertenecer al remitente o dominio verificado. Despues
de ejecutar `php artisan optimize`, valida manualmente:

- registro y verificacion de correo;
- reenvio del enlace de verificacion;
- recuperacion de contrasena del cliente;
- recuperacion de contrasena administrativa;
- recepcion, remitente, enlaces HTTPS y carpeta de spam.

## Configuracion de Google OAuth

Este proyecto usa un cliente OAuth 2.0 Web mediante Laravel Socialite. No usa
una API key publica.

1. Crea o selecciona un proyecto de Google Cloud para produccion.
2. Configura la pantalla de consentimiento OAuth.
3. Registra el dominio autorizado que realmente controlas.
4. Publica o configura correctamente el estado de la aplicacion y sus usuarios
   de prueba segun el tipo de cuenta.
5. Crea credenciales de tipo `Aplicacion web`.
6. Registra exactamente esta URI autorizada:

```text
https://tienda.example.com/auth/google/callback
```

7. Copia el Client ID y Client Secret en `GOOGLE_CLIENT_ID` y
   `GOOGLE_CLIENT_SECRET`.
8. Coloca la misma URI completa en `GOOGLE_REDIRECT_URI`.
9. Ejecuta `php artisan config:clear` y `php artisan optimize` despues de
   modificar las credenciales.

Google exige HTTPS en produccion y coincidencia exacta de protocolo, host, puerto
y ruta. No uses comodines ni la URI `127.0.0.1` de desarrollo. La pagina de
inicio, terminos y privacidad deben ser publicamente accesibles antes de pasar
la aplicacion OAuth a produccion.

Valida inicio de sesion, registro, vinculacion, desvinculacion y el caso de una
cuenta local existente con el mismo correo.

## Scheduler obligatorio

El scheduler es obligatorio para liberar en segundo plano el stock de pedidos
cuya reserva vencio. `routes/console.php` programa cada minuto:

```bash
php artisan orders:expire-pending
```

Configura una sola entrada cron en uno de los nodos de aplicacion:

```cron
* * * * * cd /var/www/tienda-virtual-natural && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

La tarea usa `withoutOverlapping(5)` y el cache de base de datos para evitar
solapamientos. Verifica:

```bash
php artisan schedule:list
php artisan orders:expire-pending --batch=100
```

En hosting compartido, crea la misma tarea desde la seccion `Cron Jobs`. En
Laravel Cloud, Forge u otro proveedor administrado, registra `schedule:run` como
tarea programada cada minuto.

Sin cron, el checkout puede reconciliar una reserva cuando el cliente vuelve,
pero el stock no se libera proactivamente. Esa proteccion no reemplaza el
scheduler.

## Worker de colas

El proyecto usa la conexion `database`. Las notificaciones actuales todavia se
envian de forma sincrona, por lo que el worker no bloquea esta version; debe
quedar preparado para los correos y trabajos asincronos de las siguientes fases.
Sera obligatorio cuando un trabajo o notificacion implemente `ShouldQueue`.

Ejemplo de Supervisor:

```ini
[program:vitanatural-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/var/www/tienda-virtual-natural
command=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/tienda-virtual-natural/storage/logs/worker.log
stopwaitsecs=3600
```

Activa la configuracion segun la distribucion y verifica que Supervisor muestre
el proceso como `RUNNING`. Tras cada despliegue ejecuta `php artisan reload` o
reinicia el worker con el gestor del proveedor para que cargue el codigo nuevo.

Comandos de diagnostico:

```bash
php artisan queue:failed
php artisan queue:retry all
```

No reintentes masivamente trabajos fallidos sin revisar primero su causa y su
idempotencia.

## Configuracion inicial de la tienda

Crea el primer administrador desde la terminal del servidor:

```bash
php artisan admin:create
```

Luego completa en el panel:

1. `Configuracion`: WhatsApp, correo, telefono, horarios, reserva de stock,
   envio gratis, plazos y direccion de recojo.
2. `Configuracion`: revisa los 50 distritos, tarifas, cobertura y dias estimados.
3. `Legal`: nombre comercial, proveedor o razon social, RUC, domicilio fiscal y
   Libro de Reclamaciones.
4. `Legal`: genera, revisa y publica terminos y politica de privacidad vigentes.
5. Calendario: registra feriados y fechas sin atencion.
6. Catalogo: crea categorias, marcas, productos, imagenes, precios, impuestos y
   stock reales.

No habilites ventas reales mientras falten los datos legales o la integracion
de pago. Nunca crees administradores mediante SQL manual ni reutilices la cuenta
de un cliente.

## Comprobacion posterior al primer despliegue

Ejecuta:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan route:list --path=admin -v
```

No publiques la salida de diagnostico si pudiera contener datos internos.

Pruebas manuales minimas:

1. `/up` responde 200 por HTTPS.
2. Home, catalogo, detalle y carrito cargan recursos sin errores 404.
3. Las imagenes subidas persisten y se sirven desde `/storage`.
4. Admin no es accesible sin autenticacion y el administrador real puede entrar.
5. Registro, verificacion y recuperacion envian correos con enlaces HTTPS.
6. Google permite iniciar sesion y volver al callback del dominio real.
7. Un cliente verificado puede completar checkout y crear un unico pedido.
8. Cancelar el pedido devuelve el stock exactamente una vez.
9. El scheduler vence una reserva expirada y libera el stock.
10. Logs, cron y worker no muestran excepciones.

## Seguridad operativa

- Expone al exterior solo HTTPS y los puertos estrictamente necesarios.
- Restringe SSH por llave y, cuando sea posible, por IP o VPN.
- No expongas MySQL a Internet; permite conexiones solo desde la aplicacion y
  desde canales administrativos protegidos.
- Usa usuarios distintos para despliegue, servidor web y base de datos, con el
  menor privilegio posible.
- Mantiene `.env` fuera de Git, con permisos restringidos y fuera del document
  root.
- Conserva `APP_DEBUG=false`, cookies seguras y HTTPS en todo el recorrido.
- Rota claves SMTP, Google y base de datos si se filtran o cambia el responsable.
- Elimina inmediatamente cuentas administrativas que ya no correspondan.
- Protege staging con acceso restringido y credenciales distintas de produccion.
- Mantiene sistema operativo, PHP, Composer y dependencias con parches vigentes.

## Despliegue de actualizaciones

Antes de cada despliegue toma una copia de seguridad y revisa las migraciones.
Un flujo basico es:

```bash
cd /var/www/tienda-virtual-natural
php artisan down --refresh=15
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --include=dev
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan reload
php artisan up
```

Si el proveedor genera un artefacto en CI, compila frontend y dependencias en CI
y publica el artefacto en lugar de ejecutar Node en el servidor. No ejecutes
`composer update` ni `npm update` durante el despliegue: deben respetarse
`composer.lock` y `package-lock.json`.

## Copias de seguridad

Respalda como minimo:

- base MySQL;
- `storage/app/public` por imagenes subidas;
- `storage/app/private` por futuros comprobantes privados;
- `APP_KEY` y secretos en un gestor seguro separado;
- configuracion del servidor web, cron y Supervisor.

Recomendacion inicial:

- copia diaria de base de datos y archivos;
- copia adicional antes de migraciones;
- cifrado en reposo y durante transferencia;
- almacenamiento fuera del servidor principal;
- retencion diaria, semanal y mensual acorde al negocio;
- prueba periodica de restauracion.

Una copia que nunca se ha restaurado no debe considerarse verificada.

## Monitoreo y mantenimiento

- Monitoriza `https://tienda.example.com/up`.
- Revisa `storage/logs/laravel.log`, logs de PHP-FPM, Nginx, cron y Supervisor.
- Controla espacio en disco, base de datos, certificados y fecha de backups.
- Revisa periodicamente `php artisan queue:failed`.
- Configura alertas para HTTP 5xx, scheduler detenido y worker caido.
- Aplica actualizaciones de seguridad del sistema, PHP y dependencias mediante un
  flujo probado en staging.
- No edites archivos directamente en produccion; todo cambio debe venir de Git o
  del artefacto de despliegue.

## Recuperacion y rollback

Ante un error grave:

1. Activa mantenimiento con `php artisan down`.
2. Conserva logs y toma una copia de la base afectada.
3. Revierte el codigo al commit o artefacto anterior.
4. Reinstala dependencias y recompila assets desde los archivos lock.
5. Ejecuta `php artisan optimize` y `php artisan reload`.
6. Restaura la base y archivos solo cuando una migracion o escritura incompatible
   lo haga necesario.
7. Ejecuta las comprobaciones posteriores antes de `php artisan up`.

Evita ejecutar `migrate:rollback` automaticamente en produccion: una migracion
puede haber transformado o eliminado datos. Prefiere una migracion correctiva o
una restauracion verificada.

## Referencias oficiales

- [Despliegue de Laravel 13](https://laravel.com/docs/13.x/deployment)
- [Almacenamiento de Laravel 13](https://laravel.com/docs/13.x/filesystem)
- [Correo en Laravel 13](https://laravel.com/docs/13.x/mail)
- [SMTP transaccional de Brevo](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP)
- [OAuth 2.0 para aplicaciones web de Google](https://developers.google.com/identity/protocols/oauth2/web-server)
