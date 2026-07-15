# Sprint 5 - Autenticacion, Clientes y Cuenta

Fecha: 2026-07-14

Estado: En progreso

## Objetivo

Implementar identidad real de clientes con correo/contrasena y Google, proteger la zona de cuenta, administrar perfil y direcciones, y evolucionar el carrito de sesion a un carrito persistente por usuario antes de construir checkout y pedidos.

## Punto de partida

- Sprint 4 cerrado con carrito funcional para visitantes basado en sesion.
- `CartService` ya depende de `CartStorageInterface`, por lo que el almacenamiento puede cambiar sin reescribir las reglas del carrito.
- `SessionCartStorage` guarda items y advertencias en la sesion actual.
- Existe el modelo base `User`, pero no hay controladores ni flujos reales de autenticacion.
- Las rutas y vistas de login, registro, recuperacion, perfil y direcciones son maquetas estaticas.
- `laravel/socialite` todavia no esta instalado.
- No existen pedidos reales. Checkout y pedidos corresponden al Sprint 6.

## Decisiones de arquitectura y negocio

- Un visitante puede navegar y agregar productos al carrito sin iniciar sesion.
- Para entrar al checkout se exigira una cuenta autenticada y con correo verificado.
- Se admitiran dos metodos de acceso a la misma cuenta:
  - correo y contrasena
  - Google
- La identidad social se guardara en una tabla `social_accounts`, no en columnas exclusivas de Google dentro de `users`.
- No se almacenaran access tokens de Google porque solo se usara como metodo de autenticacion.
- Una cuenta creada con Google podra no tener contrasena inicialmente y definirla despues desde Seguridad.
- Si Google devuelve un correo verificado que ya pertenece a una cuenta local, no se creara otro usuario. La vinculacion exigira confirmar la contrasena de la cuenta existente o realizarse desde una sesion ya autenticada.
- Desvincular Google solo sera posible cuando la cuenta conserve otro metodo de acceso, por ejemplo una contrasena configurada.
- Google se probara en local con un callback autorizado y en tests con `Socialite::fake()`.
- Las credenciales Google viviran en `.env`; `.env.example` solo contendra nombres y valores de ejemplo sin secretos.
- Cambiar el correo normalizara y validara su unicidad, invalidara la verificacion actual y enviara un nuevo enlace. El checkout seguira bloqueado hasta verificarlo.
- El avatar de cliente sera opcional, cuadrado 1:1, recortado antes de guardar y convertido a WebP. Se podra reemplazar o eliminar y las iniciales seran el fallback.
- La foto de Google no se importara automaticamente como avatar.
- Cada usuario podra guardar como maximo 10 direcciones.
- Destinatario y telefono perteneceran a cada direccion y no se sincronizaran automaticamente con el perfil.
- Mientras un usuario tenga direcciones, existira exactamente una predeterminada:
  - la primera se marcara automaticamente
  - seleccionar otra desmarcara la anterior dentro de una transaccion
  - eliminar la predeterminada promovera la direccion restante mas antigua
- En el listado la predeterminada se elegira con radio buttons; crear o editar mostrara un checkbox para marcar la direccion actual.
- La cobertura visible de esta fase sera `Lima Metropolitana y Callao`, sin calcular tarifas.
- El cliente elegira provincia `Lima` o `Callao` y luego un distrito dependiente. Departamento/region y UBIGEO se derivaran automaticamente desde un catalogo local confiable.
- El usuario nunca escribira el UBIGEO manualmente y el backend no confiara en nombres geograficos enviados por el navegador.
- Las direcciones guardadas se eliminaran fisicamente. En el Sprint 6 los pedidos conservaran una instantanea independiente de la direccion utilizada.
- Cada usuario tendra como maximo un carrito activo persistente en base de datos.
- Al iniciar sesion, el carrito invitado se fusionara con el carrito guardado:
  - se conservara la union de productos
  - las cantidades repetidas se sumaran
  - la cantidad final nunca superara el stock vigente
  - productos no visibles o sin stock se retiraran con una advertencia clara
  - el carrito de sesion se limpiara solo despues de completar la fusion
