# Sprint 3 - Estado

Fecha: 2026-07-07

Estado: Completado

## Cierre final

Sprint 3 cerrado a nivel tecnico. El modulo administrativo ya cuenta con inventario real, movimientos auditables, alertas operativas, configuracion de disponibilidad publica, visibilidad coherente de productos y resumenes UX para productos, categorias y marcas.

Validaciones finales:
- `php -l` en controladores modificados.
- `node --check public\js\app.js`
- `php artisan test tests\Feature\AdminStockTest.php`
- `php artisan test tests\Feature\AdminProductTest.php`
- `php artisan test tests\Feature\AdminCategoryTest.php tests\Feature\AdminBrandTest.php`
- `php artisan view:cache`
- `php artisan test`
- `git diff --check`
- `python -m graphify update .`

Resultado final de tests:

```txt
115 tests, 115 passed, 614 assertions
```

Ultimos ajustes incluidos:
- Composer del topbar optimizado para calcular notificaciones una sola vez por request.
- Visibilidad admin/publica corregida:
  - productos inactivos con fecha pasada se muestran como `Oculto`
  - filtro `Publicado` usa la regla real de visibilidad publica
  - boton `Ver en tienda` queda deshabilitado cuando el producto no es visible publicamente
  - tooltip explica si el producto esta oculto por estado, categoria o marca
- Warning de visibilidad reubicado en el formulario de producto para no romper la grilla.
- Resumen UX en productos con contador principal, chips compactos y desplegable mobile.
- Resumen UX equivalente en categorias con activas, inactivas, destacadas, con productos y sin productos.
- Resumen UX equivalente en marcas con activas, inactivas, con productos y sin productos.
- Tests feature para visibilidad admin de productos y resumenes UX.

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

## Fase 3: Admin de stock real

Estado: Completada

Implementado:
- Controlador `Admin\StockController`.
- Ruta `admin.stock.index` conectada a datos reales.
- Ruta `admin.stock.movements.index` para consultar historial por producto.
- Listado de productos con imagen, SKU, categoria, marca, stock actual, stock minimo, estado e historial.
- Edicion de `low_stock_threshold` desde la pantalla de stock mediante modal especifico por producto.
- Stock base editable solo al crear producto; al editar queda en lectura y los cambios deben hacerse por movimientos.
- Estados de inventario reutilizables desde `Product`:
  - `optimo`
  - `bajo-stock`
  - `sin-stock`
- Filtros por texto/SKU, categoria, marca y estado de stock.
- Paginacion con `withQueryString()` para conservar filtros.
- Orden por nombre de producto ascendente, alineado con admin productos.
- Resumen de productos totales, unidades en inventario, bajo stock y sin stock.
- Vista de historial de movimientos por producto en modo lectura.
- Acceso visual preparado para registrar movimientos en Fase 4.
- Tests feature para listado real, filtros, paginacion con filtros e historial.

Validaciones:
- `php -l` en controlador, modelo y tests.
- `php artisan test tests\Feature\AdminStockTest.php`
- `php artisan route:list --name=admin.stock`
- `php artisan view:cache`
- `php artisan test`

Resultado de tests:

```txt
89 tests, 89 passed, 420 assertions
```

## Fase 4: Registro manual de movimientos

Estado: Completada

Implementado:
- Request `StoreInventoryMovementRequest`.
- Ruta `admin.stock.movements.store`.
- Metodo `StockController::storeMovement()`.
- Registro manual de movimientos desde `/admin/stock`.
- Modal por producto para:
  - ingreso
  - salida
  - ajuste
- Uso de `InventoryService` para modificar stock y crear auditoria.
- Manejo de `InsufficientStockException` cuando una salida supera el stock disponible.
- Validacion de tipo de movimiento, cantidad, stock final, motivo, referencia y notas.
- Reapertura del modal correcto cuando hay errores de validacion.
- Alternancia de campos en frontend entre cantidad y stock final segun el tipo de movimiento.
- Historial actualizado con movimientos creados desde admin.
- Tests feature para ingresos, salidas, stock insuficiente, ajustes, validaciones e historial.

Validaciones:
- `php -l` en controlador, request y tests.
- `node --check public\js\app.js`
- `php artisan test tests\Feature\AdminStockTest.php`
- `php artisan route:list --name=admin.stock`
- `php artisan view:cache`
- `php artisan test`

Resultado de tests:

```txt
95 tests, 95 passed, 464 assertions
```

## Fase 5: Alertas y visibilidad publica

Estado: Completada

Implementado:
- Tabla `settings` para configuraciones globales.
- Configuracion global `public_stock_display_threshold` con valor por defecto `10`.
- Pantalla admin `Productos > Configuracion` para editar el umbral publico de disponibilidad.
- Boton de acceso a configuracion desde el listado admin de productos.
- Estado visual de stock en el listado admin de productos.
- Disponibilidad publica reutilizable desde `Product`:
  - `public_stock_label`
  - `public_stock_text_class`
  - `public_stock_icon`
- Tarjetas publicas de producto muestran disponibilidad:
  - sin stock
  - pocas unidades
  - mas de N disponibles
  - en stock cuando el umbral global es 0
- Detalle de producto usa la disponibilidad publica configurada.
- Detalle de producto deshabilita cantidad y boton mock de carrito cuando no hay stock.
- Dashboard admin conectado a datos reales para:
  - card de productos activos
  - productos activos con bajo stock
- Sistema de notificaciones admin (campana topbar) conectado a `AdminNotificationService`.
- Proveedor de notificaciones para bajo stock y sin stock.
- Filtro inteligente en notificaciones (ignora productos inactivos).
- Tests feature para configuracion admin y mensajes publicos de stock.
- Tests feature para alertas de stock y renderizado dinámico en topbar.
