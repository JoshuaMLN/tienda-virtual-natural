# Sprint 5 - Estado

Fecha: 2026-07-15

Estado: En progreso

## Objetivo

Implementar autenticacion y cuenta real de clientes, incluyendo Google, perfil, direcciones y un carrito persistente que pueda absorber de forma segura el carrito invitado.

## Alcance confirmado

- El visitante puede navegar y usar el carrito de sesion sin cuenta.
- Checkout y pedidos exigiran una cuenta autenticada y con correo verificado.
- Registro/login con correo y contrasena.
- Verificacion de correo, recuperacion y cambio de contrasena.
- Inicio de sesion con Google mediante Laravel Socialite.
- Google y contrasena pueden identificar a la misma cuenta.
- Una coincidencia de correo no autoriza por si sola la vinculacion: se exige contrasena o sesion autenticada.
- Perfil y direcciones reales, sin datos mock.
- Una sola direccion predeterminada por usuario.
- Carrito persistente por usuario y carrito de sesion para invitados.
- Fusion al autenticar sumando cantidades y respetando stock/visibilidad.
- Sin checkout real, pedidos, tarifas de envio ni pagos en este sprint.

## Linea base

- Sprint 4 completado.
- Suite al cerrar Sprint 4: `142 tests, 749 assertions`.
- `User` conserva la estructura base de Laravel.
- Login, registro, recuperacion, perfil y direcciones son actualmente vistas estaticas.
- `laravel/socialite` no esta instalado.
- `CartService` ya usa `CartStorageInterface` y `SessionCartStorage`.
- No existen tablas de identidades sociales, direcciones ni carritos por usuario.
- Checkout y pedidos se han movido al Sprint 6.

## Fase 1: Autenticacion local

Estado: Completada

Implementado:
- Migracion incremental de `users` con telefono opcional, contrasena nullable y fecha de aceptacion de terminos.
- Modelo `User` y factory actualizados con casts y datos de cliente.
- `RegisterRequest` con normalizacion de nombre, correo y celular, validaciones y mensajes legibles.
- `LoginRequest` con normalizacion de correo y error bag independiente.
- `RegisteredUserController` para registro, evento `Registered`, autenticacion y regeneracion de sesion.
- `AuthenticatedSessionController` para login, credenciales invalidas y logout seguro.
- Rutas GET/POST de login y registro protegidas con `guest`.
- Ruta POST de logout y zona `mi-cuenta` protegidas con `auth`.
- Redireccion de invitados a login y de usuarios autenticados fuera de paginas guest.
- Soporte de URL `intended` despues de registro o login.
- Formularios reales de login y registro con CSRF, valores anteriores, errores por campo y asteriscos obligatorios.
- Registro disponible tanto en la pagina combinada como en `/registro` mediante un partial reutilizable.
- Celular opcional validado como numero peruano de 9 digitos cuando se proporciona.
- Aceptacion obligatoria de terminos y condiciones.
- Opcion Recordarme y controles funcionales para mostrar/ocultar contrasenas.
- Duracion de Recordarme configurable con `AUTH_REMEMBER_DAYS` y valor inicial de 30 dias.
- Etiqueta de Recordarme sincronizada con la duracion configurada.
- Proveedores sociales no funcionales retirados hasta la Fase 3.
- Cierre de sesion real desde el sidebar de cuenta.
- Migracion aplicada en la base local.
- Pruebas feature en `CustomerAuthenticationTest`.

