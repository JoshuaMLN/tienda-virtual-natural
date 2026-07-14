# Sprint 5 - Estado

Fecha: 2026-07-14

Estado: No iniciado

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

Estado: Pendiente

Por implementar:
- Ajustes del modelo y tabla `users`.
- Registro real con aceptacion de terminos.
- Login y logout reales.
- Normalizacion de correo y validaciones con Form Requests.
- Sesiones regeneradas de forma segura.
- Middleware `guest` y `auth`.
- Redireccion a la URL originalmente solicitada.
- Formularios conectados a backend y retiro de proveedores sociales no implementados.

Validaciones esperadas:
- Un registro valido crea una sola cuenta.
- Correos duplicados y datos invalidos se rechazan.
- Login correcto regenera la sesion.
- Logout invalida la sesion y el token CSRF.
- Invitados no acceden a rutas de cuenta.

## Fase 2: Verificacion, recuperacion y seguridad

Estado: Pendiente

Por implementar:
- Verificacion y reenvio de correo.
- Recuperacion y restablecimiento de contrasena.
- Cambio o definicion de contrasena desde Seguridad.
- Revalidacion al cambiar el correo.
- Rate limiting y respuestas que eviten enumeracion de cuentas.
- Restriccion `verified` para checkout.

Validaciones esperadas:
- Los enlaces de verificacion y recuperacion respetan firma/token y vencimiento.
- Un correo modificado vuelve a estado no verificado.
- Una cuenta no verificada no puede entrar al checkout.
- Los limites de intentos se aplican a rutas sensibles.

## Fase 3: Inicio de sesion y vinculacion con Google

Estado: Pendiente

Por implementar:
- Dependencia y configuracion de Laravel Socialite.
- Variables Google sin secretos en `.env.example`.
- Tabla y modelo `social_accounts`.
- Redireccion, callback, vinculacion y desvinculacion Google.
- Confirmacion de contrasena para vincular un correo local existente.
- Soporte para cuentas Google sin contrasena inicial.
- Manejo de errores y pruebas fake del proveedor.
- Prueba manual con callback local autorizado.

Validaciones esperadas:
- Google crea una cuenta solo cuando no existe una identidad compatible.
- Una identidad vinculada inicia la misma cuenta.
- No se vincula por coincidencia de correo sin prueba de control.
- No se persisten tokens OAuth innecesarios.
- No se puede eliminar el ultimo metodo disponible para iniciar sesion.

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

- Iniciar Fase 1.
- Configurar un entorno local de correo para verificacion y recuperacion.
- Crear credenciales OAuth Google separadas para desarrollo y produccion durante la Fase 3.
- Actualizar cada fase al completarla con comandos y conteo real de pruebas.
- Cerrar el sprint solo cuando no queden datos ficticios dentro del alcance de cuenta.
