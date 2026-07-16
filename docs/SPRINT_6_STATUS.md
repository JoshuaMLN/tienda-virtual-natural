# Sprint 6 - Estado

Fecha: 2026-07-16

Estado: En progreso

## Objetivo

Crear checkout y pedidos reales sobre el carrito persistente, con configuracion de entrega, revalidacion concurrente, reservas de stock, datos fiscales, vistas de cliente y administracion protegida.

## Alcance confirmado

- Solo clientes autenticados y verificados pueden comprar.
- Entrar al checkout no crea pedidos.
- El pedido nace al confirmar datos y resumen vigentes.
- Los conflictos se resuelven dentro del checkout sin perder campos.
- La reserva inicial para pagos inmediatos sera configurable, con 15 minutos por defecto.
- El vencimiento usa `expired` y libera inventario; no se trata como pago `failed`.
- Entrega configurable para Lima Metropolitana y Callao.
- Plazo general configurable de 1 a 2 dias habiles desde el pago confirmado.
- Boleta y factura solicitan campos distintos y guardan snapshots fiscales.
- SUNAT emite manualmente y asigna serie y correlativo.
- La tienda registra el PDF oficial, lo guarda en privado y permite enviarlo por correo.
- Culqi y la confirmacion real de pago corresponden al Sprint 7.

## Linea base

- Sprint 5 completado y suite base en verde.
- Usuario, correo verificado, direcciones y carrito persistente disponibles.
- Inventario y movimientos reales disponibles.
- Tabla `settings` disponible para extender configuraciones.
- Checkout, pedidos de cliente y pedidos admin aun son maquetas.
- Rutas `/admin` sin autenticacion al iniciar el sprint.

## Fase 1: Seguridad administrativa base

Estado: Completada

Implementado:
- Enum `UserRole` con roles `customer` y `admin` sobre la tabla `users`.
- Migracion con `customer` como valor predeterminado para conservar todas las cuentas existentes.
- Cast, scopes, helpers y estados de factory para ambos roles.
- Middleware `admin`, `customer` y `customer_or_guest` registrado en Laravel.
- Todas las rutas privadas `/admin` protegidas por `auth` y rol administrativo.
- Cuenta, verificacion, checkout y logout publico restringidos a clientes.
- Carrito permitido para visitantes y clientes, pero bloqueado para administradores.
- Login administrativo independiente en `/admin/login`, sin Google ni `Recordarme`.
- Login publico incapaz de autenticar credenciales administrativas.
- Google bloqueado para correos o identidades vinculadas a un administrador.
- Rate limiting administrativo de cinco intentos por correo e IP.
- Recuperacion administrativa con respuesta neutra, token temporal y correo propio.
- Recuperacion publica y administrativa aisladas por rol aunque compartan el broker seguro de Laravel.
- Contrasena administrativa de al menos 12 caracteres, mayusculas, minusculas y numeros.
- Comando interactivo `php artisan admin:create` con contrasena oculta, correo unico y verificacion inicial.
- Topbar administrativo conectado al nombre, correo, avatar o iniciales del usuario real.
- Cierre de sesion desde topbar y sidebar mediante modal de confirmacion.
- Storefront adaptado para mostrar acceso al panel y ocultar compra y carrito durante una sesion administrativa.
- Pruebas admin existentes adaptadas para usar una cuenta administrativa real.
- Sidebar admin responsive con navegacion desplazable y cierre de sesion siempre visible.

Pendiente:
- Ninguno.

Validaciones:
- Migracion `2026_07_16_000100_add_role_to_users_table` aplicada en el lote 13.
- `php artisan route:list --path=admin -v`: 47 rutas administrativas; las privadas muestran `auth` y `admin`.
- `php artisan list --raw`: comando `admin:create` registrado.
- Autenticacion admin, Google y recuperacion: 34 pruebas, 263 aserciones.
- Regresion de catalogo, stock, dashboard y notificaciones admin: 85 pruebas, 475 aserciones.
- Integracion de cliente, cuenta y carrito: 129 pruebas, 823 aserciones.
- Suite completa: 285 pruebas, 1644 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre todos los PHP modificados: correcto.
- `git diff --check`: correcto.
- Creacion del primer administrador, acceso, recuperacion, separacion de roles y cierre de sesion validados manualmente por el usuario.

## Fase 2: Configuracion operativa y entrega

Estado: Completada

Implementado:
- Ajustes tipados para contacto, horario, envio gratis, reserva, plazo y recojo.
- Modelo y migracion `DeliveryDistrict` con UBIGEO unico, provincia, tarifa y estado.
- Seeder idempotente con los 43 distritos de Lima y 7 del Callao tomados del catalogo INEI existente.
- Carga inicial de los 50 distritos activos con tarifas referenciales de S/ 8.00 a S/ 25.00 desde San Isidro.
- Preservacion de tarifas y estados administrativos al volver a ejecutar el seeder.
- Servicio central para resolver cobertura, tarifa, envio gratis y disponibilidad de recojo.
- Pantalla `/admin/configuracion` con formulario operativo, resumen, filtros, paginacion y edicion modal de tarifas.
- Tiempo de reserva presentado como plazo para completar el pago, con unidad explicita en minutos.
- Paginacion global localizada, con pagina actual y total visibles tambien en mobile.
- Validaciones de WhatsApp, correo, montos, reserva, plazo coherente y direccion completa de recojo.
- Sidebar administrativo conectado al nuevo modulo de configuracion.
- Navbar, footer, contacto, home, avisos y checkout provisional conectados a una fuente unica.
- Promesa publica limitada correctamente a Lima Metropolitana y Callao.
- Contacto por WhatsApp indicado para destinos fuera de cobertura.
- Recojo oculto automaticamente cuando no existe una direccion configurada.
- Umbral `0` interpretado como envio gratis deshabilitado.
- Pruebas de seeder, configuracion, endpoints, cobertura, tarifa, envio gratis, recojo e integracion publica.

