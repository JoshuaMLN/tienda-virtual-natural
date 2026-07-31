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
- Estados de pago objetivo: `pending`, `paid`, `failed`, `expired`, `refund_pending`, `refunded`.
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
- La direccion del cliente es obligatoria solo para entrega a domicilio. El recojo requiere los datos de contacto, pero usa exclusivamente la direccion configurada por la tienda.
- La cobertura y tarifa se obtienen del UBIGEO canonico de la direccion seleccionada; el checkout no solicita un segundo distrito independiente.
- Una direccion guardada en un distrito inactivo se conserva, pero no habilita entrega a domicilio. El cliente debe elegir otra direccion, seleccionar recojo si esta disponible o contactar por WhatsApp.
- La entrega a domicilio se selecciona inicialmente solo cuando la direccion elegida tiene cobertura. Si no la tiene, el sistema no cambia silenciosamente a recojo ni selecciona otra modalidad.
- Las tarifas de distrito son importes finales con IGV incluido. Una tarifa `0` en un distrito activo representa entrega gratuita, aun cuando el umbral global de envio gratis este deshabilitado.
- El plazo general de entrega sera configurable, inicialmente de 1 a 2 dias de atencion, y actuara como respaldo para los distritos sin un plazo propio.
- Cada distrito puede sobrescribir opcionalmente el plazo minimo y maximo de entrega sin duplicar la configuracion global.
- El recojo usa un plazo de preparacion propio e independiente. Una vez notificado como listo, se aplica el plazo de recojo configurable, inicialmente de 14 dias calendario.
- El checkout convierte los plazos en fechas estimadas concretas desde el siguiente dia de atencion; el dia actual nunca cuenta como dia uno.
- Los dias de atencion se resuelven desde horarios estructurados de lunes a viernes, sabado y domingo. Un fin de semana sin apertura y cierre completos no cuenta.
- Las fechas de cierre extraordinario se administran en un calendario sencillo y tampoco cuentan para entrega ni preparacion de recojo.
- La cotizacion muestra fechas provisionales. Al confirmarse el pago se recalculan y congelan las fechas definitivas del pedido con el calendario vigente.
- Si un distrito no esta disponible o el destino queda fuera de la cobertura estandar, se muestra el WhatsApp configurado para consultar otras opciones de entrega.
- La cotizacion se calcula y recalcula en backend. La referencia de la cotizacion mostrada debe volver al servidor y coincidir con el carrito y la entrega vigentes antes de aceptarse; la sesion nunca toma importes enviados por JavaScript como fuente de verdad.
- Si una tarifa, cobertura, precio, stock o condicion de envio cambia antes de confirmar, la Fase 5 compara la cotizacion aceptada con los valores vigentes y exige aceptar el nuevo resumen antes de crear el pedido.
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
- La identidad del proveedor, los canales de atencion y los plazos comerciales se administran como configuracion legal; mientras falten razon social y RUC, la tienda se identifica expresamente como demostrativa y no puede habilitar ventas reales.
- Los terminos y condiciones se publican por versiones. Una version publicada es inmutable y, al crear el pedido en la Fase 5, se conserva la version aceptada y la fecha de aceptacion.
- La politica de privacidad se presenta como documento independiente. La aceptacion de la compra no implica consentimiento para publicidad o prospeccion comercial.
- No se aceptan devoluciones por cambio de opinion despues de la entrega. Se atienden productos defectuosos, danados, vencidos o enviados incorrectamente sin condicionar el reclamo a que el sello permanezca intacto.
- Los incidentes visibles se solicitan preferentemente dentro de 48 horas para facilitar su investigacion, sin usar ese plazo para extinguir derechos del consumidor. Cuando la tienda sea responsable, asume el recojo, la reposicion y el nuevo envio.
- Un pedido puede cancelarse mientras no haya sido entregado al transportista. La devolucion se inicia al mismo medio de pago dentro del plazo comercial configurable, inicialmente 5 dias habiles; el reflejo bancario puede tardar hasta 30 dias calendario.
- Cada tarifa de entrega cubre inicialmente tres intentos. Solo cuentan los intentos fallidos atribuibles al cliente; los errores de tienda o transportista no consumen intentos ni generan cobros adicionales.
- Despues del primer ciclo fallido, el cliente dispone inicialmente de 7 dias calendario para pagar un nuevo envio o elegir recojo. Se permite un segundo ciclo automatico de tres intentos; un nuevo fallo pasa a atencion manual.
- El plazo de recojo comienza cuando el pedido queda listo y sera configurable, inicialmente 14 dias calendario. Su vencimiento genera seguimiento manual, nunca descarte o cancelacion silenciosa.

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
  - horarios estructurados de lunes a viernes, sabado y domingo
  - umbral de envio gratis
  - minutos de reserva de stock
  - dias habiles minimos y maximos de entrega
  - dias minimos y maximos de preparacion de recojo
  - direccion completa de recojo
- Crear configuracion por distrito para los 43 distritos de Lima y 7 del Callao:
  - provincia
  - distrito
  - UBIGEO canonico
  - tarifa
  - plazo minimo y maximo opcional con herencia del plazo general
  - activo/inactivo
- Cargar inicialmente los 50 distritos activos con tarifas referenciales entre S/ 8.00 y S/ 25.00 calculadas desde San Isidro.
- Mantener la carga idempotente para no sobrescribir tarifas o estados editados posteriormente por un administrador.
- Validar montos no negativos, rango de entrega coherente y direccion de recojo completa.
- Considerar `0` como envio gratis deshabilitado.
- Ocultar recojo cuando falte su direccion y tratarlo como gratuito cuando este habilitado.
- Aplicar el umbral sobre subtotal de productos despues de descuentos y antes del envio.
- Crear una pantalla admin compacta para configuracion general y tarifas.
- Agregar un calendario simple de fechas sin atencion, con fecha unica y motivo opcional.
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
  - plazo de entrega mostrado al crear el pedido, fecha de inicio del computo y fechas estimadas definitivas al confirmar el pago
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

Durante toda la fase:
- No se crean pedidos, reservas, cobros ni correos.
- Cada etapa incluye sus pruebas automatizadas y puede cerrarse en un commit independiente.

### Etapa 4.1: Configuracion legal y documentos versionados