- No se agregaran checkboxes para seleccionar items del pedido. El carrito representa la compra prevista y el cliente puede retirar productos antes de continuar.
- Los precios no se persistiran en el carrito; seguiran recalculandose desde el producto vigente.
- Las advertencias operativas del carrito pueden permanecer en sesion aunque los items autenticados se guarden en base de datos.
- Las direcciones perteneceran siempre a un usuario y solo una podra ser predeterminada.
- La cobertura, tarifa por distrito de Lima/Callao y direccion de recojo se resolveran en el Sprint 6. Este sprint almacenara direcciones estructuradas sin calcular envio.
- Las vistas de cuenta dejaran de mostrar nombres, metricas, direcciones o pedidos ficticios.

## Alcance funcional

- Registro, login, logout y sesiones reales.
- Verificacion de correo electronico.
- Recuperacion y cambio de contrasena.
- Inicio de sesion y vinculacion con Google.
- Perfil del cliente.
- Configuracion de metodos de acceso desde Seguridad.
- CRUD de direcciones guardadas.
- Proteccion de rutas de cuenta.
- Navbar y enlaces de cuenta dependientes del estado de autenticacion.
- Carrito persistente por usuario.
- Fusion transaccional del carrito invitado al autenticarse.
- Redireccion al destino solicitado despues de iniciar sesion.
- Pruebas de autenticacion, autorizacion, Google, direcciones y carrito persistente.

## Fuera de alcance

- Checkout real, pedidos, historial y detalle de pedidos; corresponden al Sprint 6.
- Compra como invitado. Los pedidos requeriran una cuenta.
- Calculo de envio, zonas por distrito y configuracion de recojo; corresponden al Sprint 6.
- Cobro, tokenizacion y webhooks de Culqi; corresponden al Sprint 7.
- Login con Facebook o Apple.
- Autenticacion, roles y policies del panel administrativo; se mantienen en el sprint de seguridad correspondiente.
- Lista de deseos, puntos, notificaciones comerciales y preferencias de marketing.
- Eliminacion definitiva de cuenta mientras no se definan las reglas de conservacion de pedidos y comprobantes.

## Fase 1: Autenticacion local

Objetivo: reemplazar las maquetas de registro y login por flujos reales y seguros de Laravel.

Tareas:
- Extender `users` con los datos estrictamente necesarios, incluyendo telefono nullable y aceptacion de terminos.
- Permitir contrasena nullable para soportar cuentas creadas exclusivamente con Google.
- Normalizar correos antes de guardarlos y mantenerlos unicos.
- Implementar controladores y Form Requests para:
  - registro
  - login
  - logout
- Validar nombre, correo, telefono, contrasena confirmada y aceptacion de terminos.
- Regenerar la sesion al autenticar y al registrar.
- Invalidar la sesion y regenerar el token CSRF al cerrar sesion.
- Aplicar middleware `guest` a login/registro y `auth` a la zona de cuenta.
- Respetar la URL `intended` para regresar al checkout u otra pagina solicitada despues del login.
- Adaptar las vistas actuales sin reemplazar el estilo visual del proyecto.
- Retirar de la interfaz los botones de Facebook y Apple mientras no sean funcionales.

Criterio de salida:
- Un cliente puede registrarse, iniciar sesion y cerrar sesion; las rutas privadas no son accesibles como invitado.

## Fase 2: Verificacion, recuperacion y seguridad

Objetivo: completar el ciclo de vida de credenciales locales y proteger operaciones sensibles.

Tareas:
- Hacer que `User` implemente verificacion de correo.
- Crear pantalla, envio y reenvio de verificacion.
- Marcar cuentas Google como verificadas solo cuando Google confirme el correo.
- Permitir login antes de verificar, pero exigir middleware `verified` para checkout.
- Conectar solicitud y restablecimiento de contrasena mediante los tokens nativos de Laravel.
- Permitir cambiar o definir una contrasena desde la cuenta.
- Exigir la contrasena actual cuando exista antes de reemplazarla.
- Al cambiar el correo, invalidar su verificacion y solicitar una nueva.
- Agregar rate limiting a login, registro, reenvio y recuperacion.
- Usar respuestas neutras en recuperacion para no revelar si un correo existe.
- Preparar una seccion Seguridad con los metodos de acceso disponibles.

Criterio de salida:
- El cliente puede verificar su correo, recuperar o cambiar su contrasena y las operaciones sensibles cuentan con controles contra abuso.

