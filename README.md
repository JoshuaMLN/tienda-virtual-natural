# VitaNatural

VitaNatural es un ecommerce de productos naturales desarrollado como una
aplicacion web Laravel. Incluye tienda publica, administracion, cuentas de
clientes, carrito persistente y flujos de checkout y pedidos en evolucion.

## Stack principal

- PHP 8.3 y Laravel 13.
- MySQL.
- Blade, JavaScript, Bootstrap 5 y Vite.
- PHPUnit para backend y Playwright para pruebas E2E de navegador.

## Requisitos basicos

- PHP 8.3 o superior y Composer.
- Node.js y npm.
- MySQL para el entorno normal de desarrollo.

## Inicio rapido local

```bash
git clone https://github.com/JoshuaMLN/tienda-virtual-natural.git
cd tienda-virtual-natural
composer install
npm install
```

Crea `.env` desde `.env.example`, configura la conexion MySQL local y genera la
clave de aplicacion. Luego prepara la base local y los assets:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
```

Inicia el entorno de desarrollo:

```bash
composer run dev
```

Las migraciones modifican la base configurada en el entorno actual. Consulta la
guia de desarrollo antes de ejecutar operaciones destructivas.

## Documentacion

- [Indice de documentacion](docs/README.md)
- [Arquitectura](docs/ARCHITECTURE.md)
- [Desarrollo local](docs/DEVELOPMENT.md)
- [Pruebas](docs/TESTING.md)
- [Estado actual](docs/PROJECT_STATE.md)
- [Despliegue y operaciones](docs/DEPLOYMENT.md)
- [Planificacion de Sprints](docs/SPRINTS.md)
