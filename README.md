# VitaNatural

Ecommerce de productos naturales desarrollado con Laravel. Incluye catalogo
publico, administracion de productos e inventario, carrito de compras persistente
y una cuenta real para clientes.

Los Sprints 0 al 5 estan completados. El Sprint 6 esta en desarrollo y ya
incluye seguridad administrativa, configuracion de entrega, checkout real y
creacion de pedidos pendientes con reserva de inventario.

## Funcionalidades implementadas

### Tienda publica

- Catalogo con categorias, marcas, ofertas, busqueda y filtros combinables.
- Detalle de producto con imagen principal, galeria y control de disponibilidad.
- Carrito para invitados con validacion de stock y sincronizacion de precios.
- Carrito persistente por cliente y fusion segura al iniciar sesion o registrarse.
- Ajustes informados cuando cambia el precio, stock o visibilidad de un producto.
- Navegacion y footer adaptados al estado invitado o autenticado.

### Administracion

- Acceso administrativo separado y protegido por rol.
- Administracion de categorias, marcas, productos e imagenes.
- Inventario, movimientos de stock, alertas y dashboard administrativo.
- Configuracion de umbrales de stock por producto y visibilidad publica.
- Configuracion operativa, legal, de recojo y tarifas para Lima y Callao.

### Clientes y cuenta

- Registro e inicio de sesion de clientes.
- Verificacion de correo y recuperacion de contrasena.
- Inicio de sesion y vinculacion segura con Google.
- Perfil con nombre, telefono, correo y avatar recortable.
- Cambio de contrasena y gestion de metodos de acceso.
- Direcciones guardadas para Lima Metropolitana y Callao con UBIGEO canonico.
- Menu de cuenta responsive y cierre de sesion con confirmacion.
- Checkout protegido para clientes autenticados con correo verificado.
- Checkout por etapas con contacto, entrega, comprobante y terminos versionados.
- Revalidacion de precio, stock, cobertura y tarifa antes de confirmar.
- Pedidos idempotentes con reserva temporal, cancelacion y vencimiento automatico.

## Alcance actual

El checkout ya crea pedidos pendientes y reserva inventario, pero todavia no
realiza cobros. El historial del cliente, la gestion administrativa de pedidos
y comprobantes se completaran en las siguientes fases del Sprint 6. Los pagos
con Culqi corresponden al Sprint 7; cupones y promociones quedan para sprints
posteriores.

## Tecnologias

- PHP 8.3 o superior.
- Laravel 13.8 o superior dentro de la rama 13.x.
- MySQL.
- Blade, JavaScript, Bootstrap 5.3 y Vite.
- Bootstrap Icons instalado localmente.
- Laravel Socialite para Google OAuth.
- PHPUnit para pruebas automatizadas.

## Requisitos

- PHP 8.3+ con las extensiones requeridas por Laravel.
- Composer.
- Node.js y npm.
- MySQL.

## Instalacion local

```bash
git clone https://github.com/JoshuaMLN/tienda-virtual-natural.git
cd tienda-virtual-natural
composer install
npm install
```

Crea el archivo de entorno y genera la clave de la aplicacion:

```bash
cp .env.example .env
php artisan key:generate
```

En PowerShell puedes reemplazar el primer comando por:

```powershell
Copy-Item .env.example .env
```

Configura la conexion MySQL en `.env` y prepara la aplicacion:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

Inicia el entorno local:

```bash
composer run dev
```

Este comando inicia el servidor, la cola, el scheduler, los logs y Vite. La
aplicacion estara disponible normalmente en `http://127.0.0.1:8000`.

## Scheduler

El vencimiento de pedidos pendientes depende del scheduler de Laravel. Para
ejecutarlo de forma aislada durante desarrollo:

```bash
php artisan schedule:work
```

Tambien puedes procesar manualmente un lote para diagnostico:

```bash
php artisan orders:expire-pending --batch=100
```

En produccion configura una unica entrada cron que ejecute el scheduler cada
minuto desde la raiz del proyecto:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

En despliegues con varios servidores, el comando debe ejecutarse desde un solo
nodo de scheduler. La expiracion tambien se reconcilia al entrar al checkout o
abrir un pedido vencido, pero esa proteccion no reemplaza el cron.

La configuracion completa de entorno, Brevo, Google OAuth, servidor web, colas,
seguridad y backups esta en [Despliegue en produccion](docs/DEPLOYMENT.md).

## Configuracion de servicios

Para probar correos reales, configura las variables `MAIL_*` de `.env` con un
proveedor SMTP. Sin credenciales, Laravel puede registrar los mensajes usando el
mailer `log`. El correo se utiliza para verificacion de cuenta y recuperacion de
contrasena.

El acceso con Google requiere configurar:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

La URI de redireccion debe coincidir exactamente con la registrada en Google
Cloud para el cliente OAuth utilizado en el entorno local. Produccion debe usar
credenciales y una URI independientes.

La duracion de la opcion "Recordarme" se configura en dias:

```dotenv
AUTH_REMEMBER_DAYS=30
```

Nunca publiques `.env`, credenciales, claves privadas ni secretos OAuth. El
archivo `.env.example` documenta solamente nombres y valores de ejemplo seguros.

## Pruebas

```bash
composer test
npm run build
```

Validaciones adicionales:

```bash
node --check public/js/app.js
php artisan view:cache
php vendor/bin/pint --test
```

La cantidad vigente de pruebas y aserciones se registra en el estado del Sprint
6 despues de cada fase.

## Documentacion

- [Roadmap general](docs/SPRINTS.md)
- [Roadmap del Sprint 6](docs/SPRINT_6_ROADMAP.md)
- [Estado del Sprint 6](docs/SPRINT_6_STATUS.md)
- [Despliegue en produccion](docs/DEPLOYMENT.md)

Los roadmaps y estados de los sprints anteriores tambien se encuentran en
[`docs/`](docs/).