Tareas:
- Agregar configuracion administrativa para nombre comercial, razon social o titular, RUC, domicilio fiscal, correo, WhatsApp, horario y enlace al Libro de Reclamaciones.
- Agregar valores configurables para aviso preferente de incidentes, procesamiento interno de reembolsos, intentos por ciclo, ciclos automaticos, plazo para pagar reenvio y plazo de recojo.
- Aplicar inicialmente 48 horas, 5 dias habiles, 3 intentos, 2 ciclos, 7 dias calendario y 14 dias calendario, respectivamente.
- Modelar documentos legales versionados con estados `draft`, `published` y `replaced`.
- Impedir la modificacion de una version publicada y permitir publicar una nueva version que reemplace a la anterior.
- Reemplazar la maqueta publica de terminos y condiciones por la version activa y preparar la misma estructura para la politica de privacidad.
- Mostrar modo demostrativo mientras no exista identidad legal completa y bloquear la futura activacion de ventas reales con configuracion incompleta.
- Crear pruebas de configuracion, publicacion, reemplazo e inmutabilidad.

Criterio de salida:
- Existe una version legal activa, historicamente reproducible y utilizable por el checkout, sin presentar el demo como un proveedor real.

### Etapa 4.2: Base real del checkout

Tareas:
- Crear controlador, requests y servicios de lectura propios del checkout.
- Mantener `auth`, `customer` y `verified` en todas sus rutas.
- Reemplazar los productos estaticos por el carrito vigente y mostrar cantidades, subtotal, desglose tributario y total reales.
- Sincronizar visibilidad, precios y disponibilidad con las reglas existentes del carrito.
- Bloquear el avance y redirigir al carrito con un mensaje persistente cuando quede vacio.
- Retirar la caja y el boton ficticios de Culqi, cuya integracion corresponde al Sprint 7.
- Garantizar que `GET /checkout` no escriba direcciones, pedidos, reservas ni movimientos.
- Crear pruebas HTTP y de vista para acceso, carrito vacio, carrito real y ausencia de efectos secundarios.

Criterio de salida:
- El checkout protegido presenta informacion real y reproducible del carrito sin modificar el dominio.

### Etapa 4.3: Contacto y direcciones

Tareas:
- Mostrar el correo verificado como solo lectura y precargar nombre y telefono para esta compra sin modificar automaticamente el perfil.
- Permitir elegir una direccion propia, priorizando la predeterminada.
- Permitir `Guardar y usar esta direccion` desde un formulario integrado al checkout.
- Hacer predeterminada la primera direccion y ofrecer un checkbox explicito para las siguientes.
- Reutilizar el catalogo canonico para resolver provincia, distrito y UBIGEO sin aceptar texto libre inconsistente.
- Respetar el limite de 10 direcciones, deshabilitar la creacion al alcanzarlo y ofrecer acceso directo a su administracion.
- Rechazar direcciones ajenas o manipuladas y conservar la seleccion ante errores.
- Crear pruebas de propiedad, direccion predeterminada, creacion, limite y validacion canonica.

Criterio de salida:
- El cliente puede definir de forma segura los datos de contacto y la direccion que se usaran para revisar su compra.

### Etapa 4.4: Modalidad y cotizacion de entrega

Tareas:
- Mostrar en la parte superior que la entrega esta disponible solo en Lima Metropolitana y Callao.
- Permitir entrega a domicilio o recojo cuando exista una direccion de recojo completa; la direccion del cliente sera obligatoria solo para entrega a domicilio.
- Resolver cobertura y tarifa exclusivamente desde el UBIGEO de la direccion seleccionada, sin permitir un segundo distrito independiente.
- Mostrar todos los distritos canonicos de Lima y Callao e identificar los inactivos como no disponibles, sin eliminar las direcciones guardadas afectadas.
- Cuando un distrito no este disponible, ofrecer otra direccion, recojo cuando corresponda y un mensaje para contactar por WhatsApp por otras opciones de entrega.
- Seleccionar inicialmente entrega a domicilio solo con una direccion cubierta y no cambiar silenciosamente de direccion o modalidad cuando falte cobertura.
- Calcular la tarifa exclusivamente en backend, tratarla como precio final con IGV incluido y aplicar envio gratis cuando corresponda.
- Considerar gratuita una entrega con tarifa de distrito `0`, independientemente del umbral global.
- Mostrar para domicilio una ventana de fechas calculada con el plazo propio del distrito o el plazo general de respaldo.
- Mostrar para recojo la direccion de la tienda, una ventana de fechas calculada con su preparacion independiente y los 14 dias calendario configurables para recoger desde el aviso de pedido listo.
- Empezar el conteo en el siguiente dia de atencion y omitir sabados o domingos cerrados y fechas registradas en el calendario sin atencion.
- Actualizar productos, avisos y resumen de manera dinamica y accesible cuando la cotizacion sincronice el carrito.
- Exigir que la referencia de la cotizacion mostrada coincida antes de guardar la seleccion; si cambia, solicitar una nueva confirmacion sin crear direcciones ni aceptar valores no vistos.
- Conservar en el snapshot las lineas canonicas del carrito, con producto, cantidad, precio y desglose tributario, ademas de los totales y la entrega.
- Preparar la cotizacion para que la Fase 5 pueda detectar cambios posteriores de tarifa, cobertura o envio gratis y solicitar aceptacion antes de crear el pedido.
- Crear pruebas de direccion condicional, cobertura por UBIGEO, distrito inactivo, modalidad inicial, tarifa con IGV, tarifa cero, envio gratis, plazos por distrito, recojo independiente, fines de semana, cierres y snapshots de fechas.

Criterio de salida:
- Cada modalidad produce una cotizacion vigente, valida y verificable desde backend sin crear el pedido ni reservar inventario; solo se acepta la revision que el cliente vio y puede continuar sin una direccion propia cuando elige recojo.

### Etapa 4.5: Datos fiscales, terminos y revision

Tareas:
- Permitir elegir `Boleta` o `Factura` mediante un control segmentado claro, con boleta como valor inicial.
- Para boleta solicitar documento personal, numero, nombres, apellidos y correo fiscal.
- Para factura solicitar RUC, razon social, domicilio fiscal y correo fiscal, sin solicitar DNI adicional.
- Validar DNI, RUC con checksum y formatos admitidos para documentos extranjeros.
- Validar campos condicionales en backend y rechazar datos ocultos, incompatibles o manipulados.
- Mantener los datos fiscales independientes del perfil y de la direccion de entrega.
- Permitir guardar boleta o factura como borrador en la sesion cifrada y restaurarla al recargar, sin implicar aceptacion legal ni crear el pedido.
- Presentar el checkout como un recorrido de contacto y entrega, comprobante y pago, guardando internamente al continuar y sin botones de guardado redundantes.
- Exigir la aceptacion explicita de la version activa de terminos y enlazar la politica de privacidad sin mezclar consentimiento publicitario.
- Crear una accion `Revisar pedido` que valide contacto, direccion, entrega, datos fiscales y terminos, y recalcule el resumen sin persistir un pedido.
- Mantener todos los valores ingresados ante errores y no guardar borradores fiscales en almacenamiento persistente del navegador.
- Registrar internamente la version legal aceptada sin exponer su numero tecnico en el texto del checkbox.
- Mantener el resumen y los productos juntos en una columna lateral de escritorio y en un resumen desplegable para celular.
- Crear pruebas para boleta, factura, documentos, manipulacion de campos, version legal y conservacion de datos.

