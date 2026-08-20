# local_coursegradebadge MVP — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Plugin `local_coursegradebadge` que muestra la calificación total del curso en las tarjetas de `block_myoverview` (solo estudiantes) mediante función externa AJAX batch + módulo AMD.

**Architecture:** WS externa (`local_coursegradebadge_get_grades`) calcula en servidor con consultas agregadas (sin N+1); AMD `injector.js` con `MutationObserver` inyecta el badge bajo la barra de progreso; carga del AMD vía Hooks API solo en páginas `/my/`.

**Tech Stack:** PHP 8.2+ (Moodle 5.0/5.1 plugin API), Mustache, AMD/RequireJS, terser (build), git + gh.

**Spec:** `docs/superpowers/specs/2026-08-20-coursegradebadge-mvp-design.md` (leer junto con este plan).

## Global Constraints

- `component = 'local_coursegradebadge'`; `requires = 2025041400` (Moodle 5.0.0); `maturity = MATURITY_ALPHA`.
- Versionado `YYYYmmddXX`; versión inicial `2026082000`.
- La función externa NUNCA acepta `userid` del cliente: opera sobre `$USER->id`.
- Máximo 50 cursos por llamada (`grade_resolver::MAX_COURSES`).
- Consultas agregadas con `IN` — prohibido loop de `grade_get_course_grades()`.
- `reason ∈ {ok, nograde, hidden, nopermission, error}` exacto (spec §4).
- Sin `!important` en CSS; accesibilidad: `aria-live="polite"`, no solo color.
- Sin comentarios en el código salvo cabeceras de licencia GPL v3 (obligatorias en archivos PHP/JS/CSS de Moodle para el Plugins Directory).
- Rama `main`; commits Conventional Commits.

**Adaptación de verificación (spec §7):** no hay Moodle local. Cada tarea verifica sintaxis (`php -l`, `node --check`) y el build; la prueba funcional es el checklist manual del README en la instancia remota. PHPUnit/Behat = backlog CI.

---

### Task 1: Esqueleto del plugin y cadenas de idioma

**Files:**
- Create: `.gitignore`, `.editorconfig`, `package.json`
- Create: `version.php`
- Create: `lang/en/local_coursegradebadge.php`, `lang/es_mx/local_coursegradebadge.php`

**Interfaces:**
- Produces: componente `local_coursegradebadge` instalable; strings `pluginname`, `coursegradebadge:view`, `badge:coursegrade`, `privacy:metadata` usados por Tasks 2–5.

- [ ] **Step 1: Crear `.gitignore`, `.editorconfig`, `package.json`**

`.gitignore`:
```
node_modules/
*.zip
.DS_Store
```

`.editorconfig`:
```
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 4
```

`package.json`:
```json
{
    "name": "moodle-local_coursegradebadge",
    "private": true,
    "scripts": {
        "build": "terser amd/src/injector.js --compress --mangle -o amd/build/injector.min.js"
    },
    "devDependencies": {
        "terser": "^5.31.0"
    }
}
```

- [ ] **Step 2: Crear `version.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_coursegradebadge';
$plugin->version = 2026082000;
$plugin->requires = 2025041400;
$plugin->supported = [500, 501];
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0';
```

- [ ] **Step 3: Crear `lang/en/local_coursegradebadge.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course grade badge';
$string['coursegradebadge:view'] = 'View the course total grade badge on the dashboard';
$string['badge:coursegrade'] = 'Course total grade';
$string['badge:arialabel'] = 'Course total grade: {$a}';
$string['privacy:metadata'] = 'The Course grade badge plugin does not store any personal data.';
```

- [ ] **Step 4: Crear `lang/es_mx/local_coursegradebadge.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Insignia de calificación del curso';
$string['coursegradebadge:view'] = 'Ver la insignia con la calificación total del curso en el panel';
$string['badge:coursegrade'] = 'Calificación total del curso';
$string['badge:arialabel'] = 'Calificación total del curso: {$a}';
$string['privacy:metadata'] = 'El plugin Insignia de calificación del curso no almacena datos personales.';
```

