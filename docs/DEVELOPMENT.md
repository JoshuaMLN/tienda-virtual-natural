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

Este comando inicia el servidor Laravel, la cola, el scheduler y Vite. No
incluye Pail para que sea compatible con PHP en Windows. En entornos que
soporten la extension `pcntl`, los logs en tiempo real se pueden abrir aparte
con `composer run logs`.

Vite ignora los artefactos locales de Graphify y Playwright para que sus
reportes no disparen recargas ni aumenten innecesariamente el consumo de
memoria del watcher.

Para compilar los assets de frontend usa `npm run build`.

## Graphify

Usa Graphify como mapa de la estructura de codigo, pero confirma en el
repositorio cualquier hecho que afecte decisiones o comportamiento.

Cuando una tarea haya modificado codigo y corresponda refrescar el grafo
estructural, ejecuta:

```powershell
graphify update .
```

Ese subcomando reextrae los archivos de codigo y actualiza
`graphify-out/graph.json`, `graph.html` y `GRAPH_REPORT.md` sin requerir un
LLM. No uses la sintaxis antigua `graphify . --update`: intenta procesar tambien
documentos, imagenes o PDFs y puede solicitar un proveedor LLM.

Los cambios en documentacion, imagenes o PDFs requieren una actualizacion
semantica separada desde el asistente, con un proveedor LLM configurado. No es
necesario ejecutarla cuando la tarea solo modifica documentacion y no pide
actualizar esa capa semantica.

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
