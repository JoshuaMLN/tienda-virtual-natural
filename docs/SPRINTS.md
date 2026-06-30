# Roadmap de Sprints - VitaNatural

Este documento define el orden de construccion del backend y la logica de negocio del ecommerce.

## Sprint 0: Base tecnica

Objetivo: dejar el proyecto listo para crecer sin deuda accidental.

Criterios de salida:
- `.env` configurado para la base de datos local.
- Conexion a base de datos validada con migraciones.
- `php artisan migrate:status` funciona.
- `php artisan route:list` funciona.
- `php artisan view:cache` compila las vistas.
- `php artisan test` ejecuta sin fallos base.
- `storage:link` creado o documentado si el entorno no lo permite.
- Roadmap versionado en `docs/SPRINTS.md`.

## Sprint 1: Catalogo real

Objetivo: reemplazar arrays mock por datos de base de datos.

Entregables:
- Modelos y migraciones: `Category`, `Brand`, `Product`, `ProductImage`.
- Slugs para categorias y productos.
- Seeders con categorias, marcas y productos iniciales.
- Home, catalogo y detalle leyendo desde DB.
- Filtros iniciales por categoria, marca y busqueda.

## Sprint 2: Admin de catalogo

Objetivo: administrar el catalogo desde el panel.

Entregables:
- CRUD de categorias.
- CRUD de marcas.
- CRUD de productos.
- Activar/desactivar productos.
- Subida de imagen principal y galeria.
- Validaciones con Form Requests.

## Sprint 3: Stock e inventario

Objetivo: controlar disponibilidad antes de vender.

Entregables:
- Stock real por producto.
- Stock minimo.
- Movimientos de inventario: ingreso, salida y ajuste.
- Alertas de bajo stock en admin.
- Bloqueo de compra si no hay stock suficiente.

## Sprint 4: Carrito

Objetivo: carrito funcional en sesion.

Entregables:
- Agregar al carrito.
- Actualizar cantidad.
- Eliminar productos.
- Calcular subtotal, descuento y total.
- Validar stock al modificar cantidades.
- Preparar estructura para cupones.

## Sprint 5: Checkout y pedidos

Objetivo: crear ordenes reales desde el carrito.

Entregables:
- Modelos `Order`, `OrderItem`, `CustomerAddress`.
- Checkout conectado al carrito.
- Crear pedido en estado `pending`.
- Resumen real del pedido.
- Estados: pendiente, pagado, fallido y cancelado.
- Panel admin de pedidos conectado a DB.

## Sprint 6: Clientes y cuenta

Objetivo: que el cliente gestione informacion y pedidos.

Entregables:
- Registro/login real.
- Perfil del cliente.
- Direcciones guardadas.
- Historial de pedidos.
- Detalle del pedido para cliente.

## Sprint 7: Pagos con Culqi

Objetivo: integrar pago real sin marcar ordenes antes de confirmacion backend.

Entregables:
- Configuracion Culqi en `.env`.
- Crear intencion/cargo desde backend.
- Webhook de confirmacion.
- Validacion de firma e idempotencia.
- Orden marcada como pagada solo por confirmacion backend.
- Pantallas `success`, `failed` y `pending` conectadas a estados reales.

## Sprint 8: Promociones y banners

Objetivo: administrar contenido comercial.

Entregables:
- CRUD de banners.
- Productos destacados.
- Promociones simples.
- Cupones basicos.
- Home dinamico con banners y promociones.

## Sprint 9: Pulido y seguridad

Objetivo: cerrar la base con calidad operativa.

Entregables:
- Policies y autorizacion admin.
- Tests de catalogo, carrito, pedidos y pagos.
- Logs de errores de pago.
- Validaciones finales.
- Optimizacion de consultas.
- Limpieza visual y responsive.
