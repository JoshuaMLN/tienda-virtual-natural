# Sprint 5 - Estado

Fecha: 2026-07-14

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

## Fase 4: Perfil y direcciones guardadas

Estado: Pendiente

Por implementar:
- Perfil conectado al usuario autenticado.
- Edicion de nombre, telefono y correo.
- Retiro de metricas, direcciones y pedidos ficticios.
- Modelo, migracion, factory y CRUD de `CustomerAddress`.
- Direccion predeterminada unica.
- Validaciones y autorizacion por propietario.
- Estados vacios y experiencia responsive.

Validaciones esperadas:
- Un usuario solo consulta y modifica su perfil y direcciones.
- Cambiar el correo exige verificarlo nuevamente.
- Establecer una direccion predeterminada desmarca la anterior.
- Eliminar una direccion no afecta las de otras cuentas.

## Fase 5: Carrito persistente y fusion de sesion

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

## Fase 6: Integracion, pruebas y cierre

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
- Implementar las Fases 4, 5 y 6.
- Actualizar cada fase al completarla con comandos y conteo real de pruebas.
- Cerrar el sprint solo cuando no queden datos ficticios dentro del alcance de cuenta.