Validaciones:
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan test tests/Feature/CustomerAuthenticationTest.php`
- `php artisan test`
- `php artisan route:list --path=login`
- `php artisan route:list --path=mi-cuenta`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `git diff --check`

Resultado de pruebas:

```txt
Pruebas de autenticacion: 12 passed, 64 assertions
Suite completa: 154 passed, 813 assertions
```

## Fase 2: Verificacion, recuperacion y seguridad

Estado: Completada

Implementado:
- `User` implementa el contrato nativo `MustVerifyEmail`.
- Notificacion de verificacion de correo personalizada en espanol.
- Enlace de verificacion temporal, firmado y asociado al usuario/correo correctos.
- Pantalla de aviso y reenvio de verificacion.
- Registro nuevo redirige a verificacion cuando no existe otro destino `intended`.
- Evento `Verified` emitido al confirmar el correo.
- Cambiar el correo de un usuario existente invalida automaticamente su verificacion.
- Checkout protegido con middleware `auth` y `verified`.
- Formulario real para solicitar recuperacion de contrasena.
- Respuesta neutra para correos existentes y desconocidos, evitando enumeracion de cuentas.
- Notificacion de restablecimiento de contrasena personalizada en espanol.
- Pantalla y flujo real de restablecimiento mediante token temporal.
- Tokens de recuerdo rotados al restablecer o cambiar la contrasena.
- Nueva seccion `Mi cuenta > Seguridad`.
- Cambio de contrasena exige la contrasena actual cuando la cuenta ya tiene una.
- Cuentas sin contrasena pueden definir su primera credencial local, preparadas para Google.
- Estado visual del correo verificado o pendiente dentro de Seguridad.
- Rate limiting por correo/IP para login despues de 5 intentos fallidos.
- Limites HTTP para registro, recuperacion, restablecimiento, reenvio y cambio de contrasena.
- Pruebas separadas para verificacion, recuperacion y seguridad de cuenta.

Validaciones:
- `php artisan test tests/Feature/CustomerAuthenticationTest.php tests/Feature/EmailVerificationTest.php tests/Feature/PasswordResetTest.php tests/Feature/AccountSecurityTest.php`
- `php artisan test`
- `php artisan route:list --path=verificar-correo`
- `php artisan route:list --path=contrasena`
- `php artisan route:list --path=mi-cuenta/seguridad`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `git diff --check`

Resultado de pruebas:

```txt
Autenticacion, verificacion y seguridad: 31 passed, 137 assertions
Suite completa: 173 passed, 886 assertions
```

## Fase 3: Inicio de sesion y vinculacion con Google

Estado: Completada

Implementado:
- Laravel Socialite `^5.28` instalado y proveedor Google configurado en `config/services.php`.
- Variables Google documentadas en `.env.example` sin valores sensibles.
- Migracion y modelo `SocialAccount` con relacion a `User`.
- Restricciones unicas por proveedor/identidad y usuario/proveedor.
- Perfil Google normalizado y validado con identidad, correo valido y correo verificado obligatorios.
- Redireccion y callback OAuth conservando el estado en sesion, sin modo `stateless`.
- Creacion de clientes Google con correo verificado, contrasena nullable y aceptacion de terminos.
- Inicio de sesion sobre la misma cuenta cuando la identidad ya esta vinculada.
- Confirmacion mediante contrasena cuando ya existe una cuenta local con el mismo correo.
- Vinculacion desde `Mi cuenta > Seguridad`, exigiendo el mismo correo Google.
- Desvinculacion protegida para conservar siempre al menos un metodo de acceso.
- Manejo legible de cancelacion, callback invalido, correo ausente/no verificado y conflictos.
- Tokens OAuth excluidos del modelo y de la persistencia.
- Botones Google en login y registro con tema neutral y recurso multicolor oficial almacenado localmente.
- Pantalla de confirmacion y administracion visual de la vinculacion en Seguridad.
- Rate limiting para redireccion, callback, confirmacion, vinculacion y desvinculacion.
- Migracion aplicada en la base local.
- Pruebas feature con `Socialite::fake()` en `GoogleAuthenticationTest`.

Validaciones:
- `php artisan config:clear`
- Verificacion segura de presencia de variables Google sin imprimir sus valores.
- `php artisan migrate --force`
- `php artisan migrate:status`
- `php artisan route:list --name=google -v`
- Verificacion HTTP local: login `200` y redireccion OAuth con Client ID, callback y `state` presentes.
- Recorrido manual real aprobado con el callback local autorizado de Google.
- `php artisan test tests/Feature/GoogleAuthenticationTest.php`
- `php artisan test tests/Feature/GoogleAuthenticationTest.php tests/Feature/CustomerAuthenticationTest.php tests/Feature/AccountSecurityTest.php`
- `php artisan test`
- `php artisan view:cache`

Resultado de pruebas:

```txt
Flujos Google: 12 passed, 87 assertions
Google, autenticacion y seguridad: 30 passed, 181 assertions
Suite completa: 185 passed, 973 assertions
```

## Fase 4: Perfil real y avatar

Estado: Completada

Implementado:
- `ProfileController` y `UpdateProfileRequest` conectados exclusivamente al usuario autenticado.
- Edicion de nombre, telefono y correo con normalizacion, unicidad y mensajes legibles.
- Cambio de correo con invalidacion automatica de verificacion, nuevo enlace y bloqueo de checkout mediante `verified`.
- Correo readonly mientras Google esta vinculado, con acceso directo a Seguridad y explicacion adicional para cuentas sin contrasena.
- Validacion backend que impide alterar el correo vinculado mediante una solicitud manipulada.
- Cambio de correo habilitado nuevamente despues de conservar una contrasena y desvincular Google.
- Migracion incremental de `users` con `avatar_path` opcional.
- Cropper universal configurado en formato 1:1 para el avatar del cliente.
- Servicio de imagen con recorte cuadrado, salida WebP de 512x512 y almacenamiento en disco publico.
- Reemplazo y eliminacion de avatar con limpieza del archivo anterior despues de confirmar la actualizacion.
- Iniciales del primer y ultimo nombre como fallback cuando no existe avatar.
- Imagen de Google excluida de la importacion automatica de avatar.
- Modal reutilizable de confirmacion para cerrar sesion desde el sidebar de cuenta y la verificacion de correo.
- Accion de cierre resaltada en rojo sobrio, conservando POST y proteccion CSRF dentro del modal.
- Fecha real de registro y estado real de verificacion visibles en el perfil.
- Retiro de nombres, metricas, direcciones, pedidos y accesos ficticios de la zona de cuenta.
- Estados vacios reales en `Mis pedidos` y `Mis direcciones` hasta sus fases correspondientes.
- Pruebas feature en `CustomerProfileTest` para perfil, correo, autorizacion y ciclo de vida de archivos.

Validaciones:
- `php artisan migrate`
- `php artisan migrate:status`
- `php artisan test tests/Feature/CustomerProfileTest.php`
- `php artisan test tests/Feature/CustomerProfileTest.php tests/Feature/CustomerAuthenticationTest.php tests/Feature/AccountSecurityTest.php tests/Feature/EmailVerificationTest.php tests/Feature/GoogleAuthenticationTest.php`
- `php artisan test`
- `php vendor/bin/pint --test ...`
- `php artisan route:list --path=mi-cuenta/perfil`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`