Criterio de salida:
- El cliente puede completar y revisar una solicitud de checkout valida asociada a una version legal, todavia sin crear un pedido.

### Etapa 4.6: UX, seguridad y cierre

Tareas:
- Separar visualmente contacto, entrega, datos fiscales y resumen sin anidar tarjetas innecesariamente.
- Ajustar escritorio y celular, estados de carga, errores, campos obligatorios, foco y navegacion por teclado.
- Verificar CSRF, autorizacion, propiedad de recursos y rechazo de importes o identificadores manipulados.
- Completar pruebas HTTP, de servicios, validacion, vistas y JavaScript para los recorridos transversales.
- Ejecutar suite completa, cache de vistas, revision de JavaScript, build frontend, formato y `git diff --check`.
- Realizar validaciones manuales responsive y actualizar roadmap y status con resultados reales.

Avance verificado:
- Auditoria automatizada, seguridad transversal, estados de envio, foco de errores y suite completa aprobados.
- Validacion manual final de interaccion, teclado y responsive aprobada; Etapa 4.6 y Fase 4 cerradas.

Criterio de salida:
- El checkout permite completar y revisar todos los datos necesarios de forma segura y responsive, listo para que la Fase 5 cree el pedido idempotente y reserve stock.

Criterio de salida:
- Las seis etapas estan completas y visitar o revisar el checkout no modifica inventario ni crea pedidos.

## Fase 5: Revalidacion, creacion idempotente y reserva

Objetivo: crear el pedido exactamente una vez con datos vigentes y sin sobreventa bajo concurrencia.

Reglas de negocio cerradas:
- Si la disponibilidad disminuye pero sigue siendo positiva, proponer la cantidad disponible; si el producto queda sin stock u oculto, proponer retirarlo. No crear un pedido si el resultado queda vacio.
- El modal mostrara valores anteriores y vigentes. Aceptar los cambios ejecutara una nueva validacion y, si sigue vigente, creara el pedido; cualquier cambio concurrente adicional volvera a requerir confirmacion.
- Los terminos solo requeriran una nueva aceptacion cuando cambie su version legal. Los cambios comerciales se aceptaran expresamente desde el modal sin volver a marcar el checkbox.
- La reserva durara 15 minutos por defecto, conservara la configuracion administrativa existente y comenzara despues del commit exitoso que crea el pedido.
- Recargar, cerrar la pagina, perder la respuesta o reintentar el pago no extendera la reserva. Al vencer, pedido y pago quedaran expirados y el stock se liberara exactamente una vez.
- Cada cliente podra tener un solo pedido `pending_payment` con reserva vigente. Si ya existe, se le enviara a continuarlo o cancelarlo; una reserva vencida se procesara antes de permitir otro intento aunque el scheduler aun no haya corrido.
- La cancelacion minima de un pedido pendiente formara parte de esta fase para liberar la reserva y desbloquear un nuevo checkout. La Fase 6 la integrara en el historial y detalle del cliente.
- El carrito se limpiara solo de las lineas confirmadas y conservara adiciones concurrentes realizadas desde otra pestana. Un pedido vencido no restaurara automaticamente productos al carrito.
- Una vez creado el pedido y reservada la mercaderia, cambios posteriores de precio, categoria, marca o visibilidad no alteraran ni cancelaran automaticamente sus snapshots.
- La misma clave de idempotencia siempre devolvera el mismo pedido ante doble click, timeout o reintento. Solo un intento nuevo posterior a cancelacion o vencimiento podra usar una clave nueva.

### Etapa 5.1: Revalidacion y conflictos

Estado: Completada.

- Comparar el snapshot revisado con productos, cantidades, precios, impuestos, entrega, importes y terminos vigentes.
- Proponer cantidades reducidas y retirar productos agotados, ocultos o eliminados sin crear un pedido vacio.
- Devolver estados `unchanged`, `changed` o `blocked` y cambios estructurados con valores anteriores y actuales.
- Limitar la propuesta a productos y cantidades ya revisados, preservando fuera del pedido las adiciones concurrentes del carrito.
- Mantener intactos el snapshot, contacto, entrega y datos fiscales mientras se presenta el resultado.
- No crear pedidos, correlativos, reservas, movimientos ni documentos fiscales.
- Cubrir cambios de precio, tributacion, stock, visibilidad, tarifa, cobertura, envio gratis, plazos, importes y version legal.

Criterio de salida:
- El backend produce una propuesta vigente, determinista y sin efectos de dominio para que la Etapa 5.2 pueda presentar el conflicto.

### Etapa 5.2: Modal de aceptacion

Estado: Completada.

- Crear el endpoint protegido de confirmacion previa y conectar el resultado estructurado al checkout.
- Mostrar un modal bloqueante con valores anteriores y actuales, `Aceptar cambios y continuar` y `Volver al carrito`.
- Conservar el formulario y volver a solicitar terminos solamente cuando cambie la version legal.
- Revalidar al aceptar y repetir el modal si aparece otro cambio concurrente.
- Exigir la huella de la revision mostrada y la huella exacta de la propuesta aceptada para impedir confirmaciones obsoletas o manipuladas.
- Reemplazar en una sola escritura de sesion la cotizacion y la revision aceptadas, conservando contacto y datos fiscales.
- Mantener esta etapa libre de pedidos, correlativos, reservas, movimientos de inventario y documentos fiscales.

Criterio de salida:
- El checkout presenta y acepta solamente la propuesta comercial vigente, repite el modal ante un segundo cambio y devuelve al paso legal solo si cambiaron los terminos.

### Etapa 5.3: Creacion transaccional e idempotente

Estado: Completada.

- Bloquear productos y crear pedido, items, snapshots, historial, reservas y movimientos dentro de una transaccion.
- Persistir version, huella, fecha de aceptacion y snapshot historico de los terminos.
- Usar una clave por intento para neutralizar doble click, reintentos HTTP y respuestas perdidas.
- Limpiar solo las lineas y cantidades confirmadas despues del commit, conservando adiciones concurrentes.
- Probar concurrencia, doble envio, rollback y unicidad del pedido.

