# Sprint 6 - Estado

Fecha: 2026-07-24

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

Estado: Completada

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
- Etapa 4.5: datos fiscales, terminos y revision sin crear pedido. Completada.
- Etapa 4.6: UX, seguridad, pruebas integrales y cierre. Completada.

Pendiente:
- Ninguno. Las seis etapas de la Fase 4 estan completadas.

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

Etapa 4.5 completada:
- Selector segmentado de boleta o factura, con boleta como opcion inicial y paneles condicionales accesibles.
- Boleta con DNI, carnet de extranjeria o pasaporte, numero, nombres, apellidos y correo fiscal.
- Factura con RUC peruano validado por checksum, razon social, domicilio fiscal y correo fiscal, sin solicitar DNI adicional.
- Politica fiscal compartida entre checkout y dominio de pedidos para documentos personales, RUC y campos incompatibles.
- Rechazo explicito de campos ocultos manipulados de factura en boleta y de boleta en factura.
- Datos fiscales normalizados e independientes del perfil y de la direccion de entrega.
- Boleta o factura guardables como borrador en la sesion cifrada mediante `POST /checkout/datos-fiscales`, con restauracion completa despues de recargar.
- El borrador fiscal no implica aceptacion de terminos; cambiarlo invalida una revision previa sin perder contacto ni entrega.
- Checkout reorganizado como recorrido de tres etapas: contacto y entrega, comprobante con terminos, y pago preparado para su integracion posterior.
- Avance protegido por estado real del borrador: no se pueden saltar etapas pendientes, las completadas pueden reabrirse y los errores muestran la etapa correspondiente.
- Un solo boton principal por etapa: `Continuar al comprobante` y `Continuar al pago`, con persistencia interna y navegacion secundaria para volver.
- Resumen y productos agrupados en una columna lateral fija de escritorio y en un bloque desplegable compacto para celular.
- Accion protegida `POST /checkout/revisar` que revalida carrito, contacto, propiedad de direccion, entrega, cotizacion, datos fiscales y terminos.
- Recalculo de la cotizacion antes de revisar; cualquier cambio invalida la revision, actualiza el resumen y exige una nueva confirmacion sin volver al carrito.
- Snapshot de revision versionado y firmado con contacto, correo verificado, cotizacion, datos fiscales, version legal, huella del contenido y hora de aceptacion del servidor.
- Aceptacion explicita de los terminos activos y enlace independiente a privacidad, sin mostrar el numero tecnico de version ni implicar consentimiento publicitario.
- Invalidacion automatica de la revision al cambiar contacto, entrega, correo, cotizacion, version legal o contenido de la sesion.
- Borrador conservado solo en sesion del servidor, con cifrado de sesiones habilitado y sin `localStorage` ni `sessionStorage`.
- Respuestas del checkout con `Cache-Control: no-store, private` para evitar cachear datos fiscales.
- Boton de guardado de contacto y entrega visible tambien al elegir recojo, fuera del bloque condicional de direccion.
- Revision sin crear pedidos, correlativos, items, reservas, movimientos, comprobantes ni historiales.
- Sin migraciones nuevas; la referencia legal persistente del pedido se incorporara en la Fase 5 al crear el snapshot historico definitivo.
- 21 pruebas nuevas para acceso, vistas, recorrido por etapas, guardado y restauracion de borradores, boleta, factura, DNI, RUC, documentos extranjeros, manipulacion, version legal, sesion, conservacion, recotizacion e invariantes fiscales.
- Suite completa: 447 pruebas, 3062 aserciones.
- Validacion manual completada: recorrido por etapas, resumen lateral con productos y comportamiento responsive aprobados.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.

Etapa 4.6 completada:
- Formularios de contacto y comprobante con estado `aria-busy`, texto de progreso y bloqueo de doble envio.
- Foco automatico en el primer campo invalido o alerta de la etapa visible despues de una respuesta con errores.
- Rutas mutables verificadas bajo `web`, `auth`, `customer` y `verified`, con token CSRF disponible en layout y formularios.
- Cobertura adicional para invalidar la revision al cambiar contacto, datos fiscales o correo, conservando el borrador fiscal cuando corresponde.
- Cobertura del retorno automatico a la etapa que contiene errores y de la ausencia de pedidos, reservas, movimientos o documentos como efecto lateral.
- Sin uso de `localStorage` ni `sessionStorage` para el borrador del checkout.
- 5 pruebas nuevas; checkout completo: 63 pruebas, 800 aserciones.
- Suite completa: 452 pruebas, 3157 aserciones.
- `php artisan view:cache`: correcto.
- `node --check public/js/app.js`: correcto.
- `npm.cmd run build`: correcto.
- Pint sobre PHP modificado: correcto.
- `git diff --check`: correcto.
- Validacion manual final completada: 6 de 6 escenarios aprobados para doble envio, foco de errores, teclado, historial, recuperacion de conexion y responsive extremo.