Resultado de pruebas:

```txt
Perfil del cliente: 14 passed, 97 assertions
Perfil, autenticacion, seguridad y Google: 51 passed, 303 assertions
Suite completa: 199 passed, 1076 assertions
```

Validacion local y manual:
- Migracion `2026_07_15_000100_add_avatar_path_to_users_table` aplicada correctamente en el lote 10.
- Edicion de nombre, telefono y avatar verificada correctamente por el usuario.
- Cropper, bloqueo de correo vinculado a Google y confirmacion de cierre de sesion verificados correctamente por el usuario.

## Fase 5: Dominio de direcciones y UBIGEO

Estado: Completada

Implementado:
- Migracion `customer_addresses` con propietario, datos de entrega estructurados, UBIGEO, referencia nullable y estado predeterminado.
- Modelo `CustomerAddress`, factory, casts, scope de predeterminada y relaciones bidireccionales con `User`.
- Eliminacion fisica de direcciones y eliminacion en cascada cuando se elimina al usuario.
- Catalogo local versionado con 43 distritos de Lima y 7 del Callao.
- Fuente documentada: dataset oficial `UBIGEO 2022 - 1891 distritos` de INEI en la Plataforma Nacional de Datos Abiertos.
- Codigos UBIGEO normalizados como strings para conservar ceros iniciales.
- Resolucion tipada de departamento/region, provincia, distrito y UBIGEO canonicos.
- Rechazo de provincias fuera de cobertura y combinaciones provincia-distrito manipuladas.
- `CustomerAddressService` como puerta transaccional para crear, actualizar, elegir predeterminada y eliminar.
- Bloqueo pesimista por usuario para serializar cambios concurrentes sobre sus direcciones.
- Limite independiente de 10 direcciones por usuario.
- Destinatario y telefono independientes de los datos del perfil.
- Primera direccion predeterminada automaticamente y exactamente una predeterminada mientras existan direcciones.
- Cambio de predeterminada desmarcando la anterior dentro de la misma transaccion.
- Proteccion que evita desmarcar la unica predeterminada sin seleccionar otra.
- Promocion determinista de la direccion restante mas antigua mediante `created_at` e `id`.
- Aislamiento de propietario en editar, seleccionar predeterminada y eliminar.
- Normalizacion de textos, celular peruano y referencia nullable dentro del dominio.
- Pruebas separadas para catalogo, modelo y servicio de direcciones.

Validaciones:
- Verificacion del XLSX oficial de INEI y extraccion de los 50 distritos habilitados.
- `php artisan migrate`
- `php artisan migrate:status --pending`
- `php artisan test tests/Feature/LimaCallaoUbigeoCatalogTest.php tests/Feature/CustomerAddressModelTest.php tests/Feature/CustomerAddressServiceTest.php`
- `php artisan test`
- `php vendor/bin/pint --test ...`
- `php artisan config:cache`
- `php artisan config:clear`

Resultado de pruebas:

```txt
Catalogo, modelo y dominio de direcciones: 23 passed, 134 assertions
Suite completa: 222 passed, 1210 assertions
```

Validacion local:
- Migracion `2026_07_15_000200_create_customer_addresses_table` aplicada correctamente en el lote 11.
- No existe validacion manual de frontend en esta fase; el CRUD y la UX corresponden a la Fase 6.

## Fase 6: CRUD y UX de direcciones

Estado: Completada