- [ ] **Step 5: Verificar sintaxis PHP**

Run: `php -l version.php && php -l lang/en/local_coursegradebadge.php && php -l lang/es_mx/local_coursegradebadge.php`
Expected: `No syntax errors detected` en los tres.

- [ ] **Step 6: Commit**

```bash
git add .gitignore .editorconfig package.json version.php lang/
git commit -m "feat: esqueleto del plugin y cadenas de idioma (en, es_mx)"
```

---

### Task 2: Capability y proveedor de privacidad

**Files:**
- Create: `db/access.php`
- Create: `classes/privacy/provider.php`

**Interfaces:**
- Produces: capability `local/coursegradebadge:view` (consumida por Task 4); clase `\local_coursegradebadge\privacy\provider` (requisito GDPR instalación).

- [ ] **Step 1: Crear `db/access.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/coursegradebadge:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],
];
```

- [ ] **Step 2: Crear `classes/privacy/provider.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l db/access.php && php -l classes/privacy/provider.php`
Expected: `No syntax errors detected` en ambos.

- [ ] **Step 4: Commit**

```bash
git add db/access.php classes/privacy/provider.php
git commit -m "feat: capability coursegradebadge:view y null_provider de privacidad"
```

---

### Task 3: `grade_resolver` con consultas agregadas

**Files:**
- Create: `classes/grade_resolver.php`

**Interfaces:**
- Produces: `local_coursegradebadge\grade_resolver::get_course_grades(int $userid, array $courseids): array` → mapa `courseid => stdClass{courseid:int, formatted:?string, percentage:?float, reason:'ok'|'nograde'|'hidden'}`. Constante `grade_resolver::MAX_COURSES = 50`. Consumido por Task 4.

- [ ] **Step 1: Crear `classes/grade_resolver.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

class grade_resolver {

    public const MAX_COURSES = 50;

    public static function get_course_grades(int $userid, array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (count($courseids) > self::MAX_COURSES) {
            $courseids = array_slice($courseids, 0, self::MAX_COURSES);
        }
        if (empty($courseids)) {
            return [];
        }

        $result = [];
        foreach ($courseids as $courseid) {
            $result[$courseid] = (object)[
                'courseid' => $courseid,
                'formatted' => null,
                'percentage' => null,
                'reason' => 'nograde',
            ];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $courses = $DB->get_records_select('course', "id $insql", $inparams, '', 'id, showgrades');
        foreach ($result as $courseid => $entry) {
            if (!isset($courses[$courseid]) || !$courses[$courseid]->showgrades) {
                $entry->reason = 'hidden';
            }
        }

        $itemrecords = $DB->get_records_select('grade_items', "itemtype = 'course' AND courseid $insql", $inparams);
        if (empty($itemrecords)) {
            return $result;
        }

        $gradeitembycourse = [];
        foreach ($itemrecords as $itemrecord) {
            $gradeitembycourse[$itemrecord->courseid] = new \grade_item($itemrecord, false);
        }

        $sql = "SELECT gi.courseid, gg.finalgrade, gg.hidden AS gradehidden, gg.excluded
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gg.userid = :userid AND gi.itemtype = 'course' AND gi.courseid $insql";
        $params = array_merge($inparams, ['userid' => $userid]);
        $graderecords = $DB->get_records_sql($sql, $params);

        foreach ($graderecords as $record) {
            $courseid = (int)$record->courseid;
            if (!isset($result[$courseid]) || $result[$courseid]->reason === 'hidden') {
                continue;
            }
            $gradeitem = $gradeitembycourse[$courseid];
            if ($record->gradehidden || $gradeitem->is_hidden()) {
                $result[$courseid]->reason = 'hidden';
                continue;
            }
            if ($record->excluded || $record->finalgrade === null) {
                continue;
            }
            $result[$courseid]->formatted = grade_format_gradevalue($record->finalgrade, $gradeitem);
            if ($gradeitem->grademax > $gradeitem->grademin) {
                $result[$courseid]->percentage =
                    round((($record->finalgrade - $gradeitem->grademin) /
                    ($gradeitem->grademax - $gradeitem->grademin)) * 100, 2);
            }
            $result[$courseid]->reason = 'ok';
        }

        return $result;
    }
}
```

