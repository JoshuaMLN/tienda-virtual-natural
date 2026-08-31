---
name: ux-qa-playwright
description: Realiza QA funcional, exploratorio, responsive y UX de VitaNatural con Playwright, sin sustituir PHPUnit ni el manual de Playwright CLI.
---

# QA/UX con Playwright para VitaNatural

## Proposito y limites

Usa esta skill para revisar funcionalidad en navegador, regresiones visibles,
autorizacion, responsividad, accesibilidad basica o experiencia de uso. Coordina
la auditoria; para operar el navegador consulta
[playwright-cli](../playwright-cli/SKILL.md). No repite su sintaxis, comandos,
sesiones ni generacion de locators.

VitaNatural es una aplicacion Laravel. Esta skill describe la metodologia de QA
del proyecto; la preparacion detallada del entorno permanece en la guia de
pruebas.

No sustituye PHPUnit: dominio, servicios, validaciones, permisos HTTP,
concurrencia, stock, idempotencia, notificaciones y transiciones se validan
principalmente alli. Consulta [la guia de pruebas](../../../docs/TESTING.md)
para preparacion completa y resultados validados.

Una auditoria, exploracion o reproduccion de bug es de solo lectura respecto al
codigo: no modifiques aplicacion, tests ni configuracion cuando el usuario solo
pidio revisar. Durante QA se permite crear, editar o eliminar datos mediante la
aplicacion cuando el flujo lo requiera, exclusivamente en
`tienda_virtual_natural_e2e`. Una exploracion con Playwright CLI no crea
automaticamente un `.spec.ts`.

## Elegir el alcance

- **QA focalizado:** una ruta, regresion, flujo o autorizacion solicitada.
  Revisa solo el rol y viewport pertinentes.
- **QA UX exhaustivo:** usalo solo si se pide una auditoria amplia, responsive
  o visual. Recorre el alcance para cada rol en 1920x1080, 1366x768 y 390x844,
  comparando estado inicial, interaccion, errores y cierre del flujo.

Si ruta, politica o flujo no esta confirmado, inspecciona rutas, middleware y
codigo real antes de probar. No inventes recorridos, APIs ni integraciones.

## Entorno E2E seguro

El aislamiento es obligatorio:

- Desarrollo normal usa su MySQL normal `vita_natural_db`: nunca la migres,
  limpies ni resetees para E2E.
- PHPUnit usa SQLite `:memory:` y permanece intacto.
- Playwright usa exclusivamente MySQL `tienda_virtual_natural_e2e` en
  el servidor Laravel E2E `http://127.0.0.1:8011`.

La prohibicion absoluta aplica a la MySQL normal y a operaciones destructivas
directas fuera del mecanismo E2E protegido. No llames `migrate:fresh`
directamente para QA, no uses `DatabaseSeeder` y no intentes saltar guardas del
comando E2E.

Usa el preflight no destructivo en instalaciones nuevas, diagnosticos, dudas
sobre la configuracion o la base objetivo, o cambios relevantes de la
configuracion E2E:

```bash
php artisan e2e:reset --env=e2e --check
```

No es necesario ejecutarlo antes de cada `npm run test:e2e` si el entorno ya fue
validado: `e2e:reset` repite sus guardas antes de cualquier operacion
destructiva. Solo con autorizacion para restaurar datos E2E:

```bash
npm run e2e:reset
```

La ejecucion local completa es:

```bash
npm run test:e2e
```

Instala Chromium con `npm run e2e:install` solo cuando falte. No inicies worker,
scheduler ni servicios auxiliares para la primera suite.

Trata `.env.e2e` como secreto local: no lo muestres, copies ni versiones.
`playwright/.auth/customer.json` y `playwright/.auth/admin.json` contienen
cookies; no los inspecciones, edites, compartas ni anadas a Git. Si faltan,
regenera los estados con el proyecto `setup` o la ejecucion normal de la suite,
no fabricando cookies.

## Contextos, datos y aislamiento externo

Los contextos validos son:

- **Visitante / proyecto `public`:** contexto nuevo, sin estado autenticado.
- **Customer:** `playwright/.auth/customer.json`; esta verificado y acepto
  terminos.
- **Admin:** `playwright/.auth/admin.json`.

El proyecto `setup` genera los `storageState` de customer y admin mediante login
real; los proyectos `customer` y `admin` los reutilizan. `public` es
independiente de autenticacion.

