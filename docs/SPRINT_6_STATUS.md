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
- Plazo general de entrega con sobrescritura opcional por distrito y preparacion de recojo independiente.
- Ventanas de fechas calculadas desde horarios de atencion y cierres excepcionales, definitivas al confirmar el pago.
- Boleta y factura solicitan campos distintos y guardan snapshots fiscales.
- El codigo interno del pedido sera `PED-AAAA-NNNNNN`, con correlativo anual independiente de la marca y del comprobante fiscal.
- El precio administrado y publicado es el total con IGV incluido; su valor de venta e IGV se desglosan internamente en los snapshots.
- Los productos parten como gravados con 18 %, con dominio preparado para afectaciones exoneradas o inafectas.
- Los estados comerciales del pedido se separan de pago, entrega y reserva para evitar fuentes de verdad duplicadas.
- La cotizacion conserva las fechas provisionales mostradas y el pedido congela sus fechas definitivas al confirmarse el pago.
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

Estado: Completada

Implementado:
- Enums tipados para pedido, pago, entrega, modalidad, reserva, afectacion tributaria, solicitud fiscal, documento fiscal y resultado de envio.
- Value object `Money` para conversion, formato y operaciones en centimos; calculadora unica de impuestos incluidos y distribuidor proporcional de descuentos con residuo determinista.
- Precio comercial conservado como total final con IGV incluido y soporte para productos gravados, exonerados e inafectos.
- Formulario administrativo de productos con etiqueta `Precio de venta (IGV incluido)`, selector tributario y desglose auxiliar reactivo de valor de venta e IGV.
- Modelos, relaciones y factories de `Order`, `OrderItem`, `OrderSequence`, `OrderStatusHistory`, `StockReservation`, `FiscalDocument` y `FiscalDocumentDelivery`.
- Codigo `PED-AAAA-NNNNNN` independiente del ID, con contador anual, transaccion obligatoria, incremento atomico, limite de seis digitos y unicidad de base de datos.
- Creacion transaccional de pedido, items e historial inicial, con validacion central de snapshots, entrega, solicitud fiscal, importes y desglose tributario.
- Snapshots de cuenta, entrega, recojo, solicitud fiscal y productos; referencias a usuario, direccion y producto opcionales mediante `nullOnDelete`.
- Importes de pedidos e items almacenados en centimos, con reconciliacion exacta de subtotal, descuento, envio, bases gravada/exonerada/inafecta, IGV y total.
- Estados independientes y transiciones protegidas para pedido, pago y entrega, incluidas precondiciones por modalidad, pago, entrega y reservas.
- Historial inmutable por dominio con estado anterior, estado nuevo, actor opcional, snapshots del actor, motivo, metadatos y fecha.
- Reservas unicas por item con decremento bloqueado de inventario, consumo, liberacion, cancelacion y vencimiento idempotentes.
- Coordinador transaccional de pago que consume reservas antes de confirmar el pago y evita que un pedido pagado libere stock por vencimiento.
- Movimientos de inventario preservados al eliminar productos y protegidos como registros historicos inmutables.
- Solicitud de boleta o factura congelada en el pedido, sin crear comprobante durante la solicitud.
- Comprobantes fiscales independientes solo para pedidos pagados, con boleta/factura principal unica, notas relacionadas, serie y correlativo fiscal unicos y anulacion auditable.
- Historial inmutable de cada intento de envio fiscal con destinatario, actor, resultado y error, sin duplicar el comprobante.
- APIs existentes de carrito, entrega y configuracion alineadas al value object monetario y con importes explicitos en centimos.

Pendiente:
- Ninguno.

Validaciones:
- Nueve migraciones de Fase 3 aplicadas correctamente en el lote 15 sobre la base local.
- Prueba concurrente real con ocho procesos PHP sincronizados sobre una base compartida: correlativos unicos, consecutivos y sin huecos.
- Pruebas de Fase 3 para dinero, IGV incluido, exonerados, inafectos, residuos, correlativos, rollback, snapshots, referencias opcionales, estados, reservas, fiscalidad e historiales.
- Suite completa: 350 pruebas, 2161 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre todos los PHP modificados: correcto.
- `git diff --check`: correcto.

## Fase 4: Checkout real y experiencia del formulario

Estado: En progreso (4 de 6 etapas completadas)

Decisiones cerradas:
- La fase solo completa y revisa datos; pedidos, reservas e idempotencia comienzan en la Fase 5.
- Informacion legal y plazos comerciales configurables, con modo demostrativo mientras no exista empresa o RUC final.
- Terminos y condiciones versionados e inmutables despues de publicarse; politica de privacidad separada.
- Devoluciones posteriores a la entrega solo por defecto, dano, vencimiento o producto incorrecto, sin exigir sello intacto para reconocer un defecto.
- Aviso preferente de incidentes visibles dentro de 48 horas, sin extinguir derechos por superar ese plazo.
- Cancelacion permitida hasta la entrega al transportista y reembolso al mismo medio de pago.
- Procesamiento interno inicial de reembolso en 5 dias habiles y posible reflejo bancario de hasta 30 dias calendario.
- Tres intentos por tarifa, dos ciclos automaticos como maximo y 7 dias para pagar el segundo envio.
- Recojo configurable, inicialmente 14 dias desde que el pedido quede listo, con seguimiento manual al vencer.