## Fase 5: Revalidacion, creacion idempotente y reserva

Estado: Completada

Reglas cerradas:
- Ajustar la propuesta a la cantidad disponible, retirar productos agotados u ocultos y no crear pedidos vacios.
- Aceptar cambios comerciales desde un modal con revalidacion inmediata y repetible; exigir nuevamente los terminos solo ante una nueva version legal.
- Reserva configurable con 15 minutos por defecto desde el commit exitoso, sin extensiones por recarga o reintento y con expiracion y liberacion exactas.
- Un solo pedido `pending_payment` con reserva vigente por cliente, con opcion de continuarlo o cancelarlo desde el flujo previo al pago.
- Limpiar solo lineas confirmadas del carrito, preservar adiciones concurrentes y no restaurar automaticamente pedidos vencidos.
- Mantener inmutables los snapshots del pedido ante cambios posteriores del catalogo.
- Reutilizar el mismo pedido para una misma clave idempotente y permitir un intento nuevo solo despues de cancelacion o vencimiento.

Planificado:
- Etapa 5.1: revalidacion y conflictos. Completada.
- Etapa 5.2: modal de aceptacion. Completada.
- Etapa 5.3: creacion transaccional e idempotente. Completada.
- Etapa 5.4: pedido pendiente y cancelacion. Completada.
- Etapa 5.5: expiracion automatica. Completada.
- Etapa 5.6: integracion, pruebas y cierre. Completada.

Pendiente:
- Ninguno. Las seis etapas de la Fase 5 estan completadas.

Etapa 5.1 completada:
- `CheckoutRevalidationService` compara el snapshot revisado con el carrito canonico, catalogo, tributacion, entrega, importes y terminos vigentes.
- Resultado tipado con estados `unchanged`, `changed` y `blocked`, referencias anterior y actual, cambios estructurados y lineas concurrentes preservadas.
- Codigos estables para precio, cantidad, retiro, tributacion, identidad, tarifa, cobertura, envio gratis, fechas, recojo, desglose monetario y version legal.
- Las cantidades propuestas nunca superan las revisadas; productos o unidades agregados despues permanecen fuera de la propuesta y del futuro pedido.
- Productos sin stock, ocultos o eliminados se retiran de la propuesta; si no queda ninguno, el resultado bloquea la continuacion.
- Cobertura o recojo no disponibles devuelven la causa concreta; una nueva version legal bloquea y exige aceptacion en la etapa de comprobante del checkout.
- El servicio conserva revision, contacto, entrega y datos fiscales, y no crea pedidos, correlativos, reservas, movimientos ni documentos.
- No se agregaron configuraciones: reutiliza precios, stock, afectacion tributaria, cobertura, tarifas, envio gratis, plazos y documentos legales existentes.
- 13 pruebas nuevas, 217 aserciones.
- Regresion de checkout: 68 pruebas, 1005 aserciones.
- Suite completa: 465 pruebas, 3374 aserciones.

Etapa 5.2 implementada:
- Endpoint `POST /checkout/revalidar` protegido por sesion, CSRF, autenticacion, rol cliente y correo verificado.
- Contrato JSON con estados HTTP diferenciados: listo `200`, cambio comercial `409`, bloqueo operativo o legal `422`.
- Referencia de revision obligatoria y huella de propuesta aceptada para rechazar pestañas obsoletas, datos manipulados y aceptaciones de una propuesta anterior.
- Aceptacion atomica en sesion de cotizacion y revision vigentes, preservando contacto, entrega, datos fiscales y lineas agregadas concurrentemente al carrito.
- Modal estatico y accesible con total anterior y actual, cambios comerciales legibles, aviso de lineas preservadas, `Aceptar cambios y continuar` y `Volver al carrito`.
- Una segunda variacion durante la aceptacion devuelve una nueva propuesta y repite el modal; no avanza silenciosamente.
- Los cambios de terminos bloquean la aceptacion comercial y llevan al paso de comprobante para una nueva lectura y aceptacion, sin perder los datos completados.
- La etapa no crea pedidos, correlativos, reservas, movimientos de inventario ni documentos fiscales; esa transaccion permanece en la Etapa 5.3.
- 10 pruebas nuevas de servicio y HTTP sobre aceptacion, concurrencia entre propuestas, referencias manipuladas, middlewares, contrato visual, terminos y ausencia de efectos de dominio.
- Pruebas especificas de Etapas 5.1 y 5.2: 23 pruebas, 383 aserciones.
- Regresion completa de checkout: 78 pruebas, 1171 aserciones.
- Suite completa: 475 pruebas, 3540 aserciones.
- `php artisan view:cache`, `node --check public/js/app.js`, build de produccion y Pint: correctos.