Pendiente:
- Ninguno.

Validaciones:
- Migraciones `2026_07_16_000200_create_delivery_districts_table` y `2026_07_16_000300_add_operational_settings` aplicadas en el lote 14.
- Base local con 50 distritos cargados.
- Pruebas especificas de Fase 2: 15 pruebas, 80 aserciones.
- Suite completa: 300 pruebas, 1732 aserciones.
- Rutas administrativas protegidas por `auth` y `admin`.
- `php artisan view:cache`: correcto.
- Pint sobre los PHP modificados: correcto.
- Smoke test de home y contacto: HTTP 200 con configuracion central aplicada.
- `git diff --check`: correcto.
- Formulario, tarifas, filtros, modal, configuracion publica, recojo y responsive validados manualmente por el usuario.

## Fase 3: Dominio de pedidos, reservas y documentos fiscales

Estado: Pendiente

Planificado:
- Modelos, migraciones y factories de pedidos e items.
- Estados separados e historial auditable.
- Snapshots de cliente, entrega, facturacion, productos e importes.
- Reservas de stock con consumo, liberacion y expiracion.
- Documentos fiscales independientes y numeracion unica.
- Pruebas de dominio y restricciones.

Pendiente:
- Implementacion completa de la fase.

## Fase 4: Checkout real y experiencia del formulario

Estado: Pendiente

Planificado:
- Direcciones guardadas o nueva direccion persistida.
- Entrega por distrito o recojo configurado.
- Resumen con tarifa y envio gratis.
- Campos condicionales de boleta y factura.
- Conservacion de datos ante errores.
- Pruebas HTTP, validacion y vistas responsive.

Pendiente:
- Implementacion completa de la fase.

## Fase 5: Revalidacion, creacion idempotente y reserva

Estado: Pendiente

Planificado:
- Deteccion estructurada de cambios de precio, stock, visibilidad y envio.
- Modal de aceptacion sin volver a rellenar checkout.
- Transaccion con bloqueos e idempotencia.
- Creacion de snapshots y reserva auditable.
- Limpieza segura del carrito.
- Scheduler de expiracion y liberacion exacta.
- Pruebas de concurrencia, rollback y doble envio.

Pendiente:
- Implementacion completa de la fase.

## Fase 6: Pedidos del cliente

Estado: Pendiente

Planificado:
- Historial y detalle reales.
- Linea de tiempo y estados legibles.
- Cancelacion de pedidos pendientes de pago.
- Descarga privada de comprobantes.
- Correos de creacion y cancelacion.
- Autorizacion por propietario y pruebas.

Pendiente:
- Implementacion completa de la fase.

## Fase 7: Admin de pedidos y comprobantes manuales

Estado: Pendiente

Planificado:
- Listado, filtros y detalle de pedidos reales.
- Transiciones administrativas validas y auditadas.
- Registro de boleta o factura solo para pedidos pagados.
- Serie y correlativo copiados del comprobante emitido en SUNAT.
- PDF privado, XML opcional y estado de anulacion.
- Envio y reenvio por correo con trazabilidad.
- Pruebas de archivos, correo y autorizacion.

Pendiente:
- Implementacion completa de la fase.

## Fase 8: Integracion, pruebas y cierre

Estado: Pendiente

Planificado:
- Suite funcional de seguridad admin, checkout, pedidos, reservas y comprobantes.
- Validacion manual desktop y mobile.
- Migraciones, rutas, vistas, Pint y build.
- Documentacion de scheduler, colas y despliegue.
- Actualizacion final de Graphify y documentos.

Pendiente:
- Implementacion completa de la fase.

## Riesgos controlados

- Sobreventa: bloqueos, reservas y transacciones.
- Pedidos duplicados: clave de idempotencia.
- Cambios durante checkout: revision versionada y modal de reconfirmacion.
- Stock cautivo: expiracion programada y liberacion idempotente.
- Datos historicos mutables: snapshots del pedido.
- Acceso administrativo publico: Fase 1 obligatoria antes del resto.
- Exposicion de comprobantes: almacenamiento privado y autorizacion.
- Numeracion fiscal incorrecta: se copia desde SUNAT y se valida unicidad.

## Bloqueos actuales

- Ninguno para iniciar la Fase 1.

## Resultado esperado

- Al cerrar el sprint, el sistema podra crear y administrar pedidos pendientes de pago con stock reservado, y Sprint 7 podra integrar Culqi sin modificar las reglas centrales de checkout, inventario o gestion fiscal manual.
