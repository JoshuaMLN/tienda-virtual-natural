# Arquitectura

## Vista general

VitaNatural es una aplicacion web monolitica construida con Laravel 13 y PHP
8.3. Usa el patron habitual de Laravel con rutas, controladores, requests,
modelos, servicios de dominio y vistas Blade.

El frontend actual usa Blade, JavaScript, Bootstrap 5 y Vite. La persistencia
del entorno normal es MySQL.

## Dominios principales

- Catalogo: categorias, marcas, productos, imagenes e inventario.
- Carrito: estado de invitado y carrito persistente para clientes.
- Identidad y cuenta: registro, login, verificacion, recuperacion, perfil,
  direcciones y roles customer/admin.
- Checkout y pedidos: cotizacion de entrega, datos fiscales, reservas, estados,
  historial y operaciones administrativas documentadas para el Sprint 6.
- Configuracion de tienda: parametros operativos, legales, entrega y recojo.

## Identidad e integraciones

La aplicacion separa customer y admin mediante roles y middleware. Tambien
integra Google OAuth mediante Laravel Socialite. El correo se integra a traves
de Laravel; la configuracion de produccion documenta Brevo SMTP como proveedor.

Las colas procesan trabajo asincrono, incluidas notificaciones de pedidos. El
scheduler de Laravel ejecuta tareas de dominio relacionadas con pedidos y
seguimiento. Los procedimientos de operacion pertenecen a
[DEPLOYMENT.md](DEPLOYMENT.md).

## Datos y pruebas

El entorno normal usa MySQL. PHPUnit se ejecuta aislado con SQLite `:memory:` y
Playwright usa un entorno MySQL E2E separado. La configuracion, aislamiento y
operacion de las pruebas se documentan en [TESTING.md](TESTING.md).

## Limites actuales documentados

La confirmacion real de pagos con Culqi pertenece al Sprint 7. La emision fiscal
automatizada no forma parte del alcance actual; el Sprint 6 documenta el
registro de comprobantes emitidos externamente.
