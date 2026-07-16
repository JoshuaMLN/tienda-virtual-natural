# Sprint 6 - Checkout y Pedidos

Fecha: 2026-07-16

Estado: En progreso

## Objetivo

Convertir el carrito persistente de un cliente autenticado y verificado en un pedido transaccional, auditable y preparado para Culqi, con entrega configurable en Lima y Callao, reservas de inventario, datos fiscales completos y administracion real de pedidos y comprobantes.

## Punto de partida

- Sprint 5 esta cerrado con autenticacion local y Google, correo verificado, perfil, direcciones estructuradas y carrito persistente.
- El checkout ya exige `auth` y `verified`, pero sus vistas todavia no crean pedidos reales.
- El carrito recalcula precios y disponibilidad desde productos vigentes.
- El inventario dispone de movimientos auditables y bloqueo para cantidades insuficientes.
- Existe una tabla `settings` y una pantalla de configuracion de productos que sirven como patron para nuevas opciones globales.
- Las rutas `/admin` todavia no tienen autenticacion ni autorizacion.
- Las vistas de pedidos de cliente y administrador contienen contenido estatico.
- Culqi y sus webhooks corresponden al Sprint 7.

## Decisiones de arquitectura y negocio

- Solo un cliente autenticado y con correo verificado puede entrar al checkout y crear pedidos.
- No existe checkout como invitado. El carrito invitado se conserva y se fusiona al iniciar sesion segun las reglas del Sprint 5.
- Entrar al checkout no crea un pedido ni reserva inventario.
- El pedido se crea al confirmar la compra, despues de revalidar carrito, direccion, entrega, datos fiscales, precios, visibilidad, stock y totales.
- Se compra el carrito completo. No se agregan checkboxes para elegir items parciales.
- La operacion final usa transaccion, bloqueos de filas e idempotencia para evitar sobreventa y pedidos duplicados.
- Si algo cambia antes de confirmar, no se crea el pedido y el cliente permanece en el checkout con todos sus campos intactos.
- Los cambios se muestran en un modal bloqueante con valores anteriores y actuales. Aceptarlos dispara una nueva validacion automaticamente.
- Si vuelve a existir otro cambio concurrente, se presenta un nuevo resumen sin obligar a rellenar el formulario.
- Una vez creado el pedido, sus precios, impuestos, envio, direccion y datos fiscales quedan congelados como snapshots historicos.
- El codigo interno del pedido usa el formato neutral `PED-AAAA-NNNNNN`, con correlativo anual seguro ante concurrencia e independiente del ID, la marca y la numeracion fiscal.
- Los estados de pedido, pago, entrega y reserva se modelan por separado.
- Estados iniciales de pedido: `pending_payment`, `processing`, `completed`, `cancelled`, `expired`.
- Estados iniciales de pago: `pending`, `paid`, `failed`, `expired`, `refunded`.
- Estados iniciales de entrega: `pending`, `preparing`, `shipped`, `ready_for_pickup`, `delivered`, `picked_up`, `cancelled`.
- Estados iniciales de reserva: `active`, `consumed`, `released`, `expired`.
- Un intento de pago rechazado no equivale automaticamente a un pedido vencido. Mientras la reserva siga activa, Sprint 7 podra reintentar el pago sobre el mismo pedido.
- El vencimiento de la reserva marca el pedido y pago como `expired`, libera stock y exige crear un pedido nuevo.
- La reserva para pagos inmediatos sera configurable y comenzara con 15 minutos.
- Reservar stock reduce la disponibilidad vendible de inmediato y deja trazabilidad. Confirmar el pago consume la reserva sin un segundo descuento.
- Cancelar o vencer un pedido libera la reserva exactamente una vez.
- El carrito se limpia solo despues de confirmar la transaccion que crea el pedido.
- La entrega cubre Lima Metropolitana y Callao mediante distritos configurables, cada uno con estado y tarifa.
- El envio gratis usa un umbral global calculado sobre el subtotal de productos despues de descuentos y antes del envio. El valor `0` lo deshabilita.
- El recojo es gratuito y solo aparece cuando el administrador ha configurado una direccion de recojo completa.
- El plazo general de entrega sera configurable en dias habiles, inicialmente de 1 a 2. La promesa mostrada se congela al crear el pedido y su computo comienza al confirmarse el pago.
- No se calcula una fecha exacta ni se administra un calendario de feriados en este sprint.
- Si una ubicacion esta fuera de cobertura, se muestra el WhatsApp configurado para coordinar el envio a provincia.
- Una direccion nueva creada desde checkout se guarda en la cuenta. Al alcanzar el limite de 10, el cliente debe usar una direccion existente o administrar sus direcciones antes de continuar.
- Los datos de entrega y los datos fiscales son independientes.
- Para boleta se solicitara siempre tipo y numero de documento, nombres y apellidos y correo fiscal, incluso cuando el monto no supere S/ 700.
- Para factura se solicitara RUC, razon social, domicilio fiscal y correo fiscal. No se solicitara DNI adicional.
- Los datos fiscales elegidos se guardan como snapshot y no modifican automaticamente el perfil del cliente.
- El precio ingresado por el administrador y mostrado al cliente es el precio final con IGV incluido.
- Los productos se consideran gravados con IGV de 18 % por defecto, pero el dominio queda preparado para afectaciones exoneradas o inafectas.
- Los importes historicos del pedido se almacenan en centimos; el valor de venta y el IGV se calculan internamente sin reemplazar el precio comercial mostrado al cliente.
- Los descuentos futuros se distribuiran proporcionalmente entre los items, con redondeo determinista y sin mezclar el descuento de productos con el envio.
- La emision inicial del comprobante se realiza manualmente en SUNAT SEE-SOL.
- SUNAT asigna la serie y el correlativo. La tienda solo registra exactamente los valores del comprobante ya emitido.
- La tienda no genera PDFs fiscales. El administrador adjunta la representacion PDF oficial descargada de SUNAT.
- El PDF fiscal se guarda en almacenamiento privado y solo puede descargarlo el cliente propietario o un administrador.
- Solo un pedido pagado puede recibir su comprobante fiscal.
- Hasta integrar Culqi, este flujo se validara con pedidos pagados de prueba; en operacion real se habilitara cuando Sprint 7 confirme el pago desde backend.
- El tipo de comprobante registrado debe coincidir con el solicitado en checkout.
- La combinacion tipo, serie y correlativo no puede repetirse.
- El comprobante no se elimina silenciosamente. Puede registrarse como anulado y futuras notas fiscales se modelaran como documentos relacionados.
- El envio por correo usa el correo fiscal congelado en el pedido y registra fecha, destinatario, administrador y resultado.
- La automatizacion SUNAT mediante PSE, OSE o SEE del contribuyente queda fuera de este sprint.
- La proteccion basica de todo `/admin` es prerrequisito del modulo de pedidos. Las policies granulares por capacidad quedan para Sprint 9.