## Fase 3: Inicio de sesion y vinculacion con Google

Objetivo: permitir acceso con Google sin duplicar cuentas ni debilitar la seguridad de una cuenta existente.

Tareas:
- Instalar y configurar `laravel/socialite`.
- Agregar variables Google a `.env.example` sin secretos.
- Crear migracion, modelo y relaciones para `social_accounts`:
  - `user_id`
  - `provider`
  - `provider_user_id`
- Agregar restricciones unicas para proveedor/identidad y usuario/proveedor.
- Implementar rutas de redireccion y callback de Google.
- Crear una cuenta nueva cuando el correo Google verificado no exista.
- Iniciar sesion directamente cuando la identidad Google ya este vinculada.
- Cuando exista una cuenta local con el mismo correo:
  - mostrar una confirmacion de vinculacion
  - exigir la contrasena de la cuenta existente
  - vincular solo despues de validarla
- Permitir vincular Google desde una sesion autenticada.
- Permitir desvincularlo solo si queda otro metodo de acceso.
- Manejar cancelacion, callback invalido, correo ausente/no verificado y conflictos de proveedor.
- No persistir tokens OAuth de acceso o refresco.
- Configurar callback local para desarrollo y credenciales separadas para produccion.
- Crear pruebas con `Socialite::fake()` y documentar una validacion manual local.

Criterio de salida:
- Google permite crear, autenticar y vincular cuentas de forma segura, manteniendo un solo usuario por persona/correo verificado.

## Fase 4: Perfil real y avatar

Objetivo: conectar el perfil a datos reales del cliente y completar su identidad visual sin contenido ficticio.

Tareas:
- Crear controlador y Form Request para editar nombre, telefono y correo.
- Al cambiar correo, normalizarlo, exigir unicidad, invalidar su verificacion, enviar un nuevo enlace y mantener bloqueado el checkout hasta verificarlo.
- Bloquear el cambio de correo mientras Google permanezca vinculado; el cliente debe conservar otro metodo de acceso, desvincular Google desde Seguridad y recien entonces cambiarlo.
- Mostrar fecha real de registro y estado de verificacion.
- Agregar `avatar_path` al usuario y reutilizar el cropper universal con configuracion 1:1 para clientes.
- Validar el avatar, convertirlo a WebP, permitir reemplazarlo o quitarlo y eliminar archivos anteriores sin dejar huerfanos.
- Mostrar iniciales como fallback cuando no exista avatar.
- No importar automaticamente la imagen de perfil de Google.
- Confirmar el cierre de sesion mediante un modal para evitar salidas accidentales desde la zona de cuenta y la verificacion de correo.
- Eliminar del perfil nombres, metricas, direcciones y pedidos ficticios.
- Mantener `Mis pedidos` con un estado vacio hasta el Sprint 6.
- Crear pruebas feature de perfil, cambio de correo y ciclo de vida del avatar.

Criterio de salida:
- El cliente consulta y modifica su perfil real, administra un avatar opcional y no ve informacion simulada.

## Fase 5: Dominio de direcciones y UBIGEO

Objetivo: construir la persistencia y las reglas de negocio de direcciones antes de exponer formularios al cliente.

Tareas:
- Crear modelo, factory y migracion `CustomerAddress` con campos estructurados:
  - etiqueta
  - destinatario
  - telefono
  - departamento/region derivado
  - provincia
  - distrito
  - codigo UBIGEO
  - direccion
  - referencia nullable
  - predeterminada
- Crear relaciones `User::addresses()` y `CustomerAddress::user()`.
- Crear un catalogo local confiable de provincias y distritos habilitados para Lima Metropolitana y Callao.
- Usar como fuente el dataset oficial `UBIGEO 2022 - 1891 distritos` publicado por INEI en la Plataforma Nacional de Datos Abiertos.
- Derivar y validar departamento/region y UBIGEO en backend; nunca aceptar un codigo manual sin contrastarlo con el catalogo.
- Crear un servicio transaccional para las reglas de direcciones.
- Limitar a 10 direcciones por usuario.
- Mantener destinatario y telefono independientes de los datos del perfil.
- Hacer automaticamente predeterminada la primera direccion.
- Garantizar exactamente una direccion predeterminada mientras existan direcciones.
- Al cambiar la predeterminada, desmarcar la anterior dentro de la misma transaccion.
- Al eliminar la predeterminada, promover la direccion restante mas antigua.
- Eliminar fisicamente direcciones guardadas; los pedidos del Sprint 6 usaran snapshots historicos.
- Crear pruebas unitarias y de base de datos para catalogo, limite, predeterminada y aislamiento por usuario.