Nota de diseño: 3 consultas agregadas fijas (courses, grade_items, grade_grades) independientes del número de cursos — cero N+1. `new \grade_item($record, false)` es el mismo patrón de `grade_object::fetch_helper()` de core, necesario para que `grade_format_gradevalue()` respete display type/letras/escalas del curso.

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l classes/grade_resolver.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add classes/grade_resolver.php
git commit -m "feat: grade_resolver con consultas agregadas y formato del course item"
```

---

### Task 4: Función externa y servicios AJAX

**Files:**
- Create: `classes/external/get_grades.php`
- Create: `db/services.php`

**Interfaces:**
- Consumes: `grade_resolver::get_course_grades()` (Task 3), capability `local/coursegradebadge:view` (Task 2).
- Produces: WS `local_coursegradebadge_get_grades(array $courseids): array` — respuesta `[{courseid, formatted, percentage, gradeurl, reason}]` con `reason ∈ {ok, nograde, hidden, nopermission, error}`. Consumido por Task 5 (JS).

- [ ] **Step 1: Crear `classes/external/get_grades.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_coursegradebadge\grade_resolver;

defined('MOODLE_INTERNAL') || die();

class get_grades extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'),
                'Course ids to fetch the total grade for (max 50)', VALUE_DEFAULT, []
            ),
        ]);
    }

    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'grades' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course id'),
                    'formatted' => new external_value(PARAM_TEXT, 'Formatted grade or null', VALUE_OPTIONAL, null),
                    'percentage' => new external_value(PARAM_FLOAT, 'Percentage 0-100 or null', VALUE_OPTIONAL, null),
                    'gradeurl' => new external_value(PARAM_URL, 'URL to the user grade report or null', VALUE_OPTIONAL, null),
                    'reason' => new external_value(PARAM_ALPHA, 'ok|nograde|hidden|nopermission|error'),
                ])
            ),
            'warnings' => new external_warnings(),
        ]);
    }

    public static function execute(array $courseids): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        $courseids = array_slice(array_values(array_unique($params['courseids'])), 0, grade_resolver::MAX_COURSES);

        $grades = [];
        $warnings = [];

        foreach ($courseids as $courseid) {
            try {
                self::validate_context(context_course::instance($courseid));
            } catch (\moodle_exception $e) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'error',
                ];
                $warnings[] = [
                    'item' => 'course',
                    'itemid' => $courseid,
                    'warningcode' => 'contexterror',
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            if (has_capability('moodle/grade:viewall', context_course::instance($courseid))) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'nopermission',
                ];
                continue;
            }

            if (!has_capability('local/coursegradebadge:view', context_course::instance($courseid))) {
                $grades[] = [
                    'courseid' => $courseid,
                    'reason' => 'nopermission',
                ];
                continue;
            }

            $entry = grade_resolver::get_course_grades((int)$USER->id, [$courseid])[$courseid];
            $item = [
                'courseid' => $courseid,
                'reason' => $entry->reason,
            ];
            if ($entry->reason === 'ok') {
                $item['formatted'] = $entry->formatted;
                $item['percentage'] = $entry->percentage;
                $item['gradeurl'] = (new \moodle_url('/grade/report/user/index.php', ['id' => $courseid]))->out(false);
            }
            $grades[] = $item;
        }

        return ['grades' => $grades, 'warnings' => $warnings];
    }
}
```

- [ ] **Step 2: Crear `db/services.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursegradebadge_get_grades' => [
        'classname' => \local_coursegradebadge\external\get_grades::class,
        'methodname' => 'execute',
        'description' => 'Returns the total course grade for the current user in the given courses.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