## Alcance funcional

- Acceso administrativo autenticado y protegido por rol.
- Configuracion operativa, de entrega, recojo y envio gratis.
- Tarifas por distrito de Lima y Callao.
- Dominio de pedidos, items, estados, historial, reservas y documentos fiscales.
- Checkout real con direcciones, entrega, facturacion y resumen.
- Revalidacion interactiva de cambios antes de crear el pedido.
- Creacion idempotente y reserva auditable de stock.
- Vencimiento programado de pedidos y liberacion de reservas.
- Historial y detalle de pedidos del cliente.
- Listado y detalle real de pedidos en admin.
- Registro privado y envio manual de comprobantes emitidos en SUNAT.
- Pruebas de dominio, HTTP, autorizacion, concurrencia, archivos y correo.

## Fuera de alcance

- Cobro real, tokenizacion, cargos y webhooks Culqi; corresponden al Sprint 7.
- Marcar un pedido como pagado desde un boton administrativo sin evidencia del proveedor de pago.
- Metodos de pago asincronos hasta definir una politica de reserva compatible con su vencimiento.
- Emision automatica de boletas, facturas, notas de credito o notas de debito ante SUNAT.
- Generacion propia de un PDF con apariencia de comprobante fiscal.
- Envio a provincias, calculo de tarifas nacionales o integracion con couriers.
- Calendario de feriados y fecha exacta prometida de entrega.
- Cupones, promociones y descuentos administrables; corresponden al Sprint 8.
- Compra parcial del carrito.
- Checkout para invitados.
- Policies administrativas granulares por permiso; corresponden al Sprint 9.

