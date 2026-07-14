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

## Fase 4: Perfil y direcciones guardadas

Objetivo: conectar la zona de cliente a datos propios y preparar direcciones reutilizables para el checkout.

Tareas:
- Crear controlador y Form Request para editar nombre, telefono y correo.
- Mostrar fecha real de registro y estado de verificacion.
- Eliminar del perfil metricas y pedidos ficticios hasta que existan pedidos reales.
- Crear modelo, factory y migracion `CustomerAddress` con campos estructurados:
  - etiqueta
  - destinatario
  - telefono
  - departamento
  - provincia
  - distrito
  - direccion
  - referencia nullable
  - predeterminada
- Crear relaciones `User::addresses()` y `CustomerAddress::user()`.
- Implementar listado, creacion, edicion, eliminacion y seleccion de predeterminada.
- Garantizar transaccionalmente una sola direccion predeterminada por usuario.
- Impedir consultar o modificar direcciones de otro usuario.
- Mostrar estados vacios y validaciones legibles en desktop y mobile.
- Ocultar o dejar sin datos simulados la seccion de pedidos hasta el Sprint 6.

Criterio de salida:
- El cliente administra sus datos y direcciones reales sin acceder a informacion de otras cuentas.

## Fase 5: Carrito persistente y fusion de sesion

Objetivo: conservar el carrito entre sesiones y dispositivos sin perder la experiencia de compra como visitante.

Tareas:
- Crear modelos, factories y migraciones para `Cart` y `CartItem`.
- Relacionar un carrito activo unico con cada usuario.
- Agregar restricciones unicas por carrito/producto y cantidades positivas.
- Implementar `DatabaseCartStorage` respetando `CartStorageInterface`.
- Crear un almacenamiento/resolvedor consciente del usuario:
  - invitado usa `SessionCartStorage`
  - autenticado usa `DatabaseCartStorage`
- Mantener `CartService` como unica capa de reglas de visibilidad, stock y recalculo.
- Crear `CartMergeService` para fusionar dentro de una transaccion el carrito invitado y el persistente.
- Sumar cantidades repetidas y limitar el resultado al stock disponible.
- Informar ajustes y retiros con los warnings existentes del carrito.
- Limpiar el carrito invitado solamente despues de una fusion exitosa.
- Ejecutar la fusion despues de login, registro y autenticacion Google.
- Conservar el carrito de base de datos al cerrar sesion y comenzar una sesion invitada vacia.
- Verificar que iniciar sesion sin carrito invitado no altere el carrito guardado.
- Recalcular precios siempre desde el producto vigente.

Criterio de salida:
- El carrito del cliente persiste entre sesiones/dispositivos y se combina una sola vez con el carrito invitado respetando stock y visibilidad.

## Fase 6: Integracion, pruebas y cierre

Objetivo: validar los recorridos completos y dejar una base estable para checkout y pedidos.

Tareas:
- Hacer que navbar, footer y sidebar de cuenta reflejen invitado o cliente autenticado.
- Proteger todas las rutas de cuenta con `auth`.
- Proteger el acceso a checkout con `auth` y `verified`, aunque su logica real se implemente en Sprint 6.
- Verificar redireccion `intended` desde carrito/checkout hacia login y de vuelta al destino.
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

## Orden recomendado de commits

1. `feat(auth): implement customer registration and login`
2. `feat(auth): add email verification and password recovery`
3. `feat(auth): support secure Google account linking`
4. `feat(account): connect customer profile and addresses`
5. `feat(cart): persist and merge authenticated carts`
6. `test(auth): cover customer account and cart flows`
7. `docs(sprint-5): close authentication and account sprint`
