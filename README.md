# Course Grade Badge — `local_coursegradebadge`

![Moodle](https://img.shields.io/badge/Moodle-5.0%20%2F%205.1%2B-orange)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Licencia](https://img.shields.io/badge/licencia-GPL--3.0-green)
![Estado](https://img.shields.io/badge/estado-ALPHA-red)

Muestra la **calificación total del curso** en las tarjetas del bloque
**Vista general de cursos** (`block_myoverview`) del dashboard, justo debajo de
la barra de avance. Visible únicamente para estudiantes.

Desarrollado para **FES Iztacala, UNAM — Psicología SUAyED**.

---

## Características

- Badge con la calificación total del curso en las vistas *Tarjetas*,
  *Lista* y *Resumen* de `block_myoverview`.
- **Formato respetando la configuración del curso** (real, porcentaje o letra),
  calculado siempre en el servidor.
- **Solo estudiantes**: docentes, gestores y administradores no reciben badge.
- **Sin sobrescritura de plantillas core**: inyección en el DOM mediante un
  módulo AMD con `MutationObserver`, resiliente a paginación, cambios de vista
  y filtros del bloque.
- **Una sola llamada AJAX por vista** (batch de hasta 50 cursos, con consultas
  agregadas — sin N+1).
- **Accesible**: `aria-label`, `aria-live="polite"`, sin depender solo del
  color.
- **Idiomas**: inglés (`en`) y español México (`es_mx`).

## Requisitos

| Componente | Mínimo |
| --- | --- |
| Moodle | 5.0 / 5.1+ (`requires = 2025041400`) |
| PHP | 8.2 |
| Tema | Boost o derivado directo |

## Instalación

1. **Administración del sitio → Plugins → Instalar plugin** → subir
   `coursegradebadge-<versión>.zip`.
2. Confirmar la actualización de la base de datos en `admin/index.php`.
3. Verificar que el plugin aparece en *Administración del sitio → Plugins →
   Plugins locales* sin advertencias.

También puede instalarse manualmente copiando el contenido a
`local/coursegradebadge/` y ejecutando la actualización desde
`admin/index.php` o `php admin/cli/upgrade.php`.

## Cómo funciona

```text
Dashboard (/my/) ──hook──▶ carga amd/injector.js
        │                         │
        │            MutationObserver detecta tarjetas
        │            (vista, paginación, filtros)
        │                         │
        │                  1 llamada AJAX batch
        ▼                         ▼
lib/ajax/service.php ──▶ local_coursegradebadge_get_grades
        │    validate_context + capabilities por curso
        │    consultas agregadas sobre grade_items/grade_grades
        │    formato según display type del course item
        ▼
badge .lcgb-badge insertado bajo la barra de progreso
```

- El cálculo ocurre en el servidor: se respetan `is_hidden()`, `is_excluded()`
  y `showgrades` de cada curso.
- La función externa opera siempre sobre `$USER->id` (nunca acepta `userid`
  del cliente) y exige la capability `local/coursegradebadge:view`
  (permitida por defecto al rol *estudiante*).

## Desarrollo

Requisitos: Node.js 20+.

```bash
npm install
npm run build
```

`npm run build` compila `amd/src/injector.js` → `amd/build/injector.min.js`
(terser). El build se commitea al repositorio.

### Estructura

```text
├── version.php               # definición del plugin
├── db/                       # capabilities, hooks, servicios AJAX
├── classes/
│   ├── external/get_grades.php   # función externa (batch, $USER->id)
│   ├── grade_resolver.php        # cálculo y formato de calificaciones
│   ├── hook_callbacks.php        # carga del AMD solo en /my/
│   └── privacy/provider.php      # null_provider (GDPR)
├── amd/src/injector.js       # inyección del badge (MutationObserver)
├── templates/                # grade_badge.mustache
├── styles.css
└── lang/                     # en, es_mx
```

### Convenciones

- Conventional Commits (en español).
- Código sin comentarios salvo cabeceras de licencia GPL v3 canónicas.
- Sin `!important` en CSS; variables Bootstrap del tema.

## Verificación manual

1. Instalación limpia sin advertencias en `admin/index.php`.
2. Estudiante con nota → badge con el valor formateado según el display type
   del curso (real / porcentaje / letra).
3. Estudiante sin nota, curso sin ítems calificables o libro oculto →
   **sin** badge.
4. Docente/administrador → **sin** badge (respuesta `nopermission`).
5. Cambio de vista *Tarjetas → Lista → Resumen*: el badge persiste o reaparece.
6. Paginación / carga perezosa: el badge aparece en las nuevas tarjetas.
7. Consola del navegador: la respuesta de
   `local_coursegradebadge_get_grades` muestra el `reason` esperado por rol.

## Limitaciones conocidas

- No aparece en la **app Moodle Mobile**.
- La calificación mostrada es la **total del curso**, que durante el curso es
  una nota parcial (ver *Roadmap*: etiqueta explícita y enlace al reporte
  completo).
- Verificado con temas basados en Boost; temas de terceros que reescriban las
  tarjetas degradan silenciosamente.

## Roadmap

| Versión | Contenido |
| --- | --- |
| 0.2 | Badge con enlace al reporte de calificaciones; etiqueta "parcial"; ajustes `fw-bold` (Bootstrap 5) |
| 1.0 | Ajustes administrativos (`enabled`, filtro por categoría, estilo semáforo), caché MUC con observer de `user_graded`, CI con moodle-plugin-ci (PHPUnit/Behat), piloto SUAyED |

Ver `PLAN.md` para el roadmap completo (Fases 4–8) y el registro de riesgos.

## Soporte

- Incidencias: [GitHub Issues](https://github.com/jctovar/moodle-local_coursegradebadge/issues)
- Historial de cambios: `CHANGELOG.md`

## Licencia

Copyright © 2026 FES Iztacala, UNAM — Psicología SUAyED

Este programa es software libre: puedes redistribuirlo y/o modificarlo bajo
los términos de la [Licencia Pública General GNU](https://www.gnu.org/licenses/gpl-3.0.html)
versión 3 o posterior.
