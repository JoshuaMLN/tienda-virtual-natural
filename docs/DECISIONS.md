# Registro de decisiones

Este documento registra decisiones tecnicas o de producto que sigan vigentes y
cuenten con evidencia verificable. No reconstruye motivos historicos que no
esten documentados.

## Formato recomendado

Cada entrada debe incluir:

- Contexto.
- Decision.
- Motivo.
- Alternativas consideradas, solo si estan confirmadas.
- Consecuencias.
- Estado.

## D-001: Aislamiento de pruebas backend y E2E

- **Contexto:** el proyecto mantiene pruebas backend y de navegador con datos
  que no deben cruzarse con el entorno normal.
- **Decision:** PHPUnit usa SQLite `:memory:` y Playwright usa una base MySQL
  E2E exclusiva, separada de la base normal.
- **Motivo:** `TESTING.md` establece el aislamiento de datos y la proteccion de
  la base de desarrollo durante operaciones E2E.
- **Consecuencias:** las operaciones destructivas E2E se realizan solo mediante
  el flujo protegido documentado y la configuracion de cada capa permanece
  aislada.
- **Estado:** adoptada y validada.

## D-002: Destinos de autenticacion separados por rol

- **Contexto:** customer y admin comparten el guard de sesion `web`, pero sus
  areas privadas tienen autorizacion distinta.
- **Decision:** el login de cliente conserva el destino solicitado solo cuando
  no pertenece a `/admin`; un destino administrativo se descarta y el cliente
  vuelve a su perfil.
- **Motivo:** evitar que una autenticacion correcta de cliente termine en un
  `403` predecible del middleware administrativo, sin relajar dicha proteccion.
- **Consecuencias:** un cliente autenticado que visite directamente `/admin`
  sigue recibiendo `403`; solo se corrige la redireccion posterior al login.
- **Estado:** adoptada y validada con pruebas HTTP.
