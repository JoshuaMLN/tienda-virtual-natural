# Sprint 0 - Estado

Fecha: 2026-06-30

## Validaciones realizadas

- Laravel Framework: 13.18.0
- PHP: 8.3.16
- Composer: 2.8.11
- Aplicacion: VitaNatural
- Entorno: local
- URL: http://127.0.0.1:8000
- Timezone: America/Lima
- Locale: es
- Base de datos: MySQL/MariaDB mediante `DB_CONNECTION=mysql`
- Driver PHP: `pdo_mysql` habilitado

## Comandos ejecutados

```bash
php artisan config:clear
php artisan migrate
php artisan migrate:status
php artisan storage:link
php artisan route:list
php artisan view:cache
php artisan test
git init
```

## Resultado

- Conexion a base de datos validada.
- Migraciones base ejecutadas:
  - `0001_01_01_000000_create_users_table`
  - `0001_01_01_000001_create_cache_table`
  - `0001_01_01_000002_create_jobs_table`
- `storage:link` creado.
- 27 rutas cargadas.
- Vistas Blade compiladas correctamente.
- Tests base: 2 tests, 2 passed.
- Git inicializado correctamente.

## Pendiente antes de Sprint 1

- Definir si se usara MySQL/MariaDB local de forma definitiva o SQLite para testing.
- Crear migraciones del catalogo real.
- Crear seeders de categorias, marcas y productos.
- Reemplazar arrays mock del frontend por consultas reales.
