# Changelog

## 0.1.2 — 2026-08-20

### Changed

- El modulo AMD se compila ahora con el **grunt de Moodle** (rollup) en lugar
  de terser, que es lo que espera `moodle-plugin-ci grunt`. Se commitean
  `amd/build/injector.min.js` y su `.map`. Eliminados `package.json` y
  `package-lock.json`: el build se lanza desde la raiz del arbol de Moodle.
- Codigo conforme al estandar `moodle` de PHP_CodeSniffer y al PHPDoc Checker:
  docblocks de fichero, clase, metodo y constante; JSDoc en las 9 funciones del
  injector; sintaxis corta de list(); formato de llamadas multilinea.
- Claves de idioma ordenadas alfabeticamente y tag `@license` con el valor
  canonico que exige moodle-cs.
- Retirado `MOODLE_INTERNAL` de los ficheros de clase sin efectos secundarios.
- `templates/grade_badge.mustache` incluye la seccion `@template` con contexto
  de ejemplo que pide el Mustache Lint.

## 0.1.1 — 2026-08-20

### Security

- El badge ya no se muestra cuando el total del curso contiene ítems ocultos y
  el ajuste `report_user_showtotalsifcontainhidden` (por curso, con respaldo en
  `$CFG->grade_report_user_showtotalsifcontainhidden`) no es
  `GRADE_REPORT_SHOW_REAL_TOTAL_IF_CONTAINS_HIDDEN`. Antes el total agregado
  podía revelar calificaciones que el informe del usuario deja en blanco.
- La función externa exige además `moodle/grade:view` en el contexto del curso,
  de modo que retirar esa capability también oculta el badge.
- Los mensajes de excepción dejan de propagarse al cliente en `warnings`; ahora
  se devuelve un string fijo (`error:context`) y el detalle va a `debugging()`.

### Added

- Fichero `LICENSE` con el texto completo de la GPL v3.

## 0.1.0 — 2026-08-20

### Added

- Función externa `local_coursegradebadge_get_grades` (AJAX batch, máx. 50
  cursos, consultas agregadas sin N+1).
- Módulo AMD `injector` con `MutationObserver` que inyecta el badge bajo la
  barra de progreso en las vistas Tarjetas / Lista / Resumen.
- Capability `local/coursegradebadge:view` (archetype student).
- Idiomas: en, es_mx.