Etapa 5.3 implementada:
- Nuevo endpoint protegido `POST /checkout/confirmar`; `POST /checkout/revalidar` conserva su contrato puro y sigue sin crear efectos de dominio.
- `CheckoutOrderCreationService` coordina revalidacion final, bloqueo del cliente, documentos legales, productos y direccion, creacion del pedido y reserva de todas las lineas en una sola transaccion.
- Pedido, correlativo anual, items, snapshots comerciales y fiscales, historial inicial, reservas, movimientos de inventario y vencimiento compartido se confirman o revierten juntos.
- Clave UUID por intento y referencia unica de revision persistidas en `orders`; indices unicos de base de datos y bloqueo por cliente neutralizan doble click, reintentos, respuestas perdidas y confirmaciones concurrentes.
- Una misma clave devuelve el mismo pedido sin consumir otro correlativo, descontar stock ni limpiar el carrito nuevamente.
- Version, huella, fecha de aceptacion y snapshot completo de los terminos quedan congelados en el pedido; la referencia al documento es opcional y usa `nullOnDelete` sin destruir el historico.
- Datos de contacto, entrega o recojo, comprobante, importes en centimos, tributacion e imagen y presentacion de cada producto se copian desde los snapshots validados, sin confiar en importes enviados por el navegador.
- La reserva usa `stock_reservation_minutes`, con 20 minutos en las pruebas y 15 minutos como valor operativo predeterminado, sin extenderse por reintentos.
- La limpieza posterior se ejecuta en una segunda transaccion marcada con `cart_cleaned_at`: resta solo cantidades confirmadas y conserva productos o unidades agregados desde otra pestana.
- Redireccion privada inicial a `/checkout/pedidos/{codigo}/pendiente` con pedido, total y vencimiento reales; contador, continuidad y cancelacion corresponden a la Etapa 5.4.
- Migracion `2026_07_21_000100_add_checkout_confirmation_to_orders_table` aplicada.
- 9 pruebas nuevas para creacion completa, recojo, domicilio, factura, snapshots, referencia legal opcional, idempotencia, restricciones unicas, cambios aceptados, rollback intermedio, concurrencia paralela, carrito concurrente, autorizacion y privacidad.
- Suite completa: 486 pruebas, 3719 aserciones.

Etapa 5.3 validada:
- El usuario completo satisfactoriamente las validaciones manuales indicadas para creacion, idempotencia, privacidad y responsive.

Etapa 5.4 implementada:
- Nueva columna `pending_payment_owner_id`, nullable, con clave foranea e indice unico: la base de datos impide que dos solicitudes concurrentes reclamen la ranura pendiente del mismo cliente.
- La migracion conserva pedidos existentes y asigna una sola ranura a cada cliente que ya tenga un pedido pendiente con reserva activa.
- `PendingCheckoutOrderService` localiza el pedido bloqueante, conserva compatibilidad con reservas previas a la migracion y coordina una cancelacion transaccional e idempotente.
- Entrar nuevamente a `/checkout` o confirmar con una clave distinta redirige al pedido pendiente sin crear correlativos, reservas o movimientos adicionales, ni limpiar el nuevo carrito o su borrador.
- La ranura se libera en la misma transicion cuando pedido o pago abandonan su ciclo pendiente; cancelar restaura el stock exactamente una vez y no repone productos en el carrito.
- Pantalla privada con pedido, total, cantidades, vencimiento y contador ajustado con hora de servidor, mas acciones reales para seguir comprando o cancelar mediante modal de confirmacion.
- Nuevo endpoint `DELETE /checkout/pedidos/{codigo}/cancelar`, protegido por sesion, CSRF, rol cliente, correo verificado y comprobacion de propietario.
- La cancelacion rechaza pagos ya confirmados, registra al cliente como actor y conserva inmutables los historiales y snapshots.
- Migracion `2026_07_21_000200_add_pending_payment_owner_to_orders_table` aplicada.
- 7 pruebas nuevas y 81 aserciones adicionales sobre redireccion, segundo intento, carrito preservado, privacidad, metodo HTTP, contador, cancelacion repetida, pago confirmado, relaciones, unicidad y concurrencia de ocho procesos.
- Suite completa: 493 pruebas, 3800 aserciones.

Etapa 5.4 validada:
- El usuario aprobo contador, redireccion al pedido pendiente, carrito preservado, cancelacion, liberacion de stock y responsive.