## Fase 1: Seguridad administrativa base

Objetivo: cerrar el acceso publico actual de `/admin` antes de conectar pedidos, datos fiscales y documentos privados.

Tareas:
- Incorporar un rol administrativo explicito sin duplicar usuarios ni crear credenciales en codigo.
- Crear login y logout administrativos usando las protecciones nativas de Laravel.
- Agregar middleware de administrador a todo el grupo `/admin`.
- Impedir que un cliente autenticado acceda al panel y que un administrador inicie sesion desde un flujo ambiguo.
- Regenerar sesion al autenticar, invalidarla al salir y aplicar rate limiting al login.
- Crear `admin:create` como comando interactivo para registrar el primer administrador sin guardar contrasenas en argumentos ni seeders.
- Mantener separados los accesos: login y Google para clientes, login administrativo para admins.
- Exigir rol de cliente en cuenta y checkout, y bloquear carrito para sesiones administrativas.
- Mantener el login admin sin opcion de sesion persistente y con recuperacion de contrasena propia.
- Adaptar navbar y sidebar admin para mostrar el usuario real y cerrar sesion con confirmacion.
- Mantener para Sprint 9 las policies por accion o capacidad.
- Crear pruebas de invitado, cliente, administrador, login, logout y proteccion de todas las rutas admin.

Criterio de salida:
- Ninguna ruta `/admin` es accesible sin una cuenta administrativa autenticada.

## Fase 2: Configuracion operativa y entrega

Objetivo: centralizar los datos comerciales y construir las reglas configurables de despacho antes del formulario de checkout.

Tareas:
- Reutilizar `settings` mediante accesores tipados para:
  - WhatsApp de atencion
  - correo de contacto
  - horario de atencion
  - umbral de envio gratis
  - minutos de reserva de stock
  - dias habiles minimos y maximos de entrega
  - direccion completa de recojo
- Crear configuracion por distrito para los 43 distritos de Lima y 7 del Callao:
  - provincia
  - distrito
  - UBIGEO canonico
  - tarifa
  - activo/inactivo
- Cargar inicialmente los 50 distritos activos con tarifas referenciales entre S/ 8.00 y S/ 25.00 calculadas desde San Isidro.
- Mantener la carga idempotente para no sobrescribir tarifas o estados editados posteriormente por un administrador.
- Validar montos no negativos, rango de entrega coherente y direccion de recojo completa.
- Considerar `0` como envio gratis deshabilitado.
- Ocultar recojo cuando falte su direccion y tratarlo como gratuito cuando este habilitado.
- Aplicar el umbral sobre subtotal de productos despues de descuentos y antes del envio.
- Crear una pantalla admin compacta para configuracion general y tarifas.
- Hacer que navbar, footer, contacto y avisos de envio consuman una unica fuente de configuracion.
- Mostrar contacto por WhatsApp para destinos fuera de Lima Metropolitana y Callao.
- Crear pruebas para configuracion, cobertura, tarifas, envio gratis y recojo.

Criterio de salida:
- El sistema resuelve de forma centralizada si un distrito tiene cobertura, cuanto cuesta y que opciones operativas puede mostrar.

## Fase 3: Dominio de pedidos, reservas y documentos fiscales

Objetivo: crear el modelo historico y las invariantes sobre las que trabajaran checkout, cliente y admin.

Tareas:
- Crear modelos, migraciones, factories y relaciones para:
  - `Order`
  - `OrderItem`
  - `StockReservation`
  - historial de estados del pedido
  - `FiscalDocument`
  - historial de envios de documentos fiscales
- Generar el codigo interno unico `PED-AAAA-NNNNNN` con correlativo anual protegido contra concurrencia e independiente del ID y del comprobante fiscal.
- Guardar en `Order`:
  - cliente y correo de cuenta
  - estados separados de pedido, pago y entrega
  - modalidad de entrega
  - direccion de entrega o recojo como snapshot
  - datos fiscales como snapshot
  - subtotal, descuentos, envio, valor sin IGV, IGV y total en centimos
  - plazo de entrega mostrado al crear el pedido y fecha de inicio del computo al confirmar el pago
