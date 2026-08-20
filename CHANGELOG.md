# Changelog

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
