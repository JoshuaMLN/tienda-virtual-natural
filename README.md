# VitaNatural

Ecommerce de productos naturales desarrollado con Laravel. Incluye catalogo
publico, administracion de productos e inventario, carrito de compras persistente
y una cuenta real para clientes.

Los Sprints 0 al 5 estan completados. El siguiente bloque de desarrollo es el
Sprint 6, enfocado en checkout, entregas y pedidos reales.

## Funcionalidades implementadas

### Tienda publica

- Catalogo con categorias, marcas, ofertas, busqueda y filtros combinables.
- Detalle de producto con imagen principal, galeria y control de disponibilidad.
- Carrito para invitados con validacion de stock y sincronizacion de precios.
- Carrito persistente por cliente y fusion segura al iniciar sesion o registrarse.
- Ajustes informados cuando cambia el precio, stock o visibilidad de un producto.
- Navegacion y footer adaptados al estado invitado o autenticado.

### Administracion

- Administracion de categorias, marcas, productos e imagenes.
- Inventario, movimientos de stock, alertas y dashboard administrativo.
- Configuracion de umbrales de stock por producto y visibilidad publica.

### Clientes y cuenta

- Registro e inicio de sesion de clientes.
- Verificacion de correo y recuperacion de contrasena.
- Inicio de sesion y vinculacion segura con Google.
- Perfil con nombre, telefono, correo y avatar recortable.
- Cambio de contrasena y gestion de metodos de acceso.
- Direcciones guardadas para Lima Metropolitana y Callao con UBIGEO canonico.
- Menu de cuenta responsive y cierre de sesion con confirmacion.
- Checkout protegido para clientes autenticados con correo verificado.

## Alcance actual

El acceso a checkout ya exige una identidad verificada, pero la creacion de
pedidos, tarifas de envio, recojo, pagos con Culqi, cupones y promociones se
implementaran en los siguientes sprints. Las vistas de resultado de pago son
todavia una base visual y no representan transacciones reales.

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

La aplicacion estara disponible normalmente en `http://127.0.0.1:8000`.

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

Al cierre del Sprint 5, la suite completa registra `267 tests` y
`1464 assertions`.

## Documentacion

- [Roadmap general](docs/SPRINTS.md)
- [Roadmap del Sprint 5](docs/SPRINT_5_ROADMAP.md)
- [Estado del Sprint 5](docs/SPRINT_5_STATUS.md)

Los roadmaps y estados de los sprints anteriores tambien se encuentran en
[`docs/`](docs/).
