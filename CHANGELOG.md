# Changelog

## 0.1.0 — 2026-08-20

### Added

- Función externa `local_coursegradebadge_get_grades` (AJAX batch, máx. 50
  cursos, consultas agregadas sin N+1).
- Módulo AMD `injector` con `MutationObserver` que inyecta el badge bajo la
  barra de progreso en las vistas Tarjetas / Lista / Resumen.
- Capability `local/coursegradebadge:view` (archetype student).
- Idiomas: en, es_mx.