### Etapa 5.4: Pedido pendiente y cancelacion

Estado: Completada.

- Impedir mas de un pedido `pending_payment` con reserva vigente por cliente.
- Redirigir a una pantalla previa al pago con contador y opciones para continuar o cancelar.
- Liberar la reserva exactamente una vez al cancelar y permitir entonces un intento nuevo.
- Respaldar la exclusividad con una ranura nullable unica por cliente, liberada en la misma transicion al pagar, cancelar, vencer o procesar el pedido.
- Mantener los productos de un pedido cancelado fuera del carrito; cualquier carrito creado en paralelo permanece intacto.
- Conservar en esta etapa la accion real de seguir comprando; la continuacion hacia el proveedor de pago se conectara en el Sprint 7 sin simular pagos.

### Etapa 5.5: Expiracion automatica

Estado: Completada.

- Crear un comando idempotente y programarlo en el scheduler.
- Expirar pedido, pago y reservas vencidas, restaurando stock exactamente una vez.
- Procesar sincronamente una reserva vencida antes de permitir otro checkout aunque el scheduler aun no haya corrido.
- Evitar que recargas o reintentos extiendan el plazo configurado.
- Reconciliar con la hora del servidor cuando el contador llegue a cero y redirigir al carrito con un aviso persistente.
- Ejecutar `schedule:work` dentro del comando de desarrollo y dejar el cron de produccion como requisito de despliegue.

### Etapa 5.6: Integracion y cierre

Estado: Completada.

- Completar pruebas HTTP, de servicios, concurrencia, seguridad, expiracion y limpieza selectiva.
- Evitar que un reintento idempotente tardio elimine el borrador de un checkout nuevo del mismo cliente.
- Validar manualmente conflictos, doble envio, pedido pendiente, cancelacion y responsive.
- Ejecutar suite completa, cache de vistas, build, formato, revision de diff y actualizacion documental.
- Documentar la ejecucion local y productiva del scheduler que libera reservas vencidas.

Avance verificado:
- Auditoria automatizada, regresion transversal, suite completa, build y revision tecnica aprobados.
- Validacion manual final de la experiencia y los recorridos criticos aprobada.
- Guia operativa de produccion creada con entorno, Brevo, Google OAuth, scheduler, worker, almacenamiento, seguridad, backups y actualizaciones.

Criterio de salida:
- Una confirmacion crea como maximo un pedido, reserva inventario sin sobreventa y nunca obliga a rellenar checkout por cambios concurrentes.

## Fase 6: Pedidos del cliente

Objetivo: permitir que el cliente consulte y gestione sus pedidos reales sin acceder a pedidos ajenos.

Reglas de negocio cerradas:
- La interfaz mostrara un estado comercial derivado de pedido, pago, entrega y modalidad, sin reemplazar los estados tecnicos independientes del dominio.
- Domicilio terminara como `Entregado` y recojo como `Recogido`; durante el recorrido se usaran `En camino` y `Listo para recoger`, respectivamente.
- Un pago fallido con reserva vigente se mostrara como `Pago no completado`; al vencer su reserva pasara a `Vencido`.
- El historial se ordenara del mas reciente al mas antiguo, con 10 pedidos por pagina, busqueda por codigo `PED-AAAA-NNNNNN` y filtros comerciales agrupados.
- El cliente solo podra cancelar directamente un pedido `pending_payment` con pago pendiente o fallido y reserva vigente. Para un pedido pagado se mostrara contacto con la tienda hasta integrar pago y reembolso.
- No se mostrara un boton de pago ficticio. Se preparara la capacidad `puede continuar pago`, pero la accion real aparecera con Culqi en el Sprint 7.
- La linea de tiempo expondra solo eventos comprensibles para el cliente y ocultara IDs, movimientos de inventario, correos administrativos, errores y metadatos internos.
- Los items siempre se mostraran desde sus snapshots. Solo enlazaran al catalogo cuando el producto actual exista y siga visible.
- No se incluira todavia una accion `Volver a comprar`.
- Todo comprobante fiscal con PDF perteneciente al pedido sera descargable por su propietario; un documento anulado seguira visible y descargable, marcado claramente como `Anulado`.
- Los correos de creacion, cancelacion y vencimiento se enviaran despues del commit mediante cola. Un fallo de correo nunca revertira el pedido ni su transicion.
- Creacion, cancelacion y vencimiento usaran `order.customer_email` como destinatario base y agregaran una copia independiente al correo actual solo cuando sea distinto y este verificado; los comprobantes conservaran `fiscal_email`.
- DNI, documento extranjero y RUC se mostraran enmascarados salvo sus ultimos cuatro caracteres; el PDF fiscal conservara los datos completos.
- Mientras el pedido este pendiente solo se mostrara el vencimiento de la reserva. Las fechas definitivas de entrega o recojo apareceran despues de confirmar el pago.
- Listados, detalles, cancelaciones y descargas se resolveran desde el usuario autenticado o mediante policies; un pedido ajeno respondera `404`.

### Etapa 6.1: Listado y estado comercial

- Crear un resolvedor unico del estado legible a partir de pedido, pago, entrega, reserva y modalidad.
- Reemplazar la maqueta de `Mis pedidos` por una consulta limitada al cliente autenticado.
- Ordenar por creacion descendente y paginar de 10 en 10 conservando filtros con `withQueryString()`.
- Permitir busqueda normalizada por codigo interno y filtros `Todos`, `Pendientes`, `En preparacion`, `En camino o recojo`, `Finalizados` y `Cancelados o vencidos`.
- Mostrar codigo, fecha, total, modalidad, estado comercial y accion `Ver pedido` con una composicion responsive.
- Crear pruebas de consulta, orden, paginacion, busqueda, filtros, estados derivados y aislamiento entre clientes.

Criterio de salida:
- El cliente puede localizar y comprender cualquiera de sus pedidos sin ver registros de otra cuenta.

### Etapa 6.2: Detalle y linea de tiempo

Estado: Completada.

