# Guia de pruebas

## Capas y resultados validados

PHPUnit y Playwright son capas complementarias. PHPUnit conserva la cobertura
principal de dominio, servicios, validaciones, permisos HTTP, concurrencia,
stock, idempotencia, notificaciones, transiciones de estado y contratos de
backend. Playwright valida los recorridos reales de navegador de bajo alcance.

Resultados validados:

- PHPUnit: **659 passed** / **5150 assertions**.
- Playwright: **10 passed** (incluye el recorrido fiscal administrativo y las
  operaciones de pedidos pagados en domicilio y recojo, en
  1920×1080, 1366×768 y 390×844).

La suite E2E cubre login real y estado de sesion para customer y admin, la home
publica, el perfil del customer, la denegacion de customer hacia `/admin`, el
dashboard administrativo y un recorrido fiscal aislado: envio manual auditado,
correccion, nota, anulacion y reemplazo como admin; consulta y descarga privada
del PDF vigente como customer. Tambien cubre el ciclo de domicilio
preparacion -> envio -> entrega, incidencias sin consumo y atribuibles al cliente
hasta requerir otro pago de envio, y el ciclo de recojo listo -> recogido. No
sustituye las pruebas detalladas de PHPUnit ni amplia todavia los flujos de
carrito o checkout.

En esos recorridos, la vista del customer conserva el rango aceptado de
domicilio en 1366x768 al preparar y enviar, y en 390x844 sustituye la estimacion
de recojo por su fecha limite solo cuando el pedido queda listo.

Como comprobacion manual complementaria de la Fase 8 se revisaron checkout,
listado y detalle de pedidos del cliente, y listado y detalle de pedidos del
admin en 1920x1080, 1366x768 y 390x844. Los recorridos inspeccionados no
presentaron desborde horizontal ni errores de consola. Esta verificacion no
sustituye la integracion ni el cobro real de Culqi, que corresponde al Sprint 7.

## Aislamiento de datos

| Capa | Base de datos |
| --- | --- |
| Desarrollo normal | MySQL normal configurado en `.env` |
| PHPUnit | SQLite `:memory:` |
| Playwright | MySQL exclusiva `tienda_virtual_natural_e2e` y almacenamiento privado `storage/app/e2e-private` |

Nunca ejecutes el reset E2E contra la base de desarrollo. El usuario MySQL de
E2E debe tener privilegios exclusivamente sobre
`tienda_virtual_natural_e2e`, sin acceso a la base normal.
El entorno E2E tambien separa sus comprobantes privados del almacenamiento de
desarrollo; las cargas de Playwright nunca usan `storage/app/private`.

## Preparacion inicial de Playwright

1. Copia la plantilla local y completa los valores obligatorios:

   ```powershell
   Copy-Item .env.e2e.example .env.e2e
   ```

   Configura `APP_KEY`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`,
   `E2E_CUSTOMER_EMAIL`, `E2E_ADMIN_EMAIL`, `E2E_CUSTOMER_PASSWORD` y
   `E2E_ADMIN_PASSWORD`. Los emails son identidades E2E sinteticas y
   deterministas; las contrasenas son valores locales E2E. Conserva
   `APP_ENV=e2e`, `DB_CONNECTION=mysql`,
   `DB_DATABASE=tienda_virtual_natural_e2e` y `DB_URL=`. No subas
   `.env.e2e` a Git.

   Puedes generar la clave local después de crear el archivo:

   ```bash
   php artisan key:generate --env=e2e
   ```

2. Crea previamente la base y un usuario MySQL exclusivos. Laravel no crea la
   base E2E. Como administrador MySQL, adapta usuario y secreto locales:

   ```sql
   CREATE DATABASE tienda_virtual_natural_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'tienda_e2e'@'127.0.0.1' IDENTIFIED BY 'secreto-local-unico';
   GRANT ALL PRIVILEGES ON tienda_virtual_natural_e2e.* TO 'tienda_e2e'@'127.0.0.1';
   ```

   No concedas privilegios sobre la base MySQL normal. No versionas esas
   credenciales ni las copies a configuraciones compartidas.

3. El reset rechaza configuracion cacheada. Si existe, elimínala antes del
   preflight; no hay una opción para ignorar esta guarda:

   ```bash
   php artisan config:clear --env=e2e
   ```

4. Comprueba las guardas sin adquirir bloqueo ni modificar datos:

   ```bash
   php artisan e2e:reset --env=e2e --check
   ```

   El preflight exige `APP_ENV=e2e`, MySQL como conexión default y driver PDO
   real, `SELECT DATABASE()` exactamente igual a
   `tienda_virtual_natural_e2e`, sufijo `_e2e`, `DB_URL` vacío y configuración
   no cacheada. Devuelve `0` solo cuando el entorno es seguro.

5. Instala exclusivamente Chromium para Playwright:

   ```bash
   npm run e2e:install
   ```

## Reset y ejecucion

Cuando el preflight haya pasado, restablece solo la base E2E:

```bash
npm run e2e:reset
```

El comando adquiere un bloqueo MySQL, ejecuta `migrate:fresh` sobre la conexión
`mysql`, llama exclusivamente a `E2eSeeder` y verifica datos centinela. No
llama a `DatabaseSeeder`, no crea la base y no expone flags para saltar las
guardas.

## Fixtures E2E de pedidos

Cada reset protegido crea cinco pedidos deterministas del customer E2E mediante
los servicios reales de creacion, reserva y confirmacion de pago. No se crean
mediante factories ni cambios directos de estado:

| Escenario | Estado | Uso principal |
| --- | --- | --- |
| Domicilio con envio | Pagado, tarifa E2E | Fechas pospago, preparacion y envio |
| Domicilio con envio gratis | Pagado, subtotal sobre el umbral | Total, desglose y umbral de envio gratis |
| Recojo | Pagado, sin costo de entrega | Preparacion, disponibilidad y plazo de recojo |
| Domicilio pendiente de pago | Reserva activa | Punto de partida para Culqi y pantallas pendientes |
| Fiscal | Pagado, boleta con historial administrativo | Correccion, anulacion, nota y reemplazo en navegador |

El reset repone esos escenarios antes de una auditoria o prueba de navegador.
No dependas de los codigos internos de pedido: se generan por el dominio. Las
acciones de una prueba pueden modificar los fixtures; ejecuta de nuevo el reset
protegido para volver al estado inicial.

El pedido pendiente conserva una reserva de al menos dos horas solo en E2E para
que una auditoria amplia o una prueba posterior de Culqi no lo venza durante la
ejecucion. No cambia el limite real de reservas configurado para la tienda.

Para ejecutar la suite local completa:

```bash
npm run test:e2e
```

Ese script ejecuta primero el reset E2E y luego Playwright en Chromium, con un
worker, sin paralelismo ni reintentos. Laravel se inicia en
`http://127.0.0.1:8011`. Trazas, screenshots y video se retienen solo ante
fallos; los estados de autenticación en `playwright/.auth/` y los reportes de
prueba están ignorados por Git.