Planificado:
- Etapa 4.1: configuracion legal y documentos versionados. Completada.
- Etapa 4.2: base real del checkout y carrito vigente. Completada.
- Etapa 4.3: contacto y direcciones propias. Completada.
- Etapa 4.4: modalidad y cotizacion de entrega. Completada.
- Etapa 4.5: datos fiscales, terminos y revision sin crear pedido.
- Etapa 4.6: UX, seguridad, pruebas integrales y cierre.

Pendiente:
- Etapa 4.5 completa.
- Etapa 4.6 completa.

Reglas acordadas para la Etapa 4.4:
- La direccion del cliente sera obligatoria solo para entrega a domicilio; el recojo usara los datos de contacto y la direccion configurada por la tienda.
- Cobertura y tarifa se resolveran exclusivamente desde el UBIGEO canonico de la direccion seleccionada, sin un segundo selector de distrito.
- Una direccion de un distrito inactivo se conservara, pero no habilitara domicilio; se ofreceran otra direccion, recojo si esta disponible y contacto por WhatsApp para otras opciones de entrega.
- Domicilio se seleccionara inicialmente solo cuando la direccion elegida tenga cobertura; no habra cambios silenciosos de direccion o modalidad.
- Las tarifas configuradas seran precios finales con IGV incluido y una tarifa `0` en un distrito activo representara entrega gratuita aunque el umbral global este deshabilitado.
- Domicilio mostrara fechas estimadas desde el plazo propio del distrito o el respaldo general; recojo usara un plazo de preparacion separado y mantendra los 14 dias configurables desde que el pedido quede listo.
- El conteo comenzara en el siguiente dia de atencion, usando horarios separados para lunes a viernes, sabado y domingo y omitiendo fechas no laborables configuradas.
- La modalidad, direccion aplicable, lineas del carrito y referencia de la cotizacion mostrada se conservaran en sesion, pero todos los importes se recalcularan en backend y la referencia debera coincidir antes de aceptarse.
- La Etapa 4.4 no creara pedidos ni reservas. La comparacion final y el modal para aceptar cambios de tarifa, cobertura, precios o stock corresponden a la Fase 5.

Etapa 4.1 completada:
- Identidad del proveedor y reglas comerciales configurables desde `/admin/legal`.
- Modo demostrativo mientras no exista identidad completa, documentos vigentes o habilitacion expresa de ventas reales.
- Validacion reutilizable de RUC peruano y bloqueo de activacion incompleta.
- Terminos y condiciones y politica de privacidad como documentos separados.
- Borradores unicos por tipo, contenido regenerable y deteccion de cambios posteriores en la configuracion.
- Publicacion transaccional con version correlativa, reemplazo de la version anterior y una sola publicacion activa por tipo garantizada por base de datos.
- Versiones publicadas y reemplazadas inmutables, con snapshot, huella, publicador e historial administrativo.
- Paginas publicas dinamicas `/terminos-y-condiciones` y `/politica-de-privacidad`, con aviso visible de modo demostrativo.
- Migraciones `2026_07_16_001200_create_legal_documents_and_settings` y `2026_07_16_001210_add_unique_draft_slot_to_legal_documents` aplicadas.
- Pruebas especificas de configuracion, autorizacion, RUC, borradores, publicacion, reemplazo, unicidad, snapshots, inmutabilidad y paginas publicas.
- Suite completa: 374 pruebas, 2278 aserciones.
- `php artisan view:cache`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.

Etapa 4.2 completada:
- `GET /checkout` usa controlador, request y servicio de lectura propios bajo `auth`, `customer` y `verified`.
- Carrito autenticado sincronizado con las reglas vigentes de visibilidad, stock y precio antes de renderizar.
- Resumen real con productos, cantidades, subtotal, valores gravados, exonerados e inafectos, IGV incluido y total en centimos.
- Carrito vacio bloqueado con redireccion a `/carrito` y aviso persistente hasta que el cliente lo cierre.
- Checkout estatico, productos de ejemplo y componentes ficticios de Culqi retirados; el pago permanece reservado al Sprint 7.
- Lectura sin creacion de direcciones, secuencias, pedidos, items, historiales, reservas, movimientos ni documentos fiscales.
- Pruebas HTTP y de vista para acceso, carrito vacio, impuestos mixtos, sincronizacion, visibilidad y ausencia de efectos secundarios.
- Validacion manual completada: 8 de 8 escenarios aprobados.
- Suite completa: 380 pruebas, 2329 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.