Etapa 5.5 implementada:
- `PendingCheckoutOrderExpirationService` concentra el reloj de servidor, bloqueo de pedido, reconciliacion por cliente, cierre idempotente y procesamiento por lotes.
- Al vencer, las reservas pasan a `expired`, pedido y pago pasan a `expired`, la ranura pendiente se libera y cada cantidad vuelve al inventario exactamente una vez.
- El comando `orders:expire-pending` acepta lotes de 1 a 1000 pedidos y se ejecuta cada minuto con `withoutOverlapping(5)`.
- `composer run dev` inicia tambien `php artisan schedule:work`; produccion debera ejecutar el scheduler de Laravel mediante cron durante el despliegue.
- Entrar al checkout o confirmar un pedido reconcilia primero cualquier reserva vencida, incluso cuando el scheduler todavia no la proceso.
- La pantalla pendiente usa la hora entregada por el servidor; al llegar a cero llama al endpoint protegido de vencimiento, reintenta fallos de red y redirige al carrito con un aviso persistente.
- Abrir directamente una pantalla pendiente ya vencida tambien procesa el cierre antes de mostrar informacion obsoleta.
- El carrito no se repone al vencer y la fecha original de reserva nunca se extiende por recargas, reintentos, comando o reconciliacion.
- 9 pruebas nuevas y 89 aserciones adicionales sobre limite exacto, idempotencia, lotes, scheduler, opcion invalida, hora de servidor, privacidad, middleware, checkout sincronico y nuevo pedido posterior.
- Regresion de checkout y dominio: 125 pruebas, 1726 aserciones.
- Suite completa: 502 pruebas, 3889 aserciones.

Etapa 5.5 validada:
- El usuario aprobo manualmente el vencimiento por contador y scheduler, la liberacion exacta de stock, los avisos, el nuevo checkout posterior y el comportamiento responsive.

Etapa 5.6 implementada:
- Auditoria transversal de rutas, autorizacion, idempotencia, concurrencia, limpieza selectiva, reservas, cancelacion, expiracion y scheduler.
- La limpieza del borrador ahora exige que su huella coincida con la revision que creo el pedido; un reintento tardio de una clave antigua no borra un checkout nuevo.
- Prueba de regresion para reintento idempotente posterior a cancelacion, conservando el nuevo borrador, su carrito y el unico pedido original.
- README y `docs/DEPLOYMENT.md` actualizados con el alcance real del Sprint 6, configuracion productiva, Brevo, Google OAuth, scheduler, worker, seguridad, backups y actualizaciones.
- Regresion transversal de checkout, pedidos, inventario, carrito e impuestos: 177 pruebas, 2029 aserciones.
- Suite completa: 503 pruebas, 3904 aserciones.
- `php artisan migrate:status`: todas las migraciones aplicadas hasta el lote 20.
- `php artisan schedule:list`: vencimiento de pedidos registrado cada minuto.
- `php artisan view:cache`, `node --check public/js/app.js`, build de produccion, Composer y Pint sobre todos los PHP de la Fase 5: correctos.
- El Pint global conserva deuda previa en siete archivos no modificados por esta fase; no afecta la suite ni se mezclo con este cierre.

Etapa 5.6 validada:
- El usuario aprobo el cierre funcional y manual de la etapa. La Fase 5 queda completada con sus seis etapas.

## Fase 6: Pedidos del cliente

Estado: Completada (Etapas 6.1 a 6.6 cerradas)

Reglas cerradas:
- Estado comercial derivado sin perder los estados tecnicos independientes; `Entregado` corresponde a domicilio y `Recogido` a recojo.
- Pago fallido con reserva vigente se muestra como `Pago no completado`; despues del vencimiento se muestra `Vencido`.
- Historial descendente, 10 pedidos por pagina, busqueda por codigo y filtros comerciales agrupados.
- Cancelacion directa solo para `pending_payment` elegible; un pedido pagado remite al contacto de la tienda hasta integrar reembolso.
- Capacidad de reintento preparada sin boton de pago ficticio antes de Culqi.
- Timeline curado, snapshots historicos y enlaces al catalogo solo para productos vigentes y visibles.
- Comprobantes propios con PDF descargables desde almacenamiento privado, incluidos los anulados con estado visible.
- Correos de creacion, cancelacion y vencimiento enviados por cola despues del commit sin revertir el dominio ante fallos.
- Creacion, cancelacion y vencimiento enviados al correo snapshot, con una copia independiente al correo actual cuando sea distinto y este verificado; comprobantes enviados al correo fiscal.
- Documentos de identidad enmascarados en pantalla; fechas definitivas solo despues del pago.
- Recursos ajenos responden `404` mediante consultas acotadas o policies.

