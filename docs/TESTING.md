# Guia de pruebas

## Capas y resultados validados

PHPUnit y Playwright son capas complementarias. PHPUnit conserva la cobertura
principal de dominio, servicios, validaciones, permisos HTTP, concurrencia,
stock, idempotencia, notificaciones, transiciones de estado y contratos de
backend. Playwright valida los recorridos reales de navegador de bajo alcance.

Resultados validados:

- PHPUnit: **631 passed** / **4908 assertions**.
- Playwright: **5 passed**.

La suite E2E inicial cubre login real y estado de sesion para customer y admin,
la home publica, el perfil del customer, la denegacion de customer hacia
`/admin` y el dashboard administrativo. No sustituye las pruebas detalladas de
PHPUnit ni amplía todavia los flujos de carrito, checkout, pedidos o CRUD.

## Aislamiento de datos

| Capa | Base de datos |
| --- | --- |
| Desarrollo normal | MySQL normal configurado en `.env` |
| PHPUnit | SQLite `:memory:` |
| Playwright | MySQL exclusiva `tienda_virtual_natural_e2e` |

Nunca ejecutes el reset E2E contra la base de desarrollo. El usuario MySQL de
E2E debe tener privilegios exclusivamente sobre
`tienda_virtual_natural_e2e`, sin acceso a la base normal.

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

Para ejecutar la suite local completa:

```bash
npm run test:e2e
```

Ese script ejecuta primero el reset E2E y luego Playwright en Chromium, con un
worker, sin paralelismo ni reintentos. Laravel se inicia en
`http://127.0.0.1:8011`. Trazas, screenshots y video se retienen solo ante
fallos; los estados de autenticación en `playwright/.auth/` y los reportes de
prueba están ignorados por Git.
