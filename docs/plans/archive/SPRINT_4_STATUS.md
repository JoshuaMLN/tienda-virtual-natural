# Sprint 4 - Estado

Fecha: 2026-07-08

Estado: Completado

## Alcance confirmado
- Carrito local por sesion para visitantes.
- Arquitectura preparada para carrito por usuario en un sprint futuro.
- Sin checkout real, sin pagos Culqi, sin ordenes y sin descuento de inventario en este sprint.
- Se mantiene la vista actual `resources/views/shop/cart.blade.php`.
- Warnings de stock persistentes por sesion hasta cierre manual del usuario.
- Modal de cantidad para agregar productos desde cards de catalogo/home/relacionados.

## Fase 1: Capa de dominio

Estado: Completada

Implementado:
- DTO `CartItem`.
- DTO `Cart`.
- Interfaz `CartStorageInterface`, incluyendo contrato para warnings persistentes.
- Implementacion `SessionCartStorage` para items y warnings del carrito.
- Servicio `CartService` con `get`, `count`, `add`, `update`, `remove`, `clear` y `clearWarnings`.
- Recalculo del carrito desde productos vigentes.
- Validacion con `Product::active()` e `InventoryService::ensureAvailable()`.
- Excepcion `ProductUnavailableException` para productos no visibles en tienda.
- Ajuste automatico de cantidades cuando baja el stock, con mensaje detallando cantidad solicitada y cantidad disponible.
- Retiro automatico de productos sin stock, con mensaje detallando cantidad solicitada.
- Pruebas de dominio en `tests/Feature/CartServiceTest.php`.

Pendiente:
- (Nada pendiente)

## Fase 2: Controladores, rutas y validaciones

Estado: Completada

Implementado:
- `CartController` con `index`, `info`, `store`, `update`, `destroy`, `clear` y `clearWarnings`.
- `Route::view('/carrito', 'shop.cart')` reemplazado por controlador.
- Rutas HTTP para info, agregar, actualizar, eliminar, vaciar carrito y cerrar warnings.
- `AddToCartRequest`.
- `UpdateCartRequest`.
- Respuestas JSON consistentes con mensajes legibles.
- Pruebas HTTP en `tests/Feature/CartHttpTest.php`.

Pendiente:
- (Nada pendiente)

## Fase 3: Frontend dinamico

Estado: Completada

Implementado:
- Meta CSRF en `layouts.shop` para peticiones `fetch`.
- Contador real del carrito en navbar mediante `CartService`.
- Botones "Anadir al carrito" en cards de producto del catalogo/home.
- Modal de cantidad para cards de producto antes de agregar al carrito.
- Boton "Anadir al carrito" en detalle de producto conectado a la cantidad seleccionada.
- Script de carrito en `public/js/app.js` con Fetch API.
- Actualizacion asincrona del contador del navbar.
- Feedback visual con toast para exito y errores.
- Control de cantidad respetando `min` y `max` del input.
- Toasts centrados con fondo diferenciado para exito y error.
- Pruebas HTML/HTTP en `tests/Feature/CartHttpTest.php`.

Pendiente:
- (Nada pendiente)

## Fase 4: Drawer y vista completa del carrito

Estado: Completada

Implementado:
- Drawer/offcanvas de carrito en `components.shop.cart-drawer`.
- Boton del navbar abre el drawer sin redirigir.
- Drawer con estado vacio, items, subtotal, total y acciones hacia checkout/carrito.
- Edicion de cantidades desde el drawer.
- Vista `resources/views/shop/cart.blade.php` con datos reales.
- Estado vacio del carrito.
- Actualizacion asincrona de cantidades.
- Eliminacion de items.
- Opcion para vaciar carrito.
- Modal de confirmacion antes de vaciar carrito.
- Warnings visibles en drawer y pagina de carrito con cierre manual.
- Resumen visual del pedido con totales reales.
- Cupones mantenidos como maqueta no funcional.
- Pruebas HTML/HTTP para drawer y vista completa del carrito.

Pendiente:
- (Nada pendiente)

## Fase 5: Pruebas y validacion

Estado: Completada

Implementado:
- Pruebas de Fase 1 para `CartService`.
- Test: agrega producto visible y con stock.
- Test: rechaza producto inactivo.
- Test: rechaza producto sin publicar.
- Test: rechaza producto con categoria inactiva o marca inactiva.
- Test: rechaza cantidad mayor al stock al agregar.
- Test: rechaza cantidad mayor al stock al actualizar.
- Test: actualiza, elimina y limpia items.
- Test: recalcula precios desde producto vigente.
- Test: ajusta cantidad cuando baja el stock.
- Test: retira producto cuando queda sin stock.
- Test: mantiene warnings de stock en sesion hasta cierre manual.
- Pruebas HTTP de Fase 2 para endpoints del carrito.
- Pruebas HTML/HTTP de Fase 3 para CSRF, contador navbar y botones de carrito.
- Pruebas HTML/HTTP de Fase 4 para drawer, pagina `/carrito`, estado vacio, carrito con items reales y acciones.
- Suite completa ejecutada: `php artisan test` -> 142 tests, 749 assertions.

Pendiente:
- (Nada pendiente)