Completado en la Etapa 6.1:
- Resolvedor unico de estados comerciales para pedido, pago, entrega, modalidad y vencimiento de reserva.
- Listado privado limitado al cliente autenticado, ordenado por fecha e ID descendentes.
- Busqueda normalizada por codigo `PED-AAAA-NNNNNN` y filtros comerciales agrupados.
- Paginacion de 10 pedidos con conservacion de parametros mediante `withQueryString()`.
- Tabla para escritorio y tarjetas para celular con codigo, fecha, total, modalidad, estado y acceso al pedido.
- Detalle privado basico preparado para ampliarse con snapshots y timeline en la Etapa 6.2.
- Respuestas privadas sin cache publico y pedidos ajenos resueltos como `404`.
- 12 pruebas nuevas con 67 aserciones para estados, consultas, aislamiento y responsive renderizado.

Etapa 6.1 validada:
- El usuario aprobo manualmente el estado vacio, datos reales, orden, busqueda, filtros, paginacion, detalle basico, aislamiento y comportamiento responsive.
- La etapa queda cerrada; el detalle historico completo continuara en la Etapa 6.2.

Implementado en la Etapa 6.2:
- Detalle privado completo con items, nombres, imagenes, SKU, presentacion, cantidades, precios unitarios, descuentos y subtotales desde snapshots inmutables.
- Fuente de imagen del snapshot unificada: prioriza archivo local, conserva URLs heredadas y usa el placeholder solo cuando el producto carece de imagen principal.
- Resumen de importes historicos con productos, descuento, envio, valores gravados, exonerados e inafectos, IGV incluido y total.
- Fecha y hora compactas en el listado y fecha absoluta descriptiva en detalle y timeline, siempre en `America/Lima`.
- Enlace al catalogo solo cuando el producto vigente conserva la visibilidad publica completa; productos eliminados u ocultos mantienen su snapshot sin enlace.
- Informacion de contacto, domicilio o recojo y solicitud de boleta o factura con DNI, documento extranjero o RUC enmascarado.
- Reserva visible solo mientras el pedido sigue pendiente y vigente; fechas estimadas visibles solo despues de confirmar el pago.
- Timeline curado para creacion, pago, preparacion, envio, recojo, entrega, cancelacion, vencimiento y reembolso.
- Eventos de reserva, IDs, actores, correos administrativos, razones y metadata interna excluidos de la presentacion.
- Composicion responsive sin acceso horizontal y respuestas privadas sin cache publico.
- 13 pruebas nuevas; regresion especifica de pedidos del cliente con 25 pruebas y 161 aserciones.
- Suite completa: 529 pruebas y 4071 aserciones.

Etapa 6.2 validada:
- El usuario aprobo manualmente el detalle desktop y mobile, snapshots, imagenes, enlaces, importes, modalidades, privacidad fiscal, fechas y timeline.
- La composicion final usa columnas verticales independientes en escritorio y conserva un orden de lectura logico en celular.
- Los estados posteriores al pago quedan cubiertos por pruebas automatizadas hasta que Culqi habilite su recorrido manual real en el Sprint 7.
- La etapa queda cerrada.

Implementado en la Etapa 6.3:
- Capacidad central para decidir si un pedido puede cancelarse, si podra continuar al pago con Culqi y si debe mostrar contacto con soporte.
- Cancelacion visible en listado y detalle solo para pedidos pendientes o con pago fallido que conservan una reserva activa y vigente.
- Modal de confirmacion reutilizable con aviso de liberacion de stock y de que los productos no regresan automaticamente al carrito.
- Endpoint `DELETE` privado por codigo, limitado al propietario, correo verificado y tasa de seis solicitudes por minuto.
- Cancelacion transaccional e idempotente con liberacion exacta de stock, historial auditable y conservacion del carrito actual.
- Reconciliacion legible de carreras: un pago confirmado bloquea la cancelacion y una reserva vencida se expira y libera antes de responder.
- Canal de WhatsApp y correo configurables para pedidos pagados aun no entregados o recogidos, sin ofrecer cancelacion automatica.
- Capacidad de continuar pago preparada en backend sin exponer un boton ficticio antes del Sprint 7.
- Siete pruebas nuevas para capacidades, visibilidad, autorizacion, middleware, doble envio, carrito, pago, vencimiento e inventario.
- Suite completa: 536 pruebas y 4158 aserciones.

Etapa 6.3 validada:
- El usuario aprobo manualmente botones y modal en listado y detalle, mensajes posteriores, responsive y soporte para pedidos pagados.
- La etapa queda cerrada.

