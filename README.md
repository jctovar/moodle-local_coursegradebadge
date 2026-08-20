# Course Grade Badge — `local_coursegradebadge`

[![CI](https://github.com/jctovar/moodle-local_coursegradebadge/actions/workflows/ci.yml/badge.svg)](https://github.com/jctovar/moodle-local_coursegradebadge/actions/workflows/ci.yml)
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
- **Totales con ítems ocultos**: si el curso contiene algún ítem oculto, el
  badge solo se muestra cuando el ajuste `report_user_showtotalsifcontainhidden`
  del curso (o su valor de sitio) es *Mostrar el total real*. En cualquier otro
  caso el badge se omite, para no revelar por agregación lo que el informe de
  calificaciones deja en blanco o recalcula.
- La función externa opera siempre sobre `$USER->id` (nunca acepta `userid`
  del cliente) y exige `moodle/grade:view` más la capability
  `local/coursegradebadge:view` (permitida por defecto al rol *estudiante*).

## Desarrollo

### Compilar el módulo AMD

El build usa el **grunt de Moodle**, no una cadena propia, de modo que la salida
sea exactamente la que espera `moodle-plugin-ci grunt`. Requiere el plugin
dentro de un árbol de Moodle y Node.js 22 (`lts/jod`, ver `.nvmrc` de Moodle):

```bash
# una sola vez, en la raíz del árbol de Moodle
npm install

# con el plugin en local/coursegradebadge
npx grunt amd --root=local/coursegradebadge
```

Genera `amd/build/injector.min.js` y `amd/build/injector.min.js.map` (rollup).
**Ambos se commitean al repositorio**; la CI falla si no coinciden con lo que
produce grunt a partir de `amd/src/injector.js`.

Para trabajar sin copiar el plugin, un enlace simbólico basta:

```bash
ln -s /ruta/a/moodle-local_coursegradebadge <moodle>/local/coursegradebadge
```

### Empaquetado

```bash
bin/package.sh            # empaqueta HEAD
bin/package.sh v0.1.2     # empaqueta un tag concreto
```

Genera `dist/coursegradebadge-<release>.zip` con **`coursegradebadge/` como
único directorio raíz**, que es lo que exige el instalador de Moodle (no el
nombre del repositorio ni el del componente). Solo empaqueta ficheros
commiteados; los de desarrollo se excluyen vía `export-ignore` en
`.gitattributes`. El script aborta si el árbol está sucio, si el tag no
coincide con `$plugin->release` o si la estructura del ZIP no es la esperada.

### Integración continua

`.github/workflows/ci.yml` ejecuta **moodle-plugin-ci** (phplint, Code Checker,
PHPDoc Checker, validate, savepoints, Mustache lint, grunt, PHPUnit, Behat)
sobre la matriz Moodle 5.0/5.1 × PHP 8.2/8.3, más una pasada en MariaDB.

### Estructura

```text
├── version.php               # definición del plugin
├── db/                       # capabilities, hooks, servicios AJAX
├── classes/
│   ├── external/get_grades.php   # función externa (batch, $USER->id)
│   ├── grade_resolver.php        # cálculo y formato de calificaciones
│   ├── hook_callbacks.php        # carga del AMD solo en /my/
│   └── privacy/provider.php      # null_provider (GDPR)
├── amd/
│   ├── src/injector.js       # inyección del badge (MutationObserver)
│   └── build/                # salida de `grunt amd` (commiteada)
├── templates/                # grade_badge.mustache
├── styles.css
└── lang/                     # en, es_mx
```

### Convenciones

- Conventional Commits (en español).
- Cabeceras de licencia GPL v3 canónicas y PHPDoc de fichero, clase y método
  en todos los ficheros (lo exige el PHPDoc Checker de Moodle). Fuera de ahí,
  comentarios solo donde el *porqué* no se deduzca del código.
- Sin `!important` en CSS; variables Bootstrap del tema.

## Verificación manual

1. Instalación limpia sin advertencias en `admin/index.php`.
2. Estudiante con nota → badge con el valor formateado según el display type
   del curso (real / porcentaje / letra).
3. Estudiante sin nota, curso sin ítems calificables o libro oculto →
   **sin** badge.
   - Curso con un ítem oculto y *Ocultar totales si contienen ítems ocultos*
     → **sin** badge; cambiando el ajuste a *Mostrar el total real* → badge
     visible.
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
| 1.0 | Ajustes administrativos (`enabled`, filtro por categoría, estilo semáforo), caché MUC con observer de `user_graded`, suite PHPUnit/Behat, piloto SUAyED |

Ver `PLAN.md` para el roadmap completo (Fases 4–8) y el registro de riesgos.

## Soporte

- Incidencias: [GitHub Issues](https://github.com/jctovar/moodle-local_coursegradebadge/issues)
- Historial de cambios: `CHANGELOG.md`

## Licencia

Copyright © 2026 FES Iztacala, UNAM — Psicología SUAyED

Este programa es software libre: puedes redistribuirlo y/o modificarlo bajo
los términos de la [Licencia Pública General GNU](https://www.gnu.org/licenses/gpl-3.0.html)
versión 3 o posterior.