Ambos roles usan el guard `web`. El customer usa `/login` y
`/mi-cuenta/perfil`; el admin usa `/admin/login` y `/admin`. El middleware debe
impedir que customer acceda al area admin. No crees un customer no verificado
ni asumas reglas ausentes de rutas o middleware.

Prioriza los datos deterministas de `E2eSeeder`: customer/admin E2E, direccion
de San Isidro, categoria y marca E2E, y productos `E2E-OMEGA-3` y
`E2E-MAGNESIO`. Si el caso requiere estado conocido, restablece E2E autorizado,
no dependas de datos manuales o aleatorios.

Manten aislados correo (`array`), cola (`sync`), imagenes remotas de email,
Google Socialite y servicios externos no confirmados. No pruebes Google, pagos
reales, CAPTCHA, courier ni workers/scheduler salvo ampliacion explicita.
Bootstrap desde CDN se acepta en esta etapa, pero registra solicitudes fallidas.

## Exploracion y revision

Usa Playwright CLI para exploracion interactiva: navega con el contexto
adecuado, usa snapshots para elegir elementos, verifica el resultado tras cada
accion y cierra la sesion creada. Para snapshots, refs, consola, requests,
resize, almacenamiento, trazas y capturas, sigue `playwright-cli`.

Segun el alcance revisa:

- navegacion, enlaces, carga, recarga y estados vacios o de error;
- formularios, cuando existan: inputs, selects, checkboxes, radios, botones,
  validacion, mensajes de error, estados disabled o loading, envio unico,
  conservacion de datos y comportamiento tras exito o error;
- interacciones, cuando sean relevantes: links, navegacion, menu, dropdown,
  modal, drawer, foco, teclado, cierre y scroll;
- autenticacion/autorizacion: redireccion de visitante, acceso del rol correcto
  y denegacion del rol incorrecto;
- consola, respuestas HTTP, solicitudes fallidas y recursos CDN/externos;
- accesibilidad basica, sin sustituir una auditoria WCAG completa: titulo,
  jerarquia de encabezados, labels o nombres accesibles, navegacion basica por
  teclado, foco visible y logico, botones y enlaces utilizables, contraste
  evidentemente problematico e imagenes o elementos visuales relevantes sin
  alternativa o contexto suficiente.

En QA exhaustivo, por viewport verifica que no exista desbordamiento horizontal
involuntario, contenido cortado, controles inaccesibles, superposiciones que
impidan acciones, objetivos tactiles imposibles o perdida de contexto al
redimensionar. Captura evidencia solo cuando sostenga un hallazgo; conserva
consola, request o screenshot unicamente si aporta valor.

## Hallazgos, severidad y pruebas permanentes

Clasifica cada hallazgo:

- **Bug funcional:** resultado observable contradice ruta, flujo o regla actual.
- **Bug responsive:** comportamiento o contenido falla en un viewport concreto.
- **Accesibilidad:** barrera verificable de teclado, foco, etiquetas o semantica.
- **Riesgo UX:** friccion o ambiguedad con paso reproducible e impacto; no lo
  presentes como fallo objetivo si admite interpretacion razonable.
- **Sugerencia estetica:** preferencia subjetiva; separala de bugs.

Asigna severidad solo a defectos/riesgos: **critica** bloquea operacion central
o expone acceso indebido; **alta** impide un flujo relevante; **media** degrada
una tarea o viewport; **baja** tiene impacto acotado o workaround sencillo.
Incluye contexto, pasos minimos, esperado, observado e evidencia util.

Propon un test Playwright permanente solo si es estable, determinista, relevante
para usuario, verificable desde navegador y no duplica una matriz o contrato que
PHPUnit cubre mejor. Durante una auditoria o revision no crees tests permanentes
automaticamente: proponlos primero. Si el usuario ya pidio explicitamente
implementar o ampliar tests Playwright, esa solicitud constituye autorizacion
suficiente y no requiere confirmacion adicional.

## Informe y limpieza

El informe final debe incluir:

1. Alcance, rol, rutas y viewports revisados.
2. Entorno usado y si preflight/reset se ejecutaron.
3. Resultado por flujo y observaciones de consola/red.
4. Tabla de hallazgos: severidad, clasificacion, evidencia, pasos, esperado,
   observado e impacto.
5. Separacion clara entre bugs objetivos, riesgos UX y sugerencias esteticas.
6. Cobertura no revisada y recomendacion de prueba permanente, si aplica.

Cierra sesiones de Playwright CLI creadas para la auditoria. Artefactos de fallo,
reportes y estados autenticados ya estan ignorados: no los publiques, adjuntes
sin necesidad ni elimines masivamente sin autorizacion.