Implementado en la Etapa 6.4:
- Presentacion de documentos fiscales disponibles dentro del detalle privado del pedido, ordenados por fecha de emision.
- Tipo, serie, correlativo, fecha y estado visibles, conservando los documentos anulados con una identificacion diferenciada.
- Estado pendiente de emision solo para pedidos pagados sin documentos; los pedidos pendientes de pago no muestran comprobantes ficticios.
- Descarga privada desde el disco `local`, con nombre comprensible, tipo PDF, proteccion `nosniff` y encabezados que impiden cache publico.
- Resolucion conjunta de propietario, codigo de pedido y documento para que pedidos ajenos, IDs inexistentes y pares manipulados respondan `404`.
- Archivos ausentes, rutas vacias y extensiones distintas de PDF rechazados mediante la misma respuesta no reveladora.
- Ocho pruebas HTTP nuevas con 49 aserciones para presentacion, propiedad, documentos relacionados, anulacion, almacenamiento y aislamiento horizontal.

Etapa 6.4 validada tecnicamente:
- La suite focalizada pasa con ocho pruebas y 49 aserciones usando `Storage::fake('local')`.
- La suite completa pasa con 544 pruebas y 4207 aserciones.
- La validacion UX integral permanece diferida hasta disponer de carga administrativa en la Fase 7 y confirmacion real de pago con Culqi; queda registrada como criterio obligatorio de cierre del Sprint 7.
- No se agrego ningun boton administrativo ni mecanismo temporal para marcar pedidos como pagados.

Implementado en la Etapa 6.5:
- Tabla `order_notification_deliveries` con historial persistente, destinatario congelado, tipo, estado, intentos, fechas y ultimo error.
- Unicidad real en base de datos por pedido, evento y correo normalizado; identidad historica protegida contra edicion y eliminacion.
- Correo snapshot obligatorio y copia independiente al correo actual solo cuando sea distinto y este verificado; cuenta eliminada o correo sin verificar no agregan la copia.
- Trabajo unico `SendOrderTransactionalEmail` posterior al commit, con tres intentos totales y reintentos aproximados al minuto y a los cinco minutos.
- Estados auditables `queued`, `sending`, `sent` y `failed`, sin permitir que un fallo SMTP revierta el pedido, la reserva ni el inventario.
- Plantilla transaccional diferenciada para creacion pendiente de pago, cancelacion y vencimiento, con marca configurable y snapshots de items e importes.
- Integracion en las transiciones reales de confirmacion, cancelacion y vencimiento, incluidos reconciliacion por acceso y scheduler.
- Pruebas automatizadas de destinatarios, deduplicacion, congelamiento, cuenta eliminada, correo sin verificar, unicidad, inmutabilidad, cola, contenido, reintentos, limite de intentos, rollback e idempotencia de transiciones.
- Migracion aplicada en la base local y suite completa aprobada con 554 pruebas y 4273 aserciones.

Etapa 6.5 validada:
- El usuario comprobo el procesamiento mediante worker y el envio SMTP real.
- El correo de creacion muestra precio unitario, subtotal de productos, descuento cuando corresponde, costo de entrega y total.
- La etapa queda cerrada; su mejora visual con imagenes CID se traslada expresamente a la Etapa 6.6.

Implementado en la Etapa 6.6:
- Etapa 6.6: integracion, seguridad, responsive, pruebas y cierre tecnico de la Fase 6.
- Plantilla Blade responsive y version de texto plano para los correos transaccionales.
- Filas visuales de productos en el correo de creacion con miniatura CID `96 x 96 px`, nombre, presentacion, cantidad y desglose historico.
- Precio simple para una unidad; cantidad, precio unitario y subtotal de linea cuando haya mas de una.
- Miniaturas JPEG optimizadas con maximo de 25 KB, deduplicadas por mensaje, presupuesto total de 300 KB y limitadas a la imagen principal.
- Importacion controlada de snapshots HTTPS heredados desde hosts configurados, sin redirecciones, con timeout, validacion MIME y cache privada.
- Placeholder local para hosts no autorizados y snapshots ausentes, inseguros o ilegibles.
- Filas informativas sin enlaces ni JavaScript, independientes de URLs remotas y de `APP_URL`.
- Correo MIME real probado con CID inline, JPEG, dimensiones, peso, deduplicacion, texto plano y ausencia de `data:` o enlaces de producto.
- Auditoria focalizada de `Account`, `Checkout` y `Orders` aprobada con 200 pruebas y 2304 aserciones.
- Cache de Blade, build de produccion, Pint, migraciones y revision de whitespace aprobados.
- Suite completa aprobada con 558 pruebas y 4323 aserciones.

Etapa 6.6 validada:
- El usuario aprobo el formato responsive del correo, el desglose de importes y las miniaturas CID.
- Se confirmo con datos reales la imagen principal de snapshots locales y URLs heredadas permitidas, conservando el placeholder cuando el producto no tiene imagen.
- La Etapa 6.6 y la Fase 6 quedan cerradas.

