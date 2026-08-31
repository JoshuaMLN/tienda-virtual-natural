# Sprint 3 - Roadmap

Fecha: 2026-07-02

Estado: En progreso

## Objetivo

Implementar control real de stock e inventario para que el admin pueda mantener disponibilidad por producto, registrar movimientos y detectar bajo stock antes de avanzar al carrito.

## Punto de partida

- Sprint 2 cerrado funcionalmente.
- `Product` ya existe y tiene `stock`.
- El catalogo publico ya puede distinguir productos disponibles mediante `is_in_stock`.
- El admin de productos ya permite editar stock base, pero no existe historial de movimientos ni alertas operativas.
- Las vistas admin de stock existen como UI mock y deben conectarse a datos reales.

## Alcance funcional

- Stock actual por producto.
- Stock minimo configurable por producto.
- Movimientos de inventario: ingreso, salida y ajuste.
- Historial de movimientos por producto.
- Motivo/notas y referencia administrativa para cada movimiento.
- Alertas de bajo stock y sin stock en admin.
- Filtros de inventario por texto, categoria, marca y estado de stock.
- Servicio de dominio para validar disponibilidad y evitar stock negativo.
- Preparacion de validacion para Sprint 4, sin implementar carrito real.
- Tests feature/unitarios para movimientos, alertas y reglas de stock.

## Fuera de alcance

- Carrito funcional. Eso corresponde al Sprint 4.
- Reserva de stock por carrito o checkout. Eso corresponde a Sprints 4 y 5.
- Pedidos reales y descuento de stock por orden pagada. Eso corresponde a Sprints 5 y 7.
- Proveedores, compras y costos de inventario. Se puede dejar para un sprint futuro.
- Variantes por talla/color/presentacion. Por ahora el stock vive a nivel de producto.

## Decisiones recomendadas

- Mantener `products.stock` como stock actual para consultas rapidas.
- Agregar `products.low_stock_threshold` para stock minimo.
- Crear tabla `inventory_movements` como fuente de auditoria.
- Todo cambio manual de stock debe crear un movimiento.
- No permitir que una salida deje el stock en negativo.
- Usar transacciones al crear movimientos para actualizar `products.stock`.
- Centralizar reglas en un servicio, por ejemplo `App\Support\Inventory\InventoryService`, para reutilizarlo en carrito y pedidos.
- No recalcular todo el stock desde movimientos en cada request; usar movimientos como auditoria y `products.stock` como lectura operativa.

## Fase 1: Modelo y migraciones de inventario

Objetivo: crear la base de datos y relaciones para auditar cambios de stock.

Tareas:
- Agregar migracion para `low_stock_threshold` en `products`.
- Crear migracion para `inventory_movements`.
- Campos recomendados para movimientos:
  - `product_id`
  - `type`: `in`, `out`, `adjustment`
  - `quantity`
  - `stock_before`
  - `stock_after`
  - `reason`
  - `notes`
  - `reference`
  - `created_by`
- Crear modelo `InventoryMovement`.
- Agregar relacion `Product::inventoryMovements()`.
- Agregar casts y constantes/enums simples para tipos de movimiento.

Criterio de salida:
- La base de datos soporta historial de inventario y cada producto puede tener stock minimo.

## Fase 2: Servicio de dominio de inventario

Objetivo: centralizar las reglas de stock para no duplicarlas en controladores.

Tareas:
- Crear `InventoryService`.
- Implementar metodos:
  - `increase(Product $product, int $quantity, array $data = [])`
  - `decrease(Product $product, int $quantity, array $data = [])`
  - `adjust(Product $product, int $newStock, array $data = [])`
  - `ensureAvailable(Product $product, int $quantity)`
- Registrar movimientos dentro de transacciones.
- Validar cantidad positiva.
- Bloquear salidas que dejen stock negativo.
- Guardar `stock_before` y `stock_after`.
- Definir excepcion de dominio para stock insuficiente.

Criterio de salida:
- Todo cambio de stock pasa por una unica capa de reglas y deja auditoria.

## Fase 3: Admin de stock real