- Guardar en `OrderItem` referencias opcionales y snapshots de SKU, nombre, imagen, unidad, cantidad, afectacion tributaria, tasa, precio final con IGV incluido, valor sin IGV, IGV, descuento y total.
- Mantener como precio comercial el importe final ingresado por el administrador; por ejemplo, un producto publicado a S/ 190.00 conserva ese total y desglosa internamente su valor de venta e IGV.
- Ajustar el formulario administrativo de productos para etiquetar el campo como `Precio de venta (IGV incluido)` y mostrar su desglose tributario como ayuda, sin sustituir el precio final.
- Modelar la afectacion tributaria del producto con `Gravado 18 %` como valor predeterminado y soporte futuro para productos exonerados o inafectos.
- Almacenar dinero en centimos y aplicar una politica unica de redondeo para evitar diferencias entre items, resumen y comprobante.
- Preparar descuentos en cero durante este sprint y su futura distribucion proporcional por item, asignando cualquier residuo de redondeo de forma determinista.
- Permitir que el historial siga siendo legible aunque cambien o se eliminen productos, direcciones o datos del cliente; las referencias historicas seran opcionales y no usaran eliminacion en cascada.
- Modelar un historial inmutable que identifique el dominio modificado, estado anterior, estado nuevo, actor opcional, motivo, metadatos y fecha.
- Modelar reservas por item con cantidad, estado, expiracion y marcas de consumo o liberacion exacta, diferenciando cancelacion y vencimiento.
- Coordinar la confirmacion de pago y el consumo de todas sus reservas en una sola transaccion, impidiendo liberar stock de un pedido ya pagado.
- Guardar la solicitud fiscal como snapshot del pedido sin crear todavia un `FiscalDocument`.
- Modelar documentos fiscales como relacion independiente de varios documentos por pedido, creada solo despues del pago y preparada para boleta, factura y futuras notas relacionadas.
- Guardar serie, correlativo, fecha, estado, rutas privadas y usuario registrador del comprobante.
- Registrar cada intento de envio del comprobante con destinatario, administrador, fecha, resultado y detalle de error, sin duplicar el documento fiscal.
- Agregar unicidad para tipo, serie y correlativo.
- Implementar enums o value objects para estados de pedido, pago, entrega, reserva, afectacion tributaria y tipo de documento fiscal.
- Crear pruebas de correlativo concurrente, relaciones, snapshots, calculo de IGV, redondeo, transiciones validas, referencias opcionales y restricciones de base de datos.

Criterio de salida:
- El dominio puede representar un pedido completo y auditable, con codigo neutral, importes tributarios reproducibles y estados independientes, sin depender de datos mutables del catalogo o del perfil.

## Fase 4: Checkout real y experiencia del formulario

Objetivo: reemplazar la maqueta por un checkout protegido, responsive y conectado a direcciones, entrega y facturacion reales.

Tareas:
- Mantener `auth` y `verified` en todas las rutas de checkout.
- Mostrar el carrito completo y bloquear el avance cuando este vacio.
- Permitir elegir una direccion guardada o agregar una nueva que se persista en la cuenta.
- Respetar el limite de 10 direcciones y ofrecer acceso directo a su administracion.
- Resolver provincia, distrito y UBIGEO desde el catalogo canonico.
- Permitir entrega a domicilio o recojo cuando este habilitado.
- Calcular tarifa por distrito y aplicar envio gratis cuando corresponda.
- Mostrar en la parte superior que la entrega esta disponible solo en Lima Metropolitana y Callao.
- Mostrar el plazo configurable de 1 a 2 dias habiles desde la confirmacion del pago.
- Permitir elegir `Boleta` o `Factura` mediante un control claro.
- Para boleta solicitar tipo de documento, numero, nombres y apellidos y correo fiscal.
- Para factura solicitar RUC, razon social, domicilio fiscal y correo fiscal.
- Validar campos condicionales en backend y no aceptar datos fiscales ocultos o incompatibles.
- Separar visualmente datos de entrega, datos fiscales y resumen del pedido.
- Mantener todos los valores ingresados ante errores de validacion o conflictos de carrito.
- No crear pedidos desde `GET /checkout`.
- Crear pruebas HTTP y de vista para cada modalidad, comprobante y estado de validacion.

Criterio de salida:
- El cliente puede completar y revisar todos los datos necesarios sin que visitar el checkout modifique inventario ni cree pedidos.

## Fase 5: Revalidacion, creacion idempotente y reserva