Politica cerrada para la Etapa 6.5:
- Pedido creado, cancelacion real y vencimiento real generan eventos distintos; reintentos idempotentes, doble clic y nuevas ejecuciones del scheduler no crean otra comunicacion.
- `order.customer_email` recibe siempre la comunicacion como snapshot verificado al crear el pedido.
- Un correo actual verificado y diferente recibe una copia independiente; no se usa `CC` ni `BCC`, no se copia a correos sin verificar y las direcciones equivalentes se deduplican.
- Una cuenta eliminada conserva solo el destinatario snapshot y `fiscal_email` queda reservado para comprobantes.
- Los destinatarios se congelan al producirse el evento y la unicidad se garantiza por pedido, evento y correo normalizado.
- Cada destinatario tiene un registro auditable y hasta tres intentos totales de Laravel por SMTP: inicial y dos reintentos aproximados al minuto y a los cinco minutos.
- La aceptacion SMTP de Brevo completa el trabajo de Laravel; los intentos de entrega diferida del proveedor no representan nuevos eventos del pedido.
- Los trabajos se despachan despues del commit y sus fallos nunca revierten pedido, reserva ni inventario.
- Esta etapa no envia copias administrativas, confirmaciones de pago ni adjuntos fiscales.
- Remitente, nombre comercial, enlaces y contenido usan configuracion vigente y snapshots historicos segun corresponda.

Pendiente:
- Completar la validacion manual integral de la Etapa 6.4 como criterio de cierre del Sprint 7, cuando sus dependencias funcionales esten disponibles.

## Fase 7: Admin de pedidos y comprobantes manuales

Estado: En progreso (Etapas 7.1 y 7.2 completadas)

Reglas cerradas:
- Acciones contextuales en lugar de edicion libre de estados.
- `Iniciar preparacion` coordinara pedido y entrega; confirmar entrega o recojo completara el pedido atomicamente.
- Flujos separados para domicilio y recojo, con motivo y auditoria en toda accion sensible.
- Los estados de pago dependeran de Culqi; no se agregaran botones administrativos ni pagos simulados.
- La cancelacion pagada usara `refund_pending` hasta recibir la confirmacion real del reembolso.
- Intentos de entrega auditados; solo los atribuibles al cliente consumiran el limite configurable.
- Un ciclo agotado exigira nuevo pago de envio y, al agotar los ciclos configurados, el caso pasara a seguimiento manual.
- El plazo de recojo no cancelara pedidos automaticamente y generara alertas administrativas.
- Avisos de recojo al quedar listo, a mitad del plazo, 48 horas antes y al vencer, deduplicados y cancelados si el pedido se recoge.
- Correos operativos para envio, disponibilidad de recojo y finalizacion, sin correo al iniciar preparacion.
- Correo snapshot y copia al correo actual verificado para eventos operativos; correo fiscal snapshot como unico destino del comprobante.
- Identidad fiscal inmutable; correccion versionada de archivos, anulacion confirmada, notas relacionadas y estados legal y de correo separados.
- Todos los administradores podran operar esta fase; los permisos por capacidad quedan en el Sprint 9.

Planificado:
- Etapa 7.1: bandeja, filtros y detalle administrativo de solo lectura. Completada.
- Etapa 7.2: acciones contextuales, transiciones atomicas, auditoria y `refund_pending`. Completada.
- Etapa 7.3: intentos y ciclos de entrega, bloqueo por nuevo pago y seguimiento de recojo.
- Etapa 7.4: correos operativos, recordatorios, scheduler e idempotencia.
- Etapa 7.5: registro y descarga privada de boleta o factura principal.
- Etapa 7.6: correccion versionada de archivos, notas relacionadas y anulacion.
- Etapa 7.7: envio fiscal auditado, integracion y cierre de la fase.

