# Spec — `local_coursegradebadge` MVP

Fecha: 2026-08-20
Estado: aprobado en chat, pendiente de revisión escrita
Base: `PLAN.md` (raíz del repo), ajustado a las decisiones de sesión.

## 1. Objetivo

Mostrar la calificación total del curso en las tarjetas del bloque
**Vista general de cursos** (`block_myoverview`) del dashboard, justo debajo
de la barra de avance, solo para estudiantes.

## 2. Contexto confirmado

| Item | Valor |
| --- | --- |
| Instancia Moodle | Remota, versión 5.1+ (compatible 5.0: `requires = 2025041400`) |
| Tema | Boost o derivado directo (markup estándar de myoverview) |
| Instalación | Manual por el usuario (zip upload); sin acceso directo desde aquí |
| Versionado | Git local (`plugin-calificacion/`, rama `main`) + GitHub privado `moodle-local_coursegradebadge` (cuenta `jctovar`, `gh` autenticado) |
| Entorno local | PHP 8.5 CLI, Node 22, Composer, Git — sin checkout de Moodle |
| Destino final | FES Iztacala — Psicología SUAyED |

## 3. Decisiones de diseño

1. **Aproximación A (aprobada)**: webservice externa batch + módulo AMD con
   `MutationObserver`. No se sobrescriben plantillas core de `myoverview`.
2. Cálculo siempre en servidor; el frontend solo pinta lo que la función
   externa devuelve.
3. Formato de la calificación respeta el display type del `course_item`
   (real / porcentaje / letra), vía `grade_format_gradevalue()`.
4. Solo estudiantes: usuarios con `moodle/grade:viewall` reciben
   `grade = null, reason = 'nopermission'`.
5. Sin calificación, ítem oculto o `showgrades = 0` → badge no se renderiza
   (`reason = 'nograde' | 'hidden'`).
6. Carga del AMD vía **Hooks API** (`db/hooks.php` +
   `core\hook\output\before_standard_head_html_generation`), no callbacks
   legacy. Solo en `/my/courses.php` y `/my/index.php`.
7. La función externa opera **siempre sobre `$USER->id`**: nunca acepta
   `userid` del cliente.

## 4. Alcance del MVP (Fases 0–3 de PLAN.md)

### Fase 0 — Preparación
- `git init` (hecho), `.gitignore`, `.editorconfig`.
- Repo GitHub privado `moodle-local_coursegradebadge` (gh).
- CI con moodle-plugin-ci **diferido** hasta existir el repo remoto y el
  esqueleto (ver §7).

### Fase 1 — Esqueleto
- `version.php`: `component = 'local_coursegradebadge'`,
  `requires = 2025041400`, `maturity = MATURITY_ALPHA`,
  versionado `YYYYmmddXX`.
- `db/access.php`: capability `local/coursegradebadge:view`
  (`archetypes => [student]`, contexto `CONTEXT_COURSE`, por defecto
  permitida para estudiantes).
- `classes/privacy/provider.php`: `null_provider`.
- `lang/en/` + `lang/es_mx/` completos.

### Fase 2 — Backend
- `classes/grade_resolver.php`:
  - `get_course_grades(int $userid, array $courseids): array` —
    **1 consulta agregada** (`IN` sobre `{grade_grades}` con
    `itemtype = 'course'`), nunca un loop por curso.
  - Por curso: respeta `is_hidden()`, `is_excluded()`, `$course->showgrades`.
  - Formatea con `grade_format_gradevalue()` según el display type del
    `course_item`.
  - Salida por curso: `courseid, formatted, percentage, reason`.
- `classes/external/get_grades.php`:
  - `execute(array $courseids)` — máx. 50 por llamada (`PARAM_INT` limpio).
  - Por curso: `validate_context(context_course::instance($id))` +
    capability `local/coursegradebadge:view`.
  - `moodle/grade:viewall` → `reason = 'nopermission'`.
  - `execute_returns()`: `courseid, formatted, percentage, gradeurl, reason`
    (`reason ∈ {ok, nograde, hidden, nopermission, error}`).
- `db/services.php`: `ajax => true`, `loginrequired => true`; sin servicio
  externo ni tokens.
- `gradeurl`: URL a `/grade/report/user/index.php?id=<courseid>` solo cuando
  `reason = 'ok'`.

### Fase 3 — Frontend
- `amd/src/injector.js`:
  - Detecta `[data-region="courses-view"]`.
  - `MutationObserver` re-inyecta tras paginación, cambio de vista y filtros.
  - Extrae `data-course-id` de cada tarjeta; debounce 150 ms; batch único.
  - Inserción después de `.progress` /
    `[data-region="course-progress"]`, fallback al pie de la tarjeta.
  - Idempotencia: no duplica si ya existe `.lcgb-badge`.
  - Degrada silenciosamente ante cualquier error del server.
- `templates/grade_badge.mustache`: accesible (`aria-label`,
  `aria-live="polite"`), no depende solo del color.
- `styles.css`: variables Bootstrap del tema, sin `!important`.
- `amd/build/injector.min.js` compilado con `terser` local
  (sin checkout de Moodle no hay `grunt amd`; el comando se documenta en
  README y el build se commitea).

### Entregable del MVP
Zip instalable en Moodle 5.1+ remoto, badge visible bajo la barra de
progreso en vistas Tarjetas / Lista / Resumen, solo para estudiantes.

## 5. Verificación (MVP)

Sin Moodle local, la prueba automatizada se difiere al CI (§7). La
verificación del MVP es un checklist manual en la instancia remota:

1. Instalación limpia sin warnings en `admin/index.php`.
2. Estudiante con nota → badge con el valor formateado según display type.
3. Estudiante sin nota / curso sin ítems / libro oculto → sin badge.
4. Docente/admin → sin badge (y respuesta JSON con `nopermission`).
5. Cambio de vista y paginación → el badge persiste o reaparece.
6. `curl` a `/lib/ajax/service.php` como estudiante y como docente.

## 6. Riesgos heredados aplicables al MVP

R1 (markup myoverview), R2 (fuga de calificaciones → `validate_context`
obligatorio), R3 (rendimiento → consulta agregada ya en MVP),
R8 (paginación/lazy-load → MutationObserver obligatorio).
R7 se mitiga en Fase 4 (caché) — fuera del MVP, TTL no aplicable aún.

## 7. Desviaciones respecto a PLAN.md

1. **PHPUnit/Behat de Fase 2/5 diferidos**: sin checkout local de Moodle no
   son ejecutables ahora. Se activan con moodle-plugin-ci en GitHub Actions
   en cuanto el repo remoto exista (matriz Moodle 5.0/5.1 × PHP 8.2/8.3).
   La lista de casos de PLAN.md §Fase 5 queda como backlog del CI.
2. **Build AMD con `terser`** en lugar de `grunt amd` (misma razón).
3. Fases 4–8 (settings, caché, a11y dedicada, piloto, producción) fuera de
   este MVP.

## 8. Fuera de alcance del MVP

Settings administrativos (`enabled`, `categoryfilter`, `badgestyle`,
`cachettl`), caché MUC + observer `user_graded`, pruebas automatizadas
ejecutadas localmente, accesibilidad dedicada (más allá del template
accesible), app móvil, despliegue piloto.