Objetivo: crear el pedido exactamente una vez con datos vigentes y sin sobreventa bajo concurrencia.

Tareas:
- Crear un servicio de revalidacion que compare el resumen visto por el cliente con el estado vigente.
- Detectar cambios de:
  - precio
  - cantidad disponible
  - producto agotado u oculto
  - tarifa o cobertura
  - envio gratis
  - subtotal, IGV y total
- Devolver un conflicto estructurado con valores anteriores y nuevos sin redirigir al carrito.
- Mostrar un modal bloqueante con `Aceptar cambios y continuar` y `Volver al carrito`.
- Preservar direccion, entrega y datos fiscales mientras se resuelve el conflicto.
- Al aceptar, enviar una revision del resumen y volver a validar automaticamente.
- Repetir el aviso si existe otro cambio concurrente antes de la aceptacion final.
- En la confirmacion vigente, bloquear productos y crear dentro de una transaccion:
  - pedido e items
  - snapshots
  - historial inicial
  - reservas y movimientos de inventario
- Usar una clave de idempotencia por intento de checkout para neutralizar doble click, reintentos HTTP y respuestas perdidas.
- Limpiar el carrito solo despues del commit exitoso.
- Programar un comando para vencer reservas y liberar stock exactamente una vez.
- Mantener precio y stock reservados durante el plazo configurado.
- Redirigir el pedido creado a una pantalla `pending_payment` preparada para Culqi.
- Crear pruebas de concurrencia, idempotencia, rollback, expiracion, liberacion y limpieza de carrito.

Criterio de salida:
- Una confirmacion crea como maximo un pedido, reserva inventario sin sobreventa y nunca obliga a rellenar checkout por cambios concurrentes.

## Fase 6: Pedidos del cliente

Objetivo: permitir que el cliente consulte y gestione sus pedidos reales sin acceder a pedidos ajenos.

Tareas:
- Reemplazar la maqueta de `Mis pedidos` por listado real paginado y filtrable por estado.
- Crear detalle con items, totales, entrega, datos fiscales, estado y linea de tiempo.
- Mostrar claramente pedidos pendientes de pago, vencidos, procesando, enviados, entregados y cancelados.
- Permitir cancelar un pedido `pending_payment` y liberar su reserva una sola vez.
- Preparar la accion de reintento de pago para Sprint 7 mientras la reserva siga activa.
- Mostrar el plazo de 1 a 2 dias habiles solo desde la confirmacion de pago.
- Permitir descargar el comprobante desde almacenamiento privado cuando exista.
- Notificar por correo la creacion y cancelacion del pedido sin adjuntar un comprobante inexistente.
- Impedir acceso horizontal mediante consultas acotadas al usuario o policies.
- Crear pruebas de historial, detalle, cancelacion, descarga y aislamiento de propietario.

Criterio de salida:
- Cada cliente puede seguir sus pedidos y documentos reales sin ver ni modificar informacion de otra cuenta.

## Fase 7: Admin de pedidos y comprobantes manuales

Objetivo: conectar el panel administrativo a pedidos reales y operar el flujo fiscal manual inicial.

Tareas:
- Reemplazar pedidos estaticos por listado paginado con busqueda y filtros de pedido, pago, entrega y fecha.
- Crear detalle administrativo con cliente, items, snapshots, totales, direccion, datos fiscales, reserva e historial.
- Permitir transiciones administrativas validas, incluyendo procesamiento, envio, entrega y cancelacion.
- Evitar acciones incompatibles con el estado actual y registrar quien realizo cada cambio.
- Mostrar el tipo de comprobante solicitado sin permitir cambiarlo arbitrariamente.
- Habilitar `Registrar boleta` o `Registrar factura` solo para pedidos pagados.
- Validar la funcionalidad con factories de pedidos pagados mientras Sprint 7 todavia no provea pagos reales.
- Solicitar serie, correlativo, fecha de emision y PDF oficial; permitir XML opcional.
- Validar PDF, tamano, almacenamiento privado y unicidad fiscal.
- No calcular ni sugerir el siguiente correlativo. Debe copiarse el asignado por SUNAT SEE-SOL.
- Mostrar identificador fiscal completo y estado `emitido`, `enviado` o `anulado`.
- Habilitar `Enviar comprobante` solo cuando exista PDF.
- Enviar por cola al correo fiscal del snapshot y registrar exito, error, fecha, destinatario y administrador.
- Permitir reenvio sin duplicar el documento fiscal.
- Ofrecer descarga privada al administrador y al cliente propietario.
- No eliminar silenciosamente comprobantes emitidos.
- Crear pruebas con `Storage::fake()`, `Mail::fake()` o `Notification::fake()` y autorizacion.

