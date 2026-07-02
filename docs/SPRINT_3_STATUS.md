# Sprint 3 - Estado

Fecha: 2026-07-02

Estado: En progreso

## Fase 1: Modelo y migraciones de inventario

Estado: Completada

Implementado:
- Campo `low_stock_threshold` en `products` con valor por defecto `5`.
- Tabla `inventory_movements` para auditar ingresos, salidas y ajustes.
- Modelo `InventoryMovement`.
- Constantes de tipo de movimiento:
  - `in`
  - `out`
  - `adjustment`
- Relacion `Product::inventoryMovements()`.
- Relacion `InventoryMovement::product()`.
- Relacion opcional `InventoryMovement::createdBy()`.
- Casts numericos para stock y cantidades.
- Tests feature para umbral por defecto, relacion producto/movimientos y tipos definidos.

Validaciones:
- `php -l` en modelo, migraciones y `Product`.
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan test tests\Feature\InventoryModelTest.php`
- `php artisan test`
- `git diff --check`

Resultado de tests:

```txt
73 tests, 73 passed, 338 assertions
```

## Siguiente fase

Fase 2: Servicio de dominio de inventario.
