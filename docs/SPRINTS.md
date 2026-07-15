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

Estado: completado.

Detalle: `docs/SPRINT_2_ROADMAP.md`.

Entregables:
- CRUD de categorias.
- CRUD de marcas.
- CRUD de productos.
- Activar/desactivar productos.
- Subida de imagen principal y galeria.
- Validaciones con Form Requests.

## Sprint 3: Stock e inventario

Objetivo: controlar disponibilidad antes de vender.

Estado: definido.

Detalle: `docs/SPRINT_3_ROADMAP.md`.

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

## Sprint 5: Autenticacion, clientes y cuenta

Objetivo: implementar identidad real de clientes y convertir el carrito de sesion en un carrito persistente por usuario.

Estado: completado.

Detalle: `docs/SPRINT_5_ROADMAP.md`.

Entregables:
- Registro, login y logout con correo y contrasena.
- Verificacion de correo y recuperacion de contrasena.
- Inicio de sesion con Google mediante Socialite.
- Vinculacion segura de Google con cuentas existentes sin duplicar usuarios.
- Perfil y seguridad de la cuenta.
- CRUD de direcciones guardadas.
- Carrito persistente por usuario y fusion segura con el carrito invitado.
- Cuenta autenticada y verificada como requisito para acceder al checkout.

## Sprint 6: Checkout y pedidos

Objetivo: crear pedidos reales desde el carrito de un cliente autenticado.

Entregables:
- Modelos `Order` y `OrderItem` con snapshots historicos.
- Checkout protegido y conectado al carrito persistente.
- Uso de direcciones guardadas y configuracion de entrega para Lima y Callao.
- Monto global configurable para envio gratis; `0` lo deshabilita y los mensajes publicos deben usar el valor vigente.
- Configuracion operativa centralizada de WhatsApp, correo de contacto y horario de atencion.
- Navbar, footer, contacto y avisos de envio deben consumir los datos operativos configurados, sin valores duplicados en las vistas.
- Recojo habilitado solo cuando exista una direccion configurada.
- Creacion transaccional e idempotente de pedidos en estado `pending`.
- Reserva y liberacion auditable de inventario.
- Resumen real, historial y detalle de pedidos para el cliente.
- Panel admin de pedidos conectado a DB.

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
- Directorio publico de marcas activas conectado al catalogo filtrado.
- Productos nuevos calculados desde `published_at` con una ventana de dias configurable; `0` deshabilita la seccion.
- Configuracion comercial de Instagram, Facebook, TikTok y otras redes sociales.
- Ocultar en la tienda cualquier red social que no tenga una URL configurada.

## Sprint 9: Pulido y seguridad

Objetivo: cerrar la base con calidad operativa.

Entregables:
- Policies y autorizacion admin.
- Tests de catalogo, carrito, pedidos y pagos.
- Logs de errores de pago.
- Validaciones finales.
- Optimizacion de consultas.
- Limpieza visual y responsive.

## Sprint 10: Fidelizacion y experiencia del cliente

Objetivo: incorporar funciones de retencion sin bloquear el flujo principal de compra.

Entregables:
- Favoritos persistentes por cliente.
- Acceso a favoritos desde navbar y cuenta.
- Base para puntos o beneficios por compras.
- Preferencias y notificaciones del cliente.