- Crear el detalle privado por codigo con snapshots de items, precios, impuestos, totales, contacto, entrega o recojo y solicitud fiscal.
- Completar la fecha del listado con una hora compacta y mostrar en el detalle una fecha absoluta descriptiva con dia y hora.
- Mostrar fecha y hora en cada evento de la linea de tiempo, usando la zona configurada `America/Lima` y evitando fechas relativas como `hace 2 horas`.
- Enmascarar documentos de identidad y omitir datos tecnicos o administrativos sensibles.
- Enlazar un item al catalogo solo cuando el producto vigente siga siendo publicamente visible.
- Construir una linea de tiempo curada para creacion, pago, preparacion, envio, recojo, entrega, cancelacion, vencimiento y reembolso.
- Mostrar vencimiento mientras el pago siga pendiente y fechas definitivas solo despues de pagarse.
- Ajustar escritorio y celular y crear pruebas de snapshots, privacidad, timeline, modalidades y producto eliminado u oculto.

Criterio de salida:
- El detalle reproduce historicamente la compra con informacion util y sin filtrar metadatos internos.

### Etapa 6.3: Cancelacion y preparacion del pago

Estado: Completada.

- Exponer la cancelacion existente desde listado y detalle solo cuando el pedido pendiente conserve una reserva vigente.
- Confirmar la accion con modal y manejar de forma legible carreras con pago o vencimiento.
- Mantener cancelacion, liberacion de stock e historial idempotentes y no restaurar productos al carrito.
- Preparar una capacidad central `puede continuar pago` para reservas vigentes sin mostrar una accion ficticia antes del Sprint 7.
- Para pedidos pagados sin entregar, mostrar el canal de contacto de la tienda en lugar de una cancelacion automatica.
- Crear pruebas de visibilidad, autorizacion, doble envio, carrera con vencimiento y liberacion exacta de inventario.

Criterio de salida:
- El cliente puede cancelar un pedido pendiente elegible una sola vez y el dominio queda listo para que Culqi habilite el reintento real.

### Etapa 6.4: Comprobantes privados

Estado: Completada tecnicamente; validacion UX integral diferida por dependencia funcional.

Dependencia de validacion:
- La etapa puede implementarse y validarse tecnicamente con factories, almacenamiento simulado y pruebas automatizadas, aunque todavia no exista una interfaz para pagar pedidos.
- No se agregara un boton administrativo ni otro mecanismo ficticio para marcar pedidos como pagados; la confirmacion real del pago corresponde a Culqi en el Sprint 7.
- La validacion UX integral `crear pedido -> pagar -> cargar comprobante -> descargar comprobante` quedara pendiente hasta completar la carga administrativa de la Fase 7 y el pago real del Sprint 7, donde se registra como criterio obligatorio de cierre.
- Esta dependencia no bloquea el desarrollo de la consulta, autorizacion y descarga privada, pero debe conservarse como validacion manual pendiente al cerrar la etapa.

- Listar los comprobantes y documentos relacionados disponibles en el detalle del pedido.
- Mostrar tipo, serie, correlativo, fecha y estado, incluyendo documentos anulados sin ocultarlos.
- Descargar exclusivamente el PDF privado del documento perteneciente al cliente autenticado.
- Rechazar documentos ajenos, rutas manipuladas, archivos inexistentes y documentos sin PDF mediante una respuesta no reveladora.
- Evitar cache publico de respuestas fiscales y usar nombres de descarga comprensibles.
- Crear pruebas con `Storage::fake()` para propiedad, documento anulado, archivo ausente y aislamiento horizontal.

Criterio de salida:
- Cada cliente puede consultar y descargar sus propios comprobantes historicos sin acceso al almacenamiento privado de terceros.
- La consulta y descarga quedan cerradas mediante pruebas automatizadas; el recorrido UX integral se validara al disponer de carga administrativa en la Fase 7 y pago real en el Sprint 7, que no podra cerrarse sin completar esa prueba manual.

### Etapa 6.5: Correos transaccionales

Estado: Completada y validada.

- Crear notificaciones en cola para pedido creado y pendiente de pago, cancelacion y vencimiento de reserva.
- Despacharlas solo despues del commit y una vez por la transicion de dominio correspondiente.
- Considerar `order.customer_email` como destinatario base de las tres comunicaciones; este snapshot corresponde al correo verificado de la cuenta al crear el pedido.
- Si la cuenta conserva un correo actual verificado y es distinto al snapshot, enviarle una segunda copia independiente, sin `CC` ni `BCC` que exponga una direccion a la otra.
- No enviar la copia al correo actual mientras siga sin verificar; si la cuenta fue eliminada, conservar unicamente el destinatario snapshot.
- Normalizar y deduplicar las direcciones para que dos correos equivalentes generen un solo envio.
- Congelar los destinatarios al producirse la transicion. Un cambio de correo durante los reintentos no modificara los destinos del evento ya registrado.
- Reservar `fiscal_email` exclusivamente para el futuro envio del comprobante fiscal.
- Respaldar la idempotencia con una restriccion unica por pedido, tipo de evento y destinatario, manteniendo un registro auditable de cada comunicacion.
- Usar tres intentos totales de Laravel por destinatario: envio inicial y hasta dos reintentos con espera aproximada de uno y cinco minutos.
- Considerar exitoso el trabajo cuando Brevo acepte el mensaje por SMTP; sus reintentos posteriores de entrega diferida pertenecen al proveedor y no crean otro evento de negocio.
- No adjuntar comprobantes inexistentes ni hacer que un fallo de Brevo revierta pedidos, reservas o inventario.
- Mantener enlaces HTTPS al pedido propio y contenido coherente con modalidad, total, vencimiento y estado.
- Enviar solo al cliente en esta etapa, sin copia administrativa, correo de pago confirmado ni adjuntos fiscales.
- Tomar el remitente y nombre comercial desde la configuracion, sin fijar la marca en las nuevas plantillas.
- Crear pruebas con `Notification::fake()` o `Mail::fake()` para destinatarios snapshot y actual, correo sin verificar, cuenta eliminada, deduplicacion, cola, duplicados, rollback y eventos elegibles.

Criterio de salida:
- Cada transicion relevante genera una comunicacion desacoplada, segura y consistente con el pedido persistido, una sola vez por destinatario legitimo.

Resultado tecnico:
- Registro persistente e inmutable por pedido, evento y destinatario normalizado, con estados de cola, envio, exito y fallo.
- Destinatarios snapshot y actual verificado resueltos y congelados dentro de la transaccion que produce el evento.
- Trabajo unico despachado despues del commit, con tres intentos totales y esperas de 60 y 300 segundos.
- Correos diferenciados para creacion, cancelacion y vencimiento, construidos con snapshots del pedido y configuracion de marca.
- Integracion idempotente con confirmacion de checkout, cancelacion real y vencimiento real por acceso o scheduler.
- Cobertura automatizada de destinatarios, deduplicacion, inmutabilidad, cola, contenido, reintentos, limite de intentos, rollback y transiciones.

