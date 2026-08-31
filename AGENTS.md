## Principios de trabajo

- Actua como colaborador tecnico del repositorio: comprende el cambio solicitado antes de modificar codigo y limita el trabajo al alcance autorizado.
- Antes de cambiar archivos, identifica el objetivo, localiza el codigo o configuracion relevante, comprueba dependencias e impacto, y distingue los cambios preexistentes de los relacionados con la tarea. No inicies modificaciones importantes basandote solo en suposiciones.
- No asumas que una ruta, modelo, middleware, servicio, comando, configuracion, migracion o comportamiento existe solo porque aparezca documentado o en Graphify. Cuando una decision dependa de ello, verifica la implementacion o configuracion real.
- No amplias autonomamente una tarea a refactors, limpieza, documentacion o cambios funcionales no solicitados. Si detectas un problema fuera de alcance, reportalo sin corregirlo, salvo que sea necesario para completar de forma segura la tarea solicitada.

## Proteccion de secretos y worktree

- Nunca muestres, copies, versiones ni incluyas en respuestas contrasenas, tokens, claves API, cookies, estados de autenticacion, secretos de `.env` ni credenciales de bases de datos o infraestructura. Esta proteccion aplica a todos los entornos.
- Los archivos `*.example` solo pueden documentar nombres de variables y valores ficticios seguros.
- El worktree puede contener trabajo preexistente del usuario u otros agentes. Preservalo: no reviertas cambios ajenos, no ejecutes `git checkout`, `git restore`, `git reset` ni `git clean` sobre ellos, no formatees archivos fuera de la tarea, no elimines archivos no relacionados y no mezcles cambios de tareas distintas.
- Si existen cambios previos, modifica solo lo necesario para la tarea actual.
- No ejecutes operaciones destructivas sin autorizacion expresa y sin comprobar primero el entorno objetivo. Esto incluye resets de bases de datos, `migrate:fresh`, eliminacion masiva de datos o archivos, `git reset`, `git clean` y reemplazos destructivos de configuracion. Las guardas E2E existentes siguen siendo obligatorias.

## Git y comunicacion

- No crees commits ni hagas push sin autorizacion explicita. No modifiques el historial Git ni incluyas cambios preexistentes en commits de otra tarea.
- La convencion observada en el historial usa asuntos Conventional Commits en espanol, normalmente `tipo(alcance): descripcion` (por ejemplo, `feat(pedidos): ...` o `docs(sprint-6): ...`). Manten mensajes descriptivos y coherentes con ese historial cuando se autorice un commit.
- Al terminar una tarea con cambios, informa de forma concisa que se hizo, los archivos modificados, las validaciones o pruebas ejecutadas y su resultado, los problemas, riesgos o elementos no verificados, y el estado Git relevante cuando corresponda. No afirmes validaciones que no se hayan ejecutado.

## Testing

- Ejecuta pruebas proporcionales al cambio: prioriza las directamente relacionadas y amplia a suites mayores cuando el impacto lo justifique o se solicite una validacion completa.
- PHPUnit y Playwright son complementarios; no sustituyas innecesariamente una capa por la otra.
- Nunca uses, migres, limpies ni resetees la base MySQL normal para E2E. E2E debe usar exclusivamente su entorno y base aislados.
- Ejecuta operaciones destructivas E2E solo mediante el flujo protegido y documentado. No versiones secretos ni estados de autenticacion.
- Consulta `docs/TESTING.md` para los detalles operativos de PHPUnit, el entorno E2E, preflight/reset, Playwright Test, autenticacion E2E y troubleshooting.

## Graphify

- Para preguntas sobre estructura o relaciones del codigo, usa Graphify primero cuando resulte util.
- Considera que Graphify puede estar desactualizado respecto del worktree. Contrasta los hechos relevantes con codigo o configuracion real cuando una conclusion dependa de ellos.
- No actualices Graphify durante diagnostico, revision o tareas de solo lectura, ni por cambios ajenos en el worktree.
- Actualizalo solo cuando corresponda al alcance de una tarea que haya modificado codigo y no exista una instruccion que lo prohiba.

## Referencias operativas y documentacion canonica

- `docs/TESTING.md` es la fuente canonica de testing. Las skills definen procedimientos de uso del agente y no la sustituyen.
- Para operacion interactiva, debugging y exploracion mediante Playwright CLI, consulta `.agents/skills/playwright-cli/SKILL.md`.
- Para QA funcional, exploratorio, responsive, visual o de UX, consulta `.agents/skills/ux-qa-playwright/SKILL.md`.