```

- [ ] **Step 3: Verificar sintaxis**

Run: `php -l classes/external/get_grades.php && php -l db/services.php`
Expected: `No syntax errors detected` en ambos.

- [ ] **Step 4: Commit**

```bash
git add classes/external/get_grades.php db/services.php
git commit -m "feat: funcion externa get_grades con validacion de contexto y capacidades"
```

---

### Task 5: Hook, AMD injector, template y estilos

**Files:**
- Create: `db/hooks.php`, `classes/hook_callbacks.php`
- Create: `amd/src/injector.js`, `amd/build/injector.min.js` (generado)
- Create: `templates/grade_badge.mustache`, `styles.css`

**Interfaces:**
- Consumes: WS `local_coursegradebadge_get_grades` (Task 4) vía `core/ajax`; strings `badge:coursegrade`, `badge:arialabel` (Task 1).
- Produces: badge `.lcgb-badge` inyectado tras `.progress` en tarjetas de `block_myoverview`.

- [ ] **Step 1: Crear `db/hooks.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

defined('MOODLE_INTERNAL') || die();

$hooks = [
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_coursegradebadge\hook_callbacks::class, 'before_standard_head_html_generation'],
    ],
];
```

- [ ] **Step 2: Crear `classes/hook_callbacks.php`**

```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

namespace local_coursegradebadge;

use core\hook\output\before_standard_head_html_generation;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (duringinitialinstall() || !isloggedin() || isguestuser()) {
            return;
        }

        $path = $PAGE->url ? $PAGE->url->get_path() : '';
        if (!in_array($path, ['/my/index.php', '/my/courses.php'], true)) {
            return;
        }

        $PAGE->requires->js_call_amd('local_coursegradebadge/injector', 'init');
    }
}
```

- [ ] **Step 3: Crear `amd/src/injector.js`**

```js
// This file is part of Moodle - https://moodle.org/
//
// GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.

/**
 * Injects the course total grade badge into block_myoverview cards.
 *
 * @module local_coursegradebadge/injector
 */