### Etapa 6.6: Integracion y cierre

Estado: Completada y validada.

- Reemplazar la presentacion generica del correo de pedido creado por una plantilla Blade responsive, compatible con clientes de correo y con version de texto plano.
- Mostrar cada producto en una fila visual con miniatura principal, nombre, presentacion, cantidad e importes historicos del pedido.
- Cuando la cantidad sea una unidad, mostrar solo su precio; cuando sea mayor, mostrar cantidad, precio unitario y subtotal de linea.
- Generar o reutilizar miniaturas JPEG de `96 x 96 px`, calidad aproximada de 75 a 80 %, procurando un peso individual de 10 a 25 KB.
- Incrustar las miniaturas mediante CID para que el cliente de correo no dependa de URLs publicas, `APP_URL` ni acceso al entorno local.
- Permitir la importacion de snapshots HTTPS heredados solo desde hosts configurados, sin redirecciones, con timeout, validacion de imagen y cache privada.
- Usar el placeholder de producto cuando el snapshot no tenga imagen o el archivo ya no exista.
- Mantener las filas exclusivamente informativas, sin enlaces al producto ni JavaScript.
- Limitar las imagenes incrustadas a la principal de cada producto, deduplicarlas dentro del mismo mensaje y controlar el peso total del correo.
- Conservar subtotal de productos, descuento cuando exista, costo de entrega, total, modalidad, vencimiento y accion principal.
- Aplicar una identidad visual coherente a creacion, cancelacion y vencimiento; el listado ilustrado de productos se priorizara en el correo de creacion.
- Validar manualmente renderizado, imagenes CID, fallback, accesibilidad y responsive en Gmail, Outlook y un cliente movil.
- Completar pruebas HTTP, servicios, policies, vistas, archivos, cola y regresion de pedidos pendientes.
- Validar manualmente filtros, timeline, cancelacion, estados, documentos, correos y responsive.
- Ejecutar suite completa, cache de vistas, build, formato, revision de diff y actualizacion documental.
- Confirmar en despliegue que worker, scheduler y almacenamiento privado estan preparados.

Criterio de salida de la etapa:
- El recorrido completo de pedidos del cliente es seguro, responsive, auditable y queda listo para los pagos del Sprint 7 y la operacion administrativa de la Fase 7; sus correos son legibles, autocontenidos y compatibles con los clientes objetivo.

Criterio de salida de la fase:
- Las seis etapas estan completas y cada cliente puede seguir sus pedidos, cancelaciones, comunicaciones y documentos reales sin ver ni modificar informacion de otra cuenta.

Resultado tecnico:
- Plantilla Blade HTML responsive y alternativa de texto plano compartidas por creacion, cancelacion y vencimiento.
- Correo de creacion con filas informativas, imagen principal CID, presentacion, cantidad y desglose segun unidades, sin enlaces de producto ni JavaScript.
- Miniaturas JPEG locales de `96 x 96 px`, maximo de 25 KB cada una, deduplicacion por contenido y presupuesto total de 300 KB por mensaje.
- Snapshot HTTPS de un host permitido convertido y cacheado de forma privada; host no autorizado, archivo ausente, inseguro o ilegible sustituido por el placeholder.
- Pruebas sobre MIME real para CID inline, JPEG, dimensiones, peso, deduplicacion, texto plano, ausencia de `data:` y ausencia de enlaces de producto.
- Auditoria focalizada de cuenta, checkout y pedidos aprobada, junto con cache de Blade, build, formato, migraciones y suite completa.

## Fase 7: Admin de pedidos y comprobantes manuales

Objetivo: conectar el panel administrativo a pedidos reales y operar el flujo fiscal manual inicial.

Reglas operativas cerradas:
- El administrador operara mediante acciones contextuales; no existira un selector libre que permita saltar entre estados incompatibles.
- Un pago confirmado no iniciara por si solo la preparacion. `Iniciar preparacion` cambiara atomicamente el pedido a `processing` y la entrega a `preparing`.
- Entrega a domicilio seguira `Preparando -> En camino -> Entregado`; recojo seguira `Preparando -> Listo para recoger -> Recogido`.
- Confirmar entrega o recojo completara tambien el pedido dentro de la misma operacion.
- El administrador no podra marcar pagos como pagados, fallidos o reembolsados. Culqi controlara esos estados en el Sprint 7; los pedidos pagados de esta fase se validaran con factories.
- Un pedido pendiente de pago podra cancelarse liberando sus reservas. Un pedido pagado podra cancelarse antes del envio a domicilio o antes de ser recogido, siempre con motivo.
- Cancelar un pedido pagado lo dejara con pago `refund_pending`; solo la confirmacion real del proveedor podra llevarlo a `refunded`.
- Un pedido enviado, entregado o recogido no se cancelara desde este flujo; devoluciones, reclamos y reembolsos posteriores tendran su tratamiento correspondiente.
- Cada intento de entrega conservara fecha, resultado, responsable, motivo, atribucion y administrador. Solo los fallos atribuibles al cliente consumiran intentos.
- Al agotar los intentos configurados de un ciclo, el pedido quedara pendiente de un nuevo pago de envio. El Sprint 7 implementara su desbloqueo real; al agotar los ciclos automaticos el caso pasara a seguimiento manual.
- Los pedidos listos para recojo respetaran el plazo configurable. Su vencimiento generara seguimiento administrativo, pero nunca cancelacion o descarte automatico.
- El recojo enviara un aviso al quedar listo, otro a mitad del plazo, otro 48 horas antes y uno al vencer. Fechas coincidentes se deduplicaran y recoger el pedido cancelara los recordatorios pendientes.
- Los recordatorios se procesaran mediante scheduler y cola, con historial e idempotencia por pedido, evento y destinatario.
- Se enviaran comunicaciones operativas al quedar en camino, listo para recoger, entregado o recogido. `Iniciar preparacion` no generara correo.
- Las comunicaciones operativas usaran el correo snapshot del pedido y una copia al correo actual cuando sea distinto y este verificado.
- Cancelaciones, intentos fallidos y demas acciones sensibles exigiran motivo y conservaran administrador, correo, fecha y valores anteriores y nuevos.
- Todos los administradores tendran estas facultades durante este sprint; las capacidades granulares se implementaran en el Sprint 9.