Criterio de salida:
- El dominio guarda hasta 10 direcciones canonicas por usuario y mantiene sus invariantes aun sin interfaz web.

## Fase 6: CRUD y UX de direcciones

Objetivo: permitir que el cliente administre visualmente sus direcciones usando el dominio validado en la fase anterior.

Tareas:
- Crear controladores, Form Requests y rutas para listar, crear, editar, eliminar y seleccionar la predeterminada.
- Impedir consultar o modificar direcciones de otro usuario.
- Mostrar el area `Lima Metropolitana y Callao` como readonly.
- Usar selects dependientes para provincia `Lima` o `Callao` y sus distritos habilitados.
- Completar departamento/region y UBIGEO automaticamente desde el distrito elegido.
- Mostrar las direcciones como elementos compactos y responsive, sin cards anidadas.
- Usar radio buttons en el listado para elegir la predeterminada.
- Usar un checkbox al crear o editar para marcar la direccion actual como predeterminada.
- Mostrar la primera direccion como predeterminada sin accion adicional.
- Deshabilitar la creacion al alcanzar 10 direcciones y explicar el limite.
- Agregar confirmacion antes de eliminar y comunicar cuando otra direccion sea promovida.
- Mostrar estado vacio y validaciones legibles en desktop y mobile.
- Crear pruebas feature de CRUD, autorizacion, UBIGEO y experiencia HTTP.

Criterio de salida:
- El cliente administra sus direcciones reales sin acceder a datos ajenos y la interfaz refleja todas las reglas del dominio.

## Fase 7: Carrito persistente y fusion de sesion

Objetivo: conservar el carrito entre sesiones y dispositivos sin perder la experiencia de compra como visitante.

Tareas:
- Crear modelos, factories y migraciones para `Cart` y `CartItem`.
- Relacionar un carrito activo unico con cada usuario.
- Agregar restricciones unicas por carrito/producto y cantidades positivas.
- Guardar en cada item un precio de referencia informativo para detectar cambios desde la ultima revision.
- Mantener el precio vigente del producto como unica fuente para calcular subtotales y totales; el precio de referencia nunca congela ni autoriza un precio de venta.
- Al detectar un cambio, informar el precio anterior y el actual, actualizar la referencia y evitar repetir el mismo aviso indefinidamente.
- Implementar `DatabaseCartStorage` respetando `CartStorageInterface`.
- Crear un almacenamiento/resolvedor consciente del usuario:
  - invitado usa `SessionCartStorage`
  - autenticado usa `DatabaseCartStorage`
- Mantener `CartService` como unica capa de reglas de visibilidad, stock y recalculo.
- Crear `CartMergeService` para fusionar dentro de una transaccion el carrito invitado y el persistente.
- Sumar cantidades repetidas y limitar el resultado al stock disponible.
- Informar ajustes y retiros con los warnings existentes del carrito.
- Limpiar el carrito invitado solamente despues de una fusion exitosa.
- Si la fusion falla, permitir el inicio de sesion, conservar ambos carritos intactos, informar el problema y dejar la fusion pendiente para reintentarla al consultar el carrito autenticado.
- Ejecutar la fusion despues de login, registro y autenticacion Google.
- Conservar el carrito de base de datos al cerrar sesion y comenzar una sesion invitada vacia.
- No expirar automaticamente el carrito autenticado; conservar `updated_at` para una futura politica de limpieza de carritos abandonados.
- Mantener el carrito invitado sujeto a la duracion normal de la sesion.
- Verificar que iniciar sesion sin carrito invitado no altere el carrito guardado.
- Recalcular precios siempre desde el producto vigente.

Criterio de salida:
- El carrito del cliente persiste entre sesiones/dispositivos, detecta cambios de precio sin congelarlos y se combina una sola vez con el carrito invitado respetando stock y visibilidad.

