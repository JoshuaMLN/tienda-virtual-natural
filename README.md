# VitaNatural

Ecommerce de productos naturales desarrollado con Laravel. El proyecto incluye
catalogo publico, administracion de productos e inventario, carrito de compras y
autenticacion de clientes.

El desarrollo se organiza por sprints. Actualmente se encuentra en el Sprint 5,
enfocado en autenticacion, clientes y cuenta.

## Funcionalidades implementadas

- Catalogo con categorias, marcas, ofertas, busqueda y filtros combinables.
- Detalle de producto con imagen principal, galeria y control de disponibilidad.
- Carrito por sesion con validacion de stock y sincronizacion de precios.
- Administracion de categorias, marcas, productos e imagenes.
- Inventario, movimientos de stock, alertas y dashboard administrativo.
- Registro e inicio de sesion de clientes.
- Verificacion de correo y recuperacion de contrasena.
- Inicio de sesion y vinculacion segura con Google.

## Tecnologias

- PHP 8.3 o superior.
- Laravel 13.
- MySQL.
- Blade, JavaScript y Vite.
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

La aplicacion estara disponible normalmente en `http://127.0.0.1:8000`.

## Servicios opcionales

Para probar correos reales, configura las variables `MAIL_*` de `.env` con un
proveedor SMTP. Sin credenciales, Laravel puede registrar los mensajes usando el
mailer `log`.

El acceso con Google requiere configurar:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

Nunca publiques `.env`, credenciales, claves privadas ni secretos OAuth. El
archivo `.env.example` documenta solamente nombres y valores de ejemplo seguros.

## Pruebas

```bash
composer test
npm run build
```

Para aplicar el formato del proyecto:

```bash
php vendor/bin/pint
```

## Documentacion

- [Roadmap general](docs/SPRINTS.md)
- [Roadmap del Sprint 5](docs/SPRINT_5_ROADMAP.md)
- [Estado del Sprint 5](docs/SPRINT_5_STATUS.md)

Los roadmaps y estados de los sprints anteriores tambien se encuentran en
[`docs/`](docs/).