define(['core/ajax', 'core/str', 'core/templates'], function(Ajax, Str, Templates) {

    const SELECTORS = {
        coursesView: '[data-region="courses-view"]',
        card: '[data-region="course-info-container"], .course-card',
        progress: '.progress, [data-region="progress"]',
        badge: '.lcgb-badge',
    };

    const DEBOUNCE_MS = 150;
    const BATCH_LIMIT = 50;

    let debounceTimer = null;
    let pendingCourses = new Set();
    let strings = null;
    let observer = null;

    function extractCourseId(card) {
        const explicit = card.closest('[data-course-id]');
        if (explicit) {
            return parseInt(explicit.dataset.courseId, 10);
        }
        const match = card.id.match(/course-info-container-(\d+)/);
        return match ? parseInt(match[1], 10) : NaN;
    }

    function findAnchor(card) {
        return card.querySelector(SELECTORS.progress);
    }

    function renderBadge(context) {
        return Templates.render('local_coursegradebadge/grade_badge', context);
    }

    function injectBadges(gradesByCourse) {
        document.querySelectorAll(SELECTORS.coursesView + ' ' + SELECTORS.card).forEach(function(card) {
            const courseid = extractCourseId(card);
            if (isNaN(courseid) || !(courseid in gradesByCourse)) {
                return;
            }
            const grade = gradesByCourse[courseid];
            if (grade.reason !== 'ok') {
                return;
            }
            const container = findAnchor(card) || card;
            const existing = container.parentNode.querySelector(SELECTORS.badge + '[data-lcgb-course="' + courseid + '"]');
            if (existing) {
                return;
            }
            const context = {
                courseid: courseid,
                formatted: grade.formatted,
                label: strings.label,
                arialabel: strings.arialabel.replace('{$a}', grade.formatted),
            };
            renderBadge(context).then(function(html) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const node = wrapper.firstChild;
                node.dataset.lcgbCourse = courseid;
                const anchor = findAnchor(card);
                if (anchor && anchor.nextSibling) {
                    anchor.parentNode.insertBefore(node, anchor.nextSibling);
                } else if (anchor) {
                    anchor.parentNode.appendChild(node);
                } else {
                    card.appendChild(node);
                }
                return null;
            }).catch(function() {
                return null;
            });
        });
    }

    function fetchGrades() {
        if (pendingCourses.size === 0) {
            return;
        }
        const courseids = Array.from(pendingCourses).slice(0, BATCH_LIMIT);
        pendingCourses = new Set(Array.from(pendingCourses).slice(BATCH_LIMIT));
        const request = {
            methodname: 'local_coursegradebadge_get_grades',
            args: {courseids: courseids},
        };
        Ajax.call([request])[0].then(function(response) {
            const gradesByCourse = {};
            response.grades.forEach(function(grade) {
                gradesByCourse[grade.courseid] = grade;
            });
            injectBadges(gradesByCourse);
            if (pendingCourses.size > 0) {
                fetchGrades();
            }
            return null;
        }).catch(function() {
            return null;
        });
    }

    function scheduleFetch() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(fetchGrades, DEBOUNCE_MS);
    }

    function collectPendingCards() {
        document.querySelectorAll(SELECTORS.coursesView + ' ' + SELECTORS.card).forEach(function(card) {
            const courseid = extractCourseId(card);
            if (!isNaN(courseid) && !card.querySelector(SELECTORS.badge)) {
                pendingCourses.add(courseid);
            }
        });
        if (pendingCourses.size > 0) {
            scheduleFetch();
        }
    }

    function initObserver() {
        if (observer) {
            return;
        }
        const target = document.querySelector(SELECTORS.coursesView);
        if (!target) {
            return;
        }
        observer = new MutationObserver(function(mutations) {
            let relevant = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    relevant = true;
                }
            });
            if (relevant) {
                collectPendingCards();
            }
        });
        observer.observe(target, {childList: true, subtree: true});
    }

    function init() {
        Str.get_strings([
            {key: 'badge:coursegrade', component: 'local_coursegradebadge'},
            {key: 'badge:arialabel', component: 'local_coursegradebadge'},
        ]).then(function(results) {
            strings = {label: results[0], arialabel: results[1]};
            collectPendingCards();
            initObserver();
            return null;
        }).catch(function() {
            return null;
        });
    }

    return {init: init};
});
```

- [ ] **Step 4: Crear `templates/grade_badge.mustache`**

```html
{{!
    This file is part of Moodle - https://moodle.org/

    GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.
}}
<div class="lcgb-badge d-flex justify-content-between align-items-center mt-1" role="status" aria-live="polite"
     aria-label="{{arialabel}}" data-region="lcgb-badge">
    <span class="lcgb-badge-label small text-muted">{{label}}</span>
    <span class="lcgb-badge-value small font-weight-bold">{{formatted}}</span>
</div>
```

- [ ] **Step 5: Crear `styles.css`**

```css
/* This file is part of Moodle - https://moodle.org/
 *
 * GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.
 */