Etapa 4.3 completada:
- Borrador de checkout asociado al cliente y conservado en sesion sin modificar automaticamente su perfil.
- Nombre y celular precargados y editables para la compra; correo verificado mostrado como solo lectura y resuelto siempre desde la cuenta.
- Direcciones propias ordenadas con la predeterminada primero y seleccion segura sin exponer direcciones de otros clientes.
- Formulario integrado para guardar y usar una direccion nueva mediante el servicio transaccional existente.
- Primera direccion predeterminada automaticamente y checkbox explicito para reemplazarla al crear las siguientes.
- Provincia, distrito, departamento y UBIGEO resueltos con el catalogo canonico compartido por checkout y `Mis direcciones`.
- Limite de 10 direcciones aplicado en interfaz, request y dominio, con acceso directo a su administracion.
- Seleccion y campos conservados ante errores de validacion; borrador aislado por usuario dentro de la sesion.
- Guardado bloqueado para invitados, clientes sin verificar y carritos vacios, sin crear pedidos, secuencias, reservas ni movimientos.
- Sin migraciones nuevas; la etapa reutiliza `customer_addresses` y almacenamiento de sesion.
- Pruebas de acceso, precarga, propiedad, predeterminada, creacion, normalizacion, limite, persistencia, aislamiento y ausencia de efectos secundarios.
- Validacion manual completada: 11 de 11 escenarios aprobados.
- Suite completa: 391 pruebas, 2443 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.

Etapa 4.4 completada:
- Selector real de entrega a domicilio o recojo integrado entre contacto y direccion, sin campos de direccion duplicados.
- Direccion del cliente condicional: obligatoria para domicilio y completamente opcional para recojo.
- Borrador de checkout ampliado con modalidad, direccion nullable y snapshot versionado con huella de la cotizacion aceptada.
- Cotizacion centralizada en backend desde el carrito vigente y el UBIGEO canonico, sin confiar en tarifas, impuestos o totales enviados por JavaScript.
- Endpoint protegido `POST /checkout/cotizacion-entrega` con respuestas JSON estructuradas y validacion `422` consistente con los endpoints del carrito.
- Tarifas finales con IGV incluido y desglose tributario recalculado mediante la politica monetaria unica del dominio.
- Envio gratis por umbral y entrega gratuita por tarifa distrital `0` diferenciados expresamente.
- Distritos inactivos visibles como no disponibles sin eliminar direcciones guardadas ni cambiar silenciosamente de direccion o modalidad.
- Recojo habilitado solo con direccion de tienda, sin costo, con preparacion estimada y plazo configurable para recoger.
- Plazo general de entrega con sobrescritura opcional por distrito y retorno explicito al valor general desde el modal administrativo.
- Preparacion de recojo configurada de forma independiente al tiempo de entrega a domicilio.
- Horarios estructurados de apertura y cierre para lunes a viernes, sabado y domingo; un bloque opcional vacio se considera cerrado.
- Calendario administrativo de fechas sin atencion con fecha unica, motivo opcional, alta y eliminacion protegidas por rol.
- Calculo de ventanas de fechas desde el siguiente dia de atencion, omitiendo cierres semanales y extraordinarios.
- Mensajes de checkout con `Entrega estimada` o `Tu pedido estara disponible para recojo`, en lugar de rangos abstractos de dias habiles.
- Aviso superior de cobertura exclusiva en Lima Metropolitana y Callao, con alternativa de contacto por WhatsApp.
- Resumen dinamico de envio, valores tributarios y total en escritorio y celular; solicitudes concurrentes protegidas con `AbortController`.
- La referencia mostrada viaja de vuelta al backend y se compara antes de cualquier alta de direccion; una cotizacion obsoleta exige confirmar nuevamente.
- La huella incluye lineas canonicas con producto, cantidad, precio y tributacion, por lo que no confunde carritos distintos con el mismo total.
- Cada respuesta de cotizacion sincroniza tambien productos, cantidades, avisos y contador del carrito para evitar una pantalla parcialmente obsoleta.
- El alta de una direccion y su cotizacion final se coordinan transaccionalmente con bloqueo del distrito aplicable.
- Los datos de recojo se actualizan desde la misma respuesta vigente de la cotizacion.
- La huella de la cotizacion incluye las fechas estimadas, por lo que cambios relevantes del calendario exigen recalcular.
- La confirmacion de pago calcula y congela `delivery_estimated_from` y `delivery_estimated_to` sin modificar pedidos ya pagados cuando cambia el calendario.
- Configuracion administrativa aclarada como tarifa final con IGV incluido y `S/ 0.00` equivalente a entrega gratuita.
- Migracion `2026_07_20_000100_add_delivery_estimates_and_business_calendar` para plazos distritales, fechas estimadas del pedido, configuracion estructurada y calendario de cierres.
- Migracion aplicada correctamente en el lote 18 sobre la base local.
- Pruebas especificas de modalidad condicional, propiedad de direcciones, referencia aceptada, sincronizacion del carrito, distrito inactivo, tarifa, IGV, tarifa cero, umbral gratis, plazos distritales, recojo independiente, fines de semana, cierres, UBIGEO canonico, manipulacion y huella completa: 54 pruebas, 533 aserciones.
- Validacion manual completada: flujos de entrega y recojo, cotizaciones, fechas estimadas y comportamiento responsive aprobados.
- Suite completa: 426 pruebas, 2829 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.

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