Criterio de salida:
- El administrador opera pedidos reales y puede registrar y entregar de forma segura un comprobante previamente emitido en SUNAT.

## Fase 8: Integracion, pruebas y cierre

Objetivo: validar el recorrido completo y dejar contratos estables para la integracion Culqi del Sprint 7.

Tareas:
- Cubrir con pruebas automatizadas:
  - seguridad de todo `/admin`
  - configuracion y tarifas por distrito
  - checkout autenticado y verificado
  - boleta y factura con campos condicionales
  - snapshots historicos
  - cambios de precio, stock y entrega
  - idempotencia y concurrencia
  - reserva, consumo preparado, cancelacion y expiracion
  - aislamiento de pedidos por cliente
  - transiciones administrativas
  - PDF privado y envio o reenvio por correo
- Verificar que no se crea un pedido al abrir checkout.
- Verificar que un conflicto mantiene los datos y no devuelve al cliente al inicio del flujo.
- Verificar que doble click y reintentos producen un solo pedido.
- Verificar que el vencimiento libera stock una sola vez y usa estado `expired`.
- Verificar que no se puede registrar comprobante en un pedido no pagado.
- Verificar manualmente desktop y mobile para checkout, pedidos y admin.
- Ejecutar migraciones, rutas, cache de vistas, Pint, build frontend, suite completa y Graphify.
- Documentar scheduler, cola de correos y configuraciones necesarias para produccion.

Criterio de salida:
- Sprint 7 puede iniciar un cobro sobre un pedido vigente, confirmar su reserva y actualizar estados sin redisenar checkout ni pedidos.

## Rutas previstas

- `admin.login`
- `admin.authenticate`
- `admin.logout`
- `admin.settings.operational.edit`
- `admin.settings.operational.update`
- `admin.delivery-zones.index`
- `admin.delivery-zones.update`
- `checkout.index`
- `checkout.revalidate`
- `checkout.orders.store`
- `account.orders.index`
- `account.orders.show`
- `account.orders.cancel`
- `account.orders.fiscal-documents.download`
- `admin.orders.index`
- `admin.orders.show`
- `admin.orders.status.update`
- `admin.orders.cancel`
- `admin.orders.fiscal-documents.store`
- `admin.orders.fiscal-documents.download`
- `admin.orders.fiscal-documents.send`

## Criterios de cierre del Sprint 6

- Todas las rutas administrativas exigen una cuenta admin real.
- Tarifas, recojo, envio gratis, contacto y plazo se administran desde una fuente central.
- Visitar checkout no crea pedidos ni reserva stock.
- La confirmacion revalida todos los importes y maneja cambios sin perder datos.
- Un pedido se crea una sola vez y conserva snapshots completos.
- El inventario reservado no puede venderse a otro pedido.
- Cancelar o vencer libera stock exactamente una vez.
- El cliente consulta exclusivamente sus pedidos.
- El administrador trabaja con pedidos reales y estados validos.
- Boleta y factura solicitan y conservan sus datos fiscales correspondientes.
- El sistema registra la numeracion asignada por SUNAT y nunca inventa correlativos.
- Los PDFs fiscales son privados y el envio por correo queda auditado.
- La suite completa y las validaciones manuales pasan.
- Los contratos necesarios para Culqi quedan documentados y probados.

## Orden recomendado de commits

1. `feat(admin): proteger el acceso administrativo`
2. `feat(settings): configurar operacion y tarifas de entrega`
3. `feat(orders): crear dominio de pedidos y reservas`
4. `feat(checkout): conectar formulario y datos fiscales`
5. `feat(orders): crear pedidos idempotentes y reservar stock`
6. `feat(account): mostrar pedidos reales del cliente`
7. `feat(admin): gestionar pedidos y comprobantes manuales`
8. `test(sprint-6): validar checkout pedidos y comprobantes`
9. `docs(sprint-6): cerrar checkout y pedidos`