Objetivo: reemplazar la vista mock de stock por informacion real y accionable.

Tareas:
- Crear `Admin\StockController` o `Admin\InventoryController`.
- Conectar la ruta admin de stock a controlador real.
- Mostrar productos con:
  - imagen
  - SKU
  - producto
  - categoria
  - marca
  - stock actual
  - stock minimo
  - estado: optimo, bajo stock, sin stock
- Agregar filtros por texto/SKU, categoria, marca y estado de stock.
- Mantener paginacion con `withQueryString()`.
- Agregar accion rapida para registrar movimiento.
- Agregar vista o modal para ver historial de movimientos del producto.

Criterio de salida:
- El admin puede ver inventario real y detectar productos con bajo stock o sin stock.

## Fase 4: Registro manual de movimientos

Objetivo: permitir que el admin actualice stock con auditoria.

Tareas:
- Crear Form Request para movimientos de inventario.
- Crear formulario/modal para ingreso, salida y ajuste.
- Validar:
  - producto existente
  - tipo permitido
  - cantidad positiva para ingreso/salida
  - nuevo stock mayor o igual a cero para ajuste
  - motivo requerido
- Usar `InventoryService`.
- Mostrar mensajes flash claros.
- Refrescar el listado con el stock actualizado.
- Probar que las salidas no permitan stock negativo.

Criterio de salida:
- El admin puede aumentar, reducir o ajustar stock y queda historial completo.

## Fase 5: Alertas y visibilidad publica

Objetivo: reflejar disponibilidad de forma consistente sin construir aun el carrito.

Tareas:
- Mostrar estados visuales en admin:
  - sin stock
  - bajo stock
  - stock optimo
- Agregar contador/resumen de productos bajo stock y sin stock en dashboard o stock index.
- Ajustar tarjetas publicas y detalle para productos sin stock:
  - mostrar estado sin stock
  - deshabilitar acciones futuras de compra si ya existen botones mock
  - mantener visible el producto si esta activo y publicado, salvo decision contraria
- Preparar helper/metodo reutilizable para Sprint 4 al agregar al carrito.

Criterio de salida:
- El usuario y el admin ven disponibilidad coherente segun el stock real.

## Fase 6: Pruebas y cierre

Objetivo: cerrar el sprint con reglas de inventario verificadas.

Tareas:
- Tests para migraciones/modelos de inventario.
- Tests del servicio:
  - ingreso aumenta stock
  - salida reduce stock
  - ajuste fija stock
  - salida con stock insuficiente falla
  - todo movimiento guarda `stock_before` y `stock_after`
- Tests feature de admin stock:
  - listado real
  - filtros
  - registro de movimiento
  - alerta de bajo stock
- Verificar disponibilidad publica con productos sin stock.
- Ejecutar:
  - `php artisan migrate`
  - `php artisan route:list --name=admin`
  - `php artisan view:cache`
  - `node --check public/js/app.js`
  - `npm.cmd run build`
  - `git diff --check`
  - `php artisan test`

Criterio de salida:
- Inventario real operativo, tests pasando y Sprint 4 puede consumir reglas de disponibilidad.

## Rutas esperadas

- `admin.stock.index`
- `admin.stock.movements.store`
- `admin.stock.movements.index`
- `admin.products.stock.update` si se decide editar stock minimo desde producto

## Criterios de cierre del Sprint 3

- Cada producto tiene stock actual y stock minimo.
- Los movimientos de inventario quedan auditados.
- El admin puede registrar ingresos, salidas y ajustes.
- No se permite stock negativo.
- El listado de stock muestra alertas reales.
- El catalogo publico refleja productos sin stock.
- Las reglas quedan preparadas para el carrito del Sprint 4.
- Tests y validaciones finales pasan.

## Orden recomendado de commits

1. `feat(inventory): add stock threshold and movements`
2. `feat(inventory): add inventory domain service`
3. `feat(admin): connect stock management to database`
4. `feat(admin): support inventory movements`
5. `feat(shop): reflect product stock availability`
6. `test(inventory): cover stock management flows`