Reglas fiscales cerradas:
- El tipo de comprobante solicitado por el cliente no se podra cambiar desde el pedido.
- Solo un pedido pagado podra registrar su boleta o factura principal.
- Tipo, serie y correlativo seran una identidad fiscal inmutable, copiada del comprobante emitido previamente en SUNAT SEE-SOL.
- Los archivos PDF o XML cargados por error se corregiran mediante una accion explicita, motivo obligatorio y versionado privado del archivo anterior, sin alterar la identidad fiscal.
- La anulacion interna solo reflejara una anulacion ya realizada por el operador en SUNAT y exigira motivo; nunca eliminara el documento ni sus archivos historicos.
- Se habilitara el registro de notas de credito y debito relacionadas, conservando el documento principal y sus referencias.
- El estado legal `emitido` o `anulado` permanecera separado del estado de correo `no enviado`, `enviado` o `fallido`.
- El comprobante se enviara manualmente y solo al correo fiscal snapshot del pedido. Cada envio o reenvio quedara auditado y los clics simultaneos no duplicaran trabajos.

### Etapa 7.1: Bandeja y detalle administrativo

Estado: Completada.

- Reemplazar los pedidos estaticos por un listado real, paginado y ordenado del mas reciente al mas antiguo.
- Buscar por codigo, cliente, correo y documento; filtrar por pedido, pago, entrega, modalidad y rango de fecha de creacion.
- Conservar filtros y pagina en la navegacion mediante query string.
- Crear el detalle administrativo de solo lectura con cliente, items, snapshots, impuestos, totales, direccion o recojo, solicitud fiscal, reserva, historial y comunicaciones.
- Mostrar estados tecnicos separados y un estado comercial comprensible, sin ofrecer todavia acciones operativas.
- Mantener la tabla y el detalle utilizables en escritorio y celular.
- Cubrir acceso administrativo, filtros combinados, paginacion, consultas y ausencia de datos estaticos.

Criterio de salida:
- El administrador puede localizar y auditar cualquier pedido real sin modificarlo.

Resultado tecnico:
- Rutas administrativas reales para listado y detalle por codigo neutral del pedido.
- Busqueda por codigo, cliente, correo, telefono, documento y razon social.
- Filtros combinables de pedido, pago, entrega, modalidad y rango inclusivo de fecha de creacion.
- Orden descendente estable, paginacion de 15 registros y retorno al listado conservando filtros y pagina.
- Detalle de solo lectura con snapshots de productos e importes, contacto, entrega o recojo, solicitud fiscal, reservas, historial tecnico, documentos, comunicaciones y aceptacion legal.
- Estados comercial y tecnicos separados, con lectura contextual para no presentar como pendientes el pago o la entrega de pedidos ya cerrados.
- Historial cronologico identificado por color e icono para los flujos de pedido, pago, entrega y reserva.
- Reservas individuales por producto conservadas para inventario y auditoria, con resumen global y detalle desplegable en el pedido.
- Eventos de reserva agrupados por operacion mediante una referencia persistida en metadata y respaldo compatible para pedidos existentes.
- Tabla de escritorio y filas compactas para celular.
- Bloque `Ultimos pedidos` del dashboard conectado a datos reales sin modificar sus tarjetas de ventas, pedidos o clientes.
- Carga anticipada de relaciones verificada sin lazy loading y respuestas privadas sin cache.
- Doce pruebas HTTP nuevas aprobadas; suite completa en verde con 571 pruebas y 4431 aserciones.
- Validacion manual aprobada en escritorio y celular, incluyendo estados terminales, historial por flujos y reservas agrupadas.

### Etapa 7.2: Operacion y transiciones de pedidos

- Agregar `refund_pending` al dominio de pagos y definir `paid -> refund_pending -> refunded`.
- Crear servicios transaccionales para iniciar preparacion, marcar envio o disponibilidad de recojo, confirmar entrega o recojo y cancelar.
- Coordinar atomicamente pedido y entrega en las acciones que afectan ambos dominios.
- Exponer solo las acciones contextuales permitidas para el estado y modalidad actuales.
- Solicitar confirmacion y motivo en cancelaciones u otras acciones sensibles.
- Impedir cambios manuales del estado de pago y mantener la integracion de Culqi como unica autoridad futura.
- Conservar administrador, correo, fecha, motivo, valores anteriores y nuevos en el historial.
- Probar transiciones validas, invalidas, concurrencia, idempotencia y rollback.

Criterio de salida:
- El administrador puede operar el ciclo principal sin saltarse estados, alterar pagos ni dejar pedido y entrega inconsistentes.

Resultado tecnico:
- Nuevo estado de pago `refund_pending`, con transicion obligatoria `paid -> refund_pending -> refunded` y representacion comercial `Reembolso pendiente`.
- Estado comercial `Pago confirmado` para distinguir un pedido pagado que aun no inicio preparacion.
- Servicio `AdminOrderOperationService` como unica puerta para iniciar preparacion, despachar, habilitar recojo, completar y cancelar.
- Flujos separados y validados para entrega a domicilio y recojo, con actualizaciones compuestas dentro de una sola transaccion.
- Seis rutas `PATCH` explicitas; no existe un endpoint administrativo que acepte estados arbitrarios ni controles para alterar pagos manualmente.
- Acciones contextuales en el detalle, confirmacion Bootstrap, motivo obligatorio al cancelar, advertencia de reembolso y proteccion frente a doble envio.
- Cancelacion pendiente que libera reservas activas y cancelacion pagada que repone reservas consumidas exactamente una vez.
- Toda cancelacion administrativa registra y encola la comunicacion transaccional `Pedido cancelado` despues del commit, sin duplicarla ante reintentos.
- El motivo visible e inmutable se comparte entre el detalle del cliente y el correo, distingue quien inicio la cancelacion, informa el reembolso cuando aplica y nunca expone la identidad del administrador.
- Las plantillas tratan los datos de cancelacion como opcionales para que los correos de creacion y vencimiento nunca dependan de ese contexto.
- Una cancelacion o vencimiento omite correos de creacion aun en cola; si la creacion ya se esta enviando, la comunicacion terminal espera su resultado y solo la conserva cuando realmente fue enviada. Un estado `Enviando` abandonado deja de bloquear despues de dos minutos.
- El estado auditable `Omitido` distingue una comunicacion reemplazada por un estado terminal de un fallo SMTP, sin consumir intentos ni enviar mensajes historicamente obsoletos.
- Una vez que Brevo acepta dos mensajes, el orden final en la bandeja ya depende del proveedor y del cliente de correo. Se acepta que una creacion y cancelacion separadas solo por segundos puedan llegar invertidas; la web conserva siempre el estado autoritativo.
- La pantalla pendiente consulta un endpoint privado cada 10 segundos y al recuperar el foco; detiene el contador y muestra cancelacion, motivo, reembolso o vencimiento sin recargar.
- Abrir nuevamente la URL pendiente de un pedido ya no pagable conduce a su detalle historico, mientras el endpoint reconcilia vencimientos antes de responder.
- Trazabilidad de la reposicion mediante movimiento de inventario, fecha, motivo y referencia opcional sin cambiar ni borrar el estado historico consumido.
- Historial inmutable con administrador, correo snapshot, motivo, estados anterior y nuevo, accion y referencia comun de operacion.
- Bloqueo pesimista, idempotencia y reintentos acotados ante interbloqueos para evitar transiciones, historiales o reposiciones duplicadas.
- Factories para pagos con reembolso pendiente o completado y reservas consumidas ya repuestas.
- Pruebas de dominio, HTTP, permisos, modalidad, transiciones validas e invalidas, rollback, idempotencia y concurrencia real con cuatro procesos.
- Suite completa aprobada con 598 pruebas y 4642 aserciones.

