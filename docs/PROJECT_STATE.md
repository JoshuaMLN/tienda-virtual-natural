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
- Comunicaciones operativas auditables para envio, entrega y recojo, con
  recordatorios de recojo reconciliados por scheduler e idempotentes.

## Validado

- PHPUnit: 659 tests, 5150 assertions, PASS.
- Reset E2E protegido: PASS.
- Playwright: 10/10 PASS, incluidos los flujos fiscales de customer y admin,
  el envio manual del PDF vigente y las operaciones pagadas de domicilio y
  recojo.
- QA manual E2E de checkout, pedidos de cliente y pedidos administrativos en
  1920x1080, 1366x768 y 390x844: PASS sin desborde horizontal ni errores de
  consola en los recorridos revisados; no incluye un cobro real con Culqi.
- Notificaciones y operaciones de la etapa 7.4: 45 pruebas focalizadas, 320
  aserciones, PASS.
- Integracion local E2E de la etapa 7.4: reset protegido, migracion, flujo de
  recojo y reconciliacion de recordatorios, PASS sin correo externo.
- La infraestructura E2E mantiene separadas la base MySQL y el almacenamiento
  privado de los entornos locales normales.

## Parcial

- El cierre tecnico local de la Fase 8 y del Sprint 6 esta completado. La
  validacion operativa de los procesos de fondo corresponde a preproduccion,
  cuando exista un entorno de despliegue.
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

- Integrar y validar la confirmacion backend de pagos con Culqi en el Sprint 7.