.lcgb-badge {
    color: var(--bs-body-color, #333);
    border-top: 1px solid var(--bs-border-color, #dee2e6);
    padding-top: 0.25rem;
}

.lcgb-badge-value {
    color: var(--bs-primary, #0f6cbf);
}
```

- [ ] **Step 6: Compilar y verificar**

Run: `node --check amd/src/injector.js && npm install && npm run build && ls -la amd/build/`
Expected: sin errores de sintaxis; existe `amd/build/injector.min.js`.

- [ ] **Step 7: Commit**

```bash
git add db/hooks.php classes/hook_callbacks.php amd/ templates/ styles.css package-lock.json
git commit -m "feat: inyeccion AMD del badge con MutationObserver, template accesible y estilos"
```

---

### Task 6: README, CHANGELOG y paquete ZIP

**Files:**
- Create: `README.md`, `CHANGELOG.md`

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: documentación + zip instalable `../coursegradebadge-0.1.0.zip`.

- [ ] **Step 1: Crear `README.md`**

```markdown
# local_coursegradebadge

Muestra la calificación total del curso en las tarjetas del bloque
**Vista general de cursos** (`block_myoverview`) del dashboard, debajo de la
barra de avance. Solo para estudiantes.

## Requisitos

- Moodle 5.0 / 5.1+
- PHP 8.2+
- Tema basado en Boost

## Instalación

1. Administración del sitio → Plugins → Instalar plugin → subir el ZIP
   `coursegradebadge-<version>.zip`.
2. Confirmar la instalación en `admin/index.php`.
3. Verificar en *Administración del sitio → Plugins → Plugins locales*.

## Desarrollo

Compilar el módulo AMD tras editar `amd/src/injector.js`:

    npm install
    npm run build

El resultado (`amd/build/injector.min.js`) se commitea al repo.

## Verificación manual (checklist)

1. Instalación limpia sin warnings en `admin/index.php`.
2. Estudiante con nota → badge con el valor formateado según el display type
   del curso (real / porcentaje / letra).
3. Estudiante sin nota, curso sin ítems calificables o libro oculto → sin badge.
4. Docente/administrador → sin badge.
5. Cambio de vista Tarjetas → Lista → Resumen: el badge persiste o reaparece.
6. Paginación / infinite scroll: el badge aparece en las nuevas tarjetas.
7. En consola JS del navegador: respuesta de `local_coursegradebadge_get_grades`
   con `reason` esperado por rol.

## Limitaciones conocidas

- No aparece en la app Moodle Mobile.
- La calificación mostrada es la total del curso (parcial mientras el curso
  esté en curso); el badge enlaza al reporte completo del usuario.
```

- [ ] **Step 2: Crear `CHANGELOG.md`**

```markdown
# Changelog

## 0.1.0 — 2026-08-20

### Added

- Función externa `local_coursegradebadge_get_grades` (AJAX batch, máx. 50
  cursos, consultas agregadas sin N+1).
- Módulo AMD `injector` con `MutationObserver` que inyecta el badge bajo la
  barra de progreso en las vistas Tarjetas / Lista / Resumen.
- Capability `local/coursegradebadge:view` (archetype student).
- Idiomas: en, es_mx.
```

- [ ] **Step 3: Generar ZIP**

Run: `git archive --format=zip --prefix=coursegradebadge/ -o ../coursegradebadge-0.1.0.zip HEAD`
Expected: el zip existe y su raíz contiene `coursegradebadge/` con `version.php`.

- [ ] **Step 4: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: README con instalacion, build y checklist de verificacion"
```

---

### Task 7: Repo GitHub privado y push

**Files:**
- Ninguno nuevo (usa repo remoto).

**Interfaces:**
- Consumes: repo local completo (Tasks 1–6).
- Produces: `https://github.com/jctovar/moodle-local_coursegradebadge` (privado).

- [ ] **Step 1: Crear repo remoto y push**

Run: `gh repo create moodle-local_coursegradebadge --private --source=. --remote=origin --push`
Expected: repo creado y rama `main` pusheada sin errores.

- [ ] **Step 2: Verificar**

Run: `git remote -v && gh repo view --json name,visibility -q '.name + " " + .visibility'`
Expected: `moodle-local_coursegradebadge PRIVATE`.

---

## Verificación final (manual, instancia remota — spec §5)

Ejecutar el checklist del README tras instalar el ZIP. Los casos PHPUnit/Behat
de `PLAN.md` §Fase 5 quedan como backlog del CI (moodle-plugin-ci) — fuera de
este MVP.
