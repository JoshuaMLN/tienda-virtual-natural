# Desarrollo local

## Requisitos

- PHP 8.3 o superior y Composer.
- Node.js y npm.
- MySQL para el entorno normal.

## Instalacion y ejecucion

Instala las dependencias del backend y frontend:

```bash
composer install
npm install
```

Crea `.env` desde `.env.example`, configura la conexion MySQL local sin
versionar credenciales y genera la clave de aplicacion:

```bash
php artisan key:generate
```

Con la base local correcta configurada, prepara el esquema y los datos de
desarrollo:

```bash
php artisan migrate --seed
php artisan storage:link
```

Para iniciar los servicios de desarrollo usa:

```bash
composer run dev
```

Para compilar los assets de frontend usa `npm run build`.

## Reglas operativas

- Nunca compartas ni versiones `.env`, credenciales, tokens o estados de
  autenticacion.
- Antes de migrar, limpiar datos u otra operacion destructiva, confirma el
  entorno objetivo. No uses la base normal para E2E.
- Respeta los cambios preexistentes del worktree y consulta
  [AGENTS.md](../AGENTS.md) para las reglas del agente.
- Para explorar estructura y relaciones de codigo, usa Graphify cuando resulte
  util y contrasta las conclusiones importantes con el codigo real. No lo
  actualices durante diagnosticos o tareas de solo lectura.

## Referencias

- [TESTING.md](TESTING.md) es la fuente canonica de PHPUnit, E2E y Playwright.
- [DEPLOYMENT.md](DEPLOYMENT.md) contiene las operaciones de produccion.
