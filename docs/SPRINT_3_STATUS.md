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

## Fase 2: Servicio de dominio de inventario

Estado: Completada

Implementado:
- Servicio `InventoryService`.
- Metodo `increase()` para ingresos de inventario.
- Metodo `decrease()` para salidas de inventario.
- Metodo `adjust()` para ajustes a un stock final especifico.
- Metodo `ensureAvailable()` para validar disponibilidad reutilizable en carrito/pedidos.
- Excepcion `InsufficientStockException` con cantidad solicitada y stock disponible.
- Transacciones para actualizar `products.stock` y crear el movimiento de inventario de forma atomica.
- Bloqueo de fila con `lockForUpdate()` al modificar stock.
- Validacion de cantidades positivas para ingresos y salidas.
- Validacion de ajuste no negativo.
- Motivos por defecto para movimientos cuando la capa superior no envia uno.
- Tests feature para ingresos, salidas, ajustes, stock insuficiente y validaciones.

Validaciones:
- `php -l` en servicio, excepcion y tests.
- `php artisan test tests\Feature\InventoryServiceTest.php`

Resultado de tests:

```txt
7 tests, 7 passed, 27 assertions
```

## Siguiente fase

Fase 3: Admin de stock real.