## Fase 8: Integracion, pruebas y cierre

Objetivo: validar los recorridos completos y dejar una base estable para checkout y pedidos.

Tareas:
- Hacer que navbar, footer y sidebar de cuenta reflejen invitado o cliente autenticado.
- Mostrar un menu global de cuenta para clientes y confirmacion de cierre de sesion sin duplicar modales.
- Ocultar favoritos, marcas, novedades y redes sociales mientras sus modulos no esten implementados.
- Hacer que el enlace Ofertas del footer aplique el filtro real del catalogo.
- Proteger todas las rutas de cuenta con `auth`.
- Proteger el acceso a checkout con `auth` y `verified`, aunque su logica real se implemente en Sprint 6.
- Verificar redireccion `intended` desde carrito/checkout hacia login y de vuelta al destino.
- Conservar de forma segura checkout como destino mientras un cliente completa la verificacion de correo.
- Mostrar feedback global despues de cerrar sesion o verificar el correo.
- Crear pruebas de autenticacion local:
  - registro valido e invalido
  - login correcto e incorrecto
  - logout
  - regeneracion de sesion
  - rutas protegidas
- Crear pruebas de verificacion, recuperacion y cambio de contrasena.
- Crear pruebas Google:
  - crea usuario nuevo
  - autentica identidad vinculada
  - no duplica correo existente
  - exige confirmacion para vincular cuenta local
  - no permite quitar el ultimo metodo de acceso
- Crear pruebas de perfil y aislamiento de direcciones.
- Crear pruebas de carrito persistente y fusion:
  - conserva carrito del usuario
  - suma productos repetidos
  - limita por stock
  - retira productos no disponibles
  - limpia sesion tras fusion exitosa
  - no borra carrito persistente al cerrar sesion
- Ejecutar:
  - `php artisan migrate`
  - `php artisan route:list`
  - `php artisan view:cache`
  - `node --check public/js/app.js`
  - `npm.cmd run build`
  - `git diff --check`
  - `php artisan test`
  - `python -m graphify update .`

Criterio de salida:
- Los recorridos de cuenta y carrito autenticado funcionan, las pruebas pasan y Sprint 6 puede exigir una identidad verificada sin redisenar autenticacion ni almacenamiento.

## Rutas previstas

- `login`
- `register`
- `logout`
- `verification.notice`
- `verification.verify`
- `verification.send`
- `password.request`
- `password.email`
- `password.reset`
- `password.update`
- `auth.google.redirect`
- `auth.google.callback`
- `account.profile`
- `account.profile.update`
- `account.avatar.update`
- `account.avatar.destroy`
- `account.security`
- `account.password.update`
- `account.google.link`
- `account.google.unlink`
- `account.addresses.index`
- `account.addresses.store`
- `account.addresses.update`
- `account.addresses.destroy`
- `account.addresses.default`

## Criterios de cierre del Sprint 5

- Registro, login, logout, verificacion y recuperacion funcionan con datos reales.
- Google funciona en local y sus callbacks tienen pruebas automatizadas.
- Una cuenta existente puede vincular Google de forma segura sin crear duplicados.
- El cliente puede usar contrasena y Google sobre la misma cuenta cuando ambos metodos estan configurados.
- Las rutas de cuenta no exponen datos de otros usuarios.
- Perfil y direcciones ya no dependen de contenido mock.
- El carrito invitado sigue funcionando sin cuenta.
- El carrito autenticado persiste y la fusion respeta stock, visibilidad y transacciones.
- Checkout exige usuario autenticado y correo verificado.
- No se implementan pedidos ni pagos antes de sus sprints.
- La suite completa y las validaciones finales pasan.

## Orden de commits del sprint

1. `feat(auth): implementar acceso y seguridad de clientes`
2. `feat(auth): integrar acceso y vinculacion con Google`
3. `feat(account): conectar perfil y avatar del cliente`
4. `feat(account): crear dominio de direcciones y UBIGEO`
5. `feat(account): implementar gestion de direcciones`
6. `feat(cart): persistir y fusionar carritos autenticados`
7. `test(sprint-5): validar flujos de cuenta y carrito`
8. `docs(sprint-5): cerrar sprint de autenticacion y cuenta`