Implementado:
- `CustomerAddressController`, Form Requests y siete rutas autenticadas para el CRUD completo.
- Listado exclusivo del usuario autenticado con la direccion predeterminada primero.
- Resolucion por propietario que responde `404` al consultar, editar, eliminar o seleccionar direcciones ajenas.
- Formulario compartido de creacion y edicion con valores reales de destinatario y celular editables por direccion.
- Area `Lima Metropolitana y Callao` readonly.
- Selects dependientes de provincia y distrito alimentados por el catalogo local versionado.
- Departamento/region y UBIGEO derivados automaticamente y excluidos como campos editables del request.
- Validacion backend de celular, campos requeridos y coherencia provincia-distrito con mensajes legibles.
- Tarjetas compactas y responsive, sin anidar cards, con contador de direcciones.
- Radio buttons para cambiar la predeterminada directamente desde el listado.
- Checkbox de predeterminada en creacion y edicion, preservando siempre la invariante del dominio.
- Primera direccion marcada automaticamente y explicada en el formulario.
- Creacion deshabilitada al alcanzar 10 direcciones, con explicacion visible.
- Modal reutilizable antes de eliminar y advertencia adicional para la direccion predeterminada.
- Mensaje posterior al borrado indicando cual direccion fue promovida como predeterminada.
- Estado vacio real y acciones adaptadas para desktop y mobile.
- Pruebas feature en `CustomerAddressHttpTest` para autenticacion, CRUD, autorizacion, UBIGEO, limite y estados HTTP.

Validaciones:
- `php artisan route:list --name=account.addresses --except-vendor`
- `php artisan test tests/Feature/CustomerAddressHttpTest.php`
- `php artisan test tests/Feature/CustomerAddressModelTest.php tests/Feature/CustomerAddressServiceTest.php tests/Feature/LimaCallaoUbigeoCatalogTest.php tests/Feature/CustomerAddressHttpTest.php`
- `php artisan test`
- `php vendor/bin/pint --test ...` sobre todos los PHP modificados en las Fases 5 y 6.
- `php artisan migrate:status --pending`
- `php artisan view:cache`
- `node --check public/js/app.js`
- `npm.cmd run build`
- `git diff --check`

Resultado de pruebas:

```txt
CRUD HTTP de direcciones: 12 passed, 92 assertions
Catalogo, modelo, dominio y CRUD: 35 passed, 226 assertions
Suite completa: 234 passed, 1302 assertions
```

Validacion local:
- Las siete rutas de direcciones estan registradas bajo `mi-cuenta` y protegidas por `auth`.
- No existen migraciones pendientes.
- Plantillas Blade, JavaScript y build de produccion validados correctamente.
- Recorrido visual manual pendiente de confirmacion antes del commit conjunto de las Fases 5 y 6.

## Fase 7: Carrito persistente y fusion de sesion

Estado: Pendiente

Por implementar:
- Tablas y modelos `Cart` y `CartItem`.
- `DatabaseCartStorage` compatible con el contrato actual.
- Seleccion automatica de storage segun autenticacion.
- `CartMergeService` transaccional.
- Fusion despues de registro, login y Google.
- Ajustes de stock y visibilidad con warnings legibles.
- Limpieza del carrito invitado despues de una fusion exitosa.
- Persistencia del carrito al cerrar sesion.

Validaciones esperadas:
- Invitados siguen usando la sesion.
- Usuarios autenticados recuperan el mismo carrito en otra sesion.
- Productos repetidos suman cantidades sin superar stock.
- Productos no disponibles se retiran y se informa al cliente.
- Una fusion fallida no pierde ninguno de los dos carritos.
- Cerrar sesion no elimina el carrito persistente.

## Fase 8: Integracion, pruebas y cierre

Estado: Pendiente

Por implementar:
- Navbar, footer y sidebar conscientes de autenticacion.
- Proteccion completa de rutas de cuenta.
- Proteccion temporal de checkout con `auth` y `verified`.
- Cobertura feature de todos los flujos locales y Google.
- Cobertura de autorizacion de perfil/direcciones.
- Cobertura de carrito persistente y fusion.
- Validaciones de rutas, vistas, JavaScript, build y suite completa.
- Actualizacion de Graphify y de este documento con resultados reales.

Validaciones esperadas:
- Los recorridos completos funcionan en desktop y mobile.
- La redireccion al login conserva el destino de checkout.
- La suite completa no introduce regresiones en catalogo, inventario o carrito.
- Sprint 6 puede consumir usuario, direccion y carrito sin cambiar sus contratos.

## Pendientes generales

- Crear credenciales OAuth Google separadas para produccion antes del despliegue.
- Implementar las Fases 7 y 8.
- Actualizar cada fase al completarla con comandos y conteo real de pruebas.
- Cerrar el sprint solo cuando no queden datos ficticios dentro del alcance de cuenta.
