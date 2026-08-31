# Estado actual del proyecto

Esta es una fotografia de hechos confirmados. La planificacion y el historial
de fases permanecen en los documentos de Sprint.

## Implementado

- Tienda publica con catalogo, carrito e inventario.
- Cuentas de clientes, roles customer/admin, verificacion de correo, perfil y
  direcciones.
- Google OAuth documentado como integracion de autenticacion.
- Checkout con revalidacion, creacion de pedidos pendientes y reservas de
  inventario.
- Historial y detalle de pedidos del cliente, junto con operaciones
  administrativas documentadas en el Sprint 6.

## Validado

- PHPUnit: 631 tests, 4908 assertions, PASS.
- Reset E2E protegido: PASS.
- Playwright smoke: 5/5 PASS.
- La infraestructura E2E se mantiene separada del entorno MySQL normal.

## Parcial

- El Sprint 6 continua en progreso segun su documento de estado.
- La confirmacion real de pagos con Culqi corresponde al Sprint 7.
- La emision fiscal automatizada no esta implementada; el alcance documentado
  registra comprobantes emitidos externamente.

## Riesgos conocidos

- La base normal no debe usarse para operaciones E2E destructivas.
- Scheduler, colas, almacenamiento y configuracion de servicios externos
  requieren validacion operativa antes de produccion.
- Las ventas reales dependen de la integracion de pago y de la identidad legal
  definitiva documentadas para el proyecto.

## Pendientes confirmados

- Completar los elementos pendientes del Sprint 6 segun su roadmap y estado.
- Integrar y validar la confirmacion backend de pagos con Culqi en el Sprint 7.