Completado en la Etapa 7.1:
- `Admin\OrderController` y `ListAdminOrdersRequest` reemplazan las rutas y vistas estaticas.
- Busqueda normalizada sobre codigo, cliente, correo, telefono, documento y razon social.
- Filtros validados por enums para pedido, pago, entrega y modalidad, junto con rango inclusivo de fecha.
- Listado real ordenado por fecha e ID descendentes, paginado de 15 en 15 y con query string persistente.
- Navegacion listado-detalle-listado que conserva filtros y pagina sin aceptar una URL de retorno arbitraria.
- Presentador administrativo reutilizable para importes, estados, snapshots, reservas, historial, documentos y comunicaciones.
- Detalle estrictamente consultivo, sin controles para transiciones, cancelacion, carga o envio fiscal.
- Lectura contextual de estados terminales: entrega `No aplica` si el pedido vencio antes de despacharse y pago `No realizado` si se cancelo antes de cobrar.
- Historial tecnico con leyenda, colores e iconos diferenciados para pedido, pago, entrega y reserva.
- Resumen unico del estado de las reservas, con cantidades totales y detalle por producto cerrado por defecto.
- Agrupacion de eventos de reserva por lote sin perder reservas, movimientos, cantidades ni metadata individuales.
- Vista responsive con tabla en escritorio, filas compactas en celular y columnas de detalle independientes.
- Dashboard conectado a los cuatro pedidos mas recientes, sin tocar las tarjetas estaticas excluidas del alcance.
- Respuestas privadas, relaciones cargadas anticipadamente y prueba explicita contra lazy loading.
- Doce pruebas HTTP nuevas, 104 aserciones focalizadas y suite completa aprobada con 571 pruebas y 4431 aserciones.
- Build de produccion, cache de Blade, rutas, formato y revision de whitespace aprobados.
- Validacion manual aprobada en escritorio y celular, incluyendo estados terminales, historial por flujos y reservas agrupadas.

Completado en la Etapa 7.2:
- `refund_pending` incorporado al enum, esquema, filtros, badges, estado comercial y linea de tiempo del cliente.
- Flujo de reembolso restringido a `paid -> refund_pending -> refunded`; el panel no permite cambiar pagos manualmente.
- Estado comercial `Pago confirmado` mientras el pedido pagado aun espera que un administrador inicie la preparacion.
- `AdminOrderOperationService` coordina acciones bajo bloqueo pesimista, transaccion, validacion contextual e idempotencia.
- Domicilio opera `pendiente -> preparando -> enviado -> entregado`; recojo opera `pendiente -> preparando -> listo -> recogido`.
- Iniciar preparacion y finalizar entrega o recojo actualizan pedido y entrega atomicamente.
- Cancelacion no pagada libera reservas activas; cancelacion pagada repone el stock consumido y deja el pago en reembolso pendiente.
- Las cancelaciones administrativas pagadas y no pagadas registran y encolan un correo transaccional idempotente despues del commit.
- Detalle del cliente y correo muestran el mismo motivo historico, distinguen cancelacion propia o de la tienda, informan el reembolso y ocultan la identidad administrativa.
- Las plantillas aceptan la ausencia de contexto de cancelacion y la regresion renderiza HTML y texto reales para `Pedido creado`.
- Cancelacion y vencimiento sustituyen comunicaciones de creacion pendientes con estado auditable `Omitido`; un envio ya iniciado se resuelve antes de liberar la comunicacion terminal y un intento abandonado vence a los dos minutos.
- Los trabajos omitidos no consumen intentos SMTP ni pueden enviar despues un correo de creacion obsoleto.
- Limitacion aceptada: si Brevo ya acepto la creacion y la cancelacion con pocos segundos de diferencia, puede entregarlas en otro orden; el estado visible en la cuenta sigue siendo la fuente autoritativa.
- Estado pendiente sincronizado cada 10 segundos y al volver a la pestana mediante endpoint privado sin cache; cancelacion o vencimiento detienen el contador y reemplazan las acciones.
- La respuesta terminal incluye solo datos visibles para el cliente y la URL pendiente ya cerrada redirige al detalle del pedido.
- Cada reposicion queda enlazada a un movimiento de inventario unico, fecha y motivo, sin reescribir el estado historico consumido.
- Seis endpoints administrativos explicitos, protegidos por autenticacion y rol, con retorno que conserva filtros y pagina.
- Panel responsive con solo las acciones permitidas, confirmaciones, motivo obligatorio, advertencia para pagos y bloqueo del boton durante el envio.
- Historial conserva actor, correo, fecha, motivo, valores anterior/nuevo, accion y una referencia compartida para la operacion compuesta.
- Pruebas focalizadas de dominio y HTTP cubren flujos, permisos, restricciones, rollback, doble ejecucion y reposicion.
- Prueba multiproceso con cuatro cancelaciones simultaneas confirma una sola reposicion, un solo movimiento y una sola transicion de reembolso.
- Entorno Artisan de pruebas aislado mediante `.env.testing.example`, con SQLite en memoria, idioma y zona horaria equivalentes al desarrollo.
- Suite completa aprobada con 598 pruebas y 4642 aserciones.

Pendiente:
- Implementar y validar las Etapas 7.3 a 7.7 en orden.

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

- Ninguno para continuar con la Fase 7.
- La validacion manual integral de sus comprobantes queda transferida como criterio obligatorio de cierre del Sprint 7.

## Resultado esperado

- Al cerrar el sprint, el sistema podra crear y administrar pedidos pendientes de pago con stock reservado, y Sprint 7 podra integrar Culqi sin modificar las reglas centrales de checkout, inventario o gestion fiscal manual.