### Etapa 7.3: Intentos de entrega y seguimiento de recojo

- Crear historial inmutable de intentos de entrega con ciclo, numero, fecha, resultado, responsable, motivo, atribucion y administrador.
- Usar las reglas aceptadas por cada pedido desde `terms_snapshot.settings_snapshot`; los pedidos heredados usaran un respaldo explicito y probado.
- Consumir intentos solo cuando el fallo sea atribuible al cliente.
- Bloquear nuevos intentos al agotar un ciclo y mostrar `Pendiente de nuevo pago de envio` hasta que Sprint 7 confirme ese pago.
- Pasar a seguimiento manual cuando se agoten los ciclos configurados.
- Registrar fecha de disponibilidad y fecha limite para recojo usando el plazo historico del pedido.
- Generar alertas administrativas al acercarse o vencer el plazo de recojo, sin cancelar ni descartar el pedido.
- Probar limites, atribucion, ciclos, snapshots, concurrencia y alertas.

Criterio de salida:
- Cada intento y plazo queda trazable, aplica la politica aceptada por el cliente y nunca cobra o cancela silenciosamente.

### Etapa 7.4: Correos operativos y recordatorios

- Ampliar las notificaciones en cola para pedido en camino, listo para recoger, entregado y recogido.
- No enviar correo al iniciar preparacion.
- Programar recordatorios de recojo al quedar listo, a mitad del plazo, 48 horas antes y al vencer.
- Deduplicar fechas coincidentes y cancelar recordatorios pendientes cuando el pedido sea recogido.
- Resolver destinatarios con el correo snapshot y la copia al correo actual distinto y verificado.
- Registrar una entrega inmutable por pedido, evento y destinatario, con reintentos limitados.
- Procesar recordatorios mediante scheduler y reconciliarlos idempotentemente.
- Probar cola posterior al commit, destinatarios, deduplicacion, scheduler, reintentos y ausencia de envios obsoletos.

Criterio de salida:
- El cliente recibe solo comunicaciones oportunas y el administrador puede auditar cada envio.

### Etapa 7.5: Registro del comprobante principal

- Mostrar el tipo solicitado sin permitir cambiarlo y habilitar el registro solo para pedidos pagados.
- Solicitar serie, correlativo, fecha de emision y PDF oficial; permitir XML opcional.
- Validar archivos, tamano, fecha, almacenamiento privado y unicidad fiscal.
- No calcular ni sugerir el siguiente correlativo; copiar el asignado por SUNAT SEE-SOL.
- Mantener tipo, serie y correlativo como identidad inmutable.
- Mostrar por separado identidad, estado legal y estado de correo.
- Ofrecer descarga privada al administrador y al cliente propietario.
- Validar la funcionalidad con factories de pedidos pagados mientras Sprint 7 no provea pagos reales.
- Probar autorizacion, aislamiento horizontal, archivos, unicidad, concurrencia e inmutabilidad.

Criterio de salida:
- Una boleta o factura emitida externamente puede registrarse y consultarse sin exponer sus archivos ni inventar numeracion.

### Etapa 7.6: Correcciones, notas y anulacion fiscal

- Versionar cada correccion de PDF o XML, exigir motivo y conservar el archivo anterior de forma privada.
- Impedir que una correccion de archivo cambie tipo, serie o correlativo.
- Registrar notas de credito y debito relacionadas con su propia identidad y archivos.
- Permitir la anulacion solo como reflejo de una operacion ya realizada en SUNAT, con confirmacion, motivo y administrador.
- Mantener visibles los documentos anulados y todas sus relaciones sin eliminaciones en cascada.
- Probar versiones, notas relacionadas, anulacion, restricciones unicas, historial y acceso privado.

Criterio de salida:
- Los errores de carga y cambios fiscales se corrigen con trazabilidad completa y sin reescribir la historia.

### Etapa 7.7: Envio fiscal e integracion de la fase

- Habilitar `Enviar comprobante` solo para documentos emitidos con PDF vigente.
- Enviar manualmente por cola y exclusivamente al correo fiscal snapshot del pedido.
- Registrar exito, error, fecha, destinatario y administrador por cada intento.
- Permitir reenvio intencional sin duplicar el documento ni crear trabajos simultaneos por doble clic.
- Mantener separados el estado legal y el estado derivado de entrega por correo.
- Integrar en el detalle administrativo pedidos, operacion, entrega, recojo y documentos fiscales.
- Ejecutar pruebas focalizadas de la fase, cache de vistas, Pint, build y revision responsive.
- Documentar expresamente que la validacion UX con pago real y comprobante cargado se completara en el Sprint 7.

Criterio de salida:
- La operacion administrativa y fiscal queda integrada, auditable y preparada para recibir confirmaciones reales de Culqi.

Criterio de salida de la fase:
- El administrador opera pedidos reales mediante acciones validas y auditadas, controla intentos y recojos sin saltarse el pago, y puede registrar, corregir y entregar de forma segura un comprobante previamente emitido en SUNAT.

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
  - intentos y ciclos de entrega
  - recordatorios de recojo y scheduler
  - PDF privado, versiones, notas, anulacion y envio o reenvio por correo
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
- `admin.orders.delivery-attempts.store`
- `admin.orders.fiscal-documents.store`
- `admin.orders.fiscal-documents.download`
- `admin.orders.fiscal-documents.files.update`
- `admin.orders.fiscal-documents.related.store`
- `admin.orders.fiscal-documents.annul`
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
