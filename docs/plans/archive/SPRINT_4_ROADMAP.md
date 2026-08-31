# Sprint 4 - Carrito de Compras

Estado: Completado

Objetivo principal: crear un carrito funcional basado en sesion para visitantes, con reglas de stock y visibilidad reales, componentes asincronos para el frontend y una base preparada para migrar a carrito por usuario cuando se implemente autenticacion.

## Decisiones de alcance
- El carrito sera local a la sesion actual. No requiere login.
- La capa de almacenamiento debe quedar desacoplada mediante contrato para poder agregar despues un `DatabaseCartStorage` asociado a usuarios.
- Se mantiene la vista actual `resources/views/shop/cart.blade.php` y la ruta publica `/carrito`; no se movera a `shop/cart/index.blade.php` salvo necesidad real posterior.
- El carrito no descuenta stock. Solo valida disponibilidad. El descuento de inventario queda para checkout/pedidos.
- El precio se recalcula desde el producto vigente al renderizar el carrito. El snapshot definitivo de precio corresponde al futuro modelo de pedido.
- Checkout real, pagos Culqi, ordenes, cupones funcionales y login quedan fuera de este sprint.
- Se agrega modal de cantidad para cards de producto antes de agregar al carrito.
- Los warnings de correcciones automaticas de stock se guardan en sesion hasta cierre manual del usuario.

## Reglas de negocio
- Solo se puede agregar un producto que cumpla `Product::active()`: producto activo, publicado, categoria activa y marca activa si aplica.
- La cantidad total solicitada para un producto debe ser menor o igual al stock disponible.
- Si un producto del carrito deja de estar disponible, se elimina o queda sin stock, el carrito debe detectarlo al recalcular y devolver una advertencia clara.
- Si el stock baja por debajo de la cantidad guardada, se ajusta la cantidad al stock disponible.
- Las advertencias de stock deben indicar cantidad solicitada y cantidad final/disponible.
- Las advertencias de stock deben persistir hasta que el usuario las cierre manualmente.
- El contador del navbar debe salir del carrito real de sesion, no de un numero fijo.
- Los mensajes de error deben ser legibles para el cliente final, no claves tecnicas como `validation.required`.

## Fase 1: Capa de dominio
Crear la logica central del carrito, aislada de controladores y vistas.
- DTO `CartItem`: representa un item renderizable del carrito con producto, cantidad, precio vigente, subtotal, imagen y estado de disponibilidad.
- DTO `Cart`: representa el carrito completo con items, subtotal, total, cantidad total de unidades, cantidad de productos y advertencias.
- Interfaz `CartStorageInterface`: contrato para leer, escribir y limpiar el carrito, incluyendo warnings.
- Implementacion `SessionCartStorage`: guarda solo `product_id => quantity` en sesion para evitar datos obsoletos y guarda warnings de stock hasta cierre manual.
- Servicio `CartService`:
  - `get()`
  - `count()`
  - `add(Product|int $product, int $quantity)`
  - `update(Product|int $product, int $quantity)`
  - `remove(Product|int $product)`
  - `clear()`
  - `clearWarnings()`
  - Recalculo contra base de datos en cada lectura.
  - Validacion con `Product::active()` e `InventoryService::ensureAvailable()`.

## Fase 2: Controladores, rutas y validaciones
Exponer la logica del carrito hacia el frontend y reemplazar la vista estatica actual.
- `CartController`:
  - `index`: renderiza `/carrito` con el carrito real.
  - `info`: devuelve resumen JSON.
  - `store`: agrega producto.
  - `update`: actualiza cantidad.
  - `destroy`: elimina producto.
  - `clear`: vacia carrito.
  - `clearWarnings`: limpia warnings persistentes del carrito.
- Rutas sugeridas, manteniendo el orden y estilo actual de `routes/web.php`:
  - `GET /carrito`
  - `GET /carrito/info`
  - `POST /carrito/items`
  - `PATCH /carrito/items/{product}`
  - `DELETE /carrito/items/{product}`
  - `DELETE /carrito/warnings`
  - `DELETE /carrito`
- `AddToCartRequest`: valida producto visible en tienda y cantidad entera positiva.
- `UpdateCartRequest`: valida cantidad entera positiva y stock suficiente.
- Respuestas JSON consistentes: `cart`, `message`, `warnings`, `errors`.

## Fase 3: Frontend dinamico
Conectar el carrito con catalogo, detalle de producto, navbar y feedback visual.
- Script `cart.js` o extension ordenada de `public/js/app.js`, segun encaje mejor con la estructura actual.
- Conectar botones "Anadir al carrito" del catalogo y detalle de producto.
- En cards de catalogo/home/relacionados, abrir modal para elegir cantidad antes de agregar.
- Leer cantidad seleccionada en detalle de producto.
- Actualizar contador del navbar con la cantidad real del carrito.
- Mostrar toast o feedback visual para exito, errores de stock y producto no disponible.
- Mostrar warnings persistentes de stock en carrito y drawer, con cierre manual.
- Evitar agregar productos sin stock desde UI, manteniendo validacion fuerte en backend.

## Fase 4: Drawer y vista completa del carrito
Reemplazar los datos estaticos por datos reales y cubrir los estados de UX.
- Drawer/offcanvas `cart-drawer.blade.php` para resumen rapido del carrito.
- Vista `resources/views/shop/cart.blade.php` usando `CartService`.
- Estado vacio del carrito con accion para volver al catalogo.
- Tabla/lista responsive con producto, precio vigente, cantidad, subtotal y eliminar.
- Actualizacion asincrona de cantidades y eliminacion de productos.
- Edicion de cantidades dentro del drawer/offcanvas.
- Resumen de pedido visual, dejando cupones solo como maqueta no funcional.
- Confirmacion antes de vaciar carrito.
- Boton "Proceder al checkout" visible, pero checkout funcional queda para Sprint 5.

## Fase 5: Pruebas y validacion
Crear pruebas automatizadas enfocadas en reglas de negocio y endpoints.
- Tests unitarios o feature para `CartService`.
- Agrega producto visible y con stock.
- Rechaza producto inactivo, sin publicar, con categoria inactiva o marca inactiva.
- Rechaza cantidad mayor al stock.
- Actualiza, elimina y limpia items.
- Recalcula precios desde producto vigente.
- El contador del carrito responde segun sesion.
- La vista `/carrito` muestra estado vacio y carrito con items reales.
- Warnings por baja de stock persisten hasta cierre manual.
- Drawer y modal de cantidad estan presentes en el layout de tienda.
- Ejecutar `php artisan test` antes de cerrar el sprint.
