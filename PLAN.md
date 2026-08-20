# PLAN.md — `local_coursegradebadge`

Roadmap de desarrollo del plugin que muestra la calificación total del curso
en las tarjetas del bloque **Vista general de cursos** (`block_myoverview`),
justo debajo de la barra de avance.

---

## 1. Resumen ejecutivo

| Campo | Valor |
| --- | --- |
| Nombre del plugin | `local_coursegradebadge` |
| Tipo | `local` (plugin local, sin tocar core) |
| Versión objetivo de Moodle | 5.0 / 5.1+ (`$plugin->requires` = 2025041400, Moodle 5.0.0) |
| PHP mínimo | 8.2 |
| Licencia | GPL v3 |
| Destino | FES Iztacala — Psicología SUAyED (uso interno) |
| Estrategia | Función externa (webservice AJAX) + módulo AMD que inyecta el badge en el DOM |

### Decisiones de diseño confirmadas

1. **Formato de la calificación**: respeta la configuración del curso
   (`grade_report_user` / display type del `course_item`: real, porcentaje o letra).
2. **Roles**: solo estudiantes ven el badge. Docentes, gestores y administradores
   no reciben badge (la función externa devuelve `grade = null` con
   `reason = 'nopermission'`).
3. **Sin calificación / libro oculto**: el badge simplemente no se renderiza
   (`grade = null` con `reason = 'nograde'` o `'hidden'`), de modo que el
   frontend distingue "sin permiso" de "sin nota".
4. **Ubicación visual**: debajo de la barra de progreso (`.progress` de la tarjeta),
   antes del bloque de acciones.

---

## 2. Arquitectura

```text
Dashboard / Mis cursos
        │
        ├─ block_myoverview renderiza tarjetas (Mustache + AMD core)
        │
        └─ [hook] local_coursegradebadge inyecta módulo AMD
                    │
                    ├─ MutationObserver detecta tarjetas renderizadas
                    ├─ Recolecta courseids visibles
                    ├─ 1 sola llamada AJAX (batch)
                    │
                    └─ external_function: local_coursegradebadge_get_grades
                              │
                              ├─ Valida contexto y capability por curso
                              ├─ grade_get_course_grades() / grade_item::fetch_course_item()
                              ├─ Aplica display type del curso
                              └─ Devuelve [{courseid, formatted, raw, url, reason}]
```

### Por qué esta arquitectura

- **No se sobrescriben plantillas Mustache de core.** El markup de
  `block_myoverview` ha cambiado entre 4.x y 5.x; un override de plantilla
  obligaría a re-diff en cada actualización mayor.
- **Una sola petición AJAX por vista** en lugar de N peticiones por tarjeta.
- **El cálculo de calificación siempre ocurre en servidor**, respetando
  `hideit`, `showtotalsifcontainhidden` y permisos del gradebook.

---

## 3. Estructura de archivos

```text
local/coursegradebadge/
├── version.php
├── settings.php
├── db/
│   ├── services.php               # declaración de la external function (ajax; sin servicio externo)
│   ├── access.php                 # capability local/coursegradebadge:view
│   ├── caches.php                 # caché application de calificaciones
│   ├── events.php                 # observer de \core\event\user_graded (invalidación de caché)
│   └── hooks.php                  # Hooks API (Moodle 4.4+)
├── classes/
│   ├── external/
│   │   └── get_grades.php         # extends core_external\external_api
│   ├── event/
│   │   └── observer.php           # invalida caché al calificar
│   ├── hook_callbacks.php
│   ├── grade_resolver.php         # lógica de cálculo y formato
│   └── privacy/
│       └── provider.php           # null_provider (GDPR / Plugins Directory)
├── amd/
│   ├── src/
│   │   └── injector.js
│   └── build/
│       └── injector.min.js
├── templates/
│   └── grade_badge.mustache
├── styles.css
├── lang/
│   ├── en/local_coursegradebadge.php
│   └── es_mx/local_coursegradebadge.php
├── tests/
│   ├── get_grades_test.php
│   └── behat/
│       └── grade_badge.feature
├── PLAN.md
├── README.md
└── CHANGELOG.md
```

---

## 4. Fases de desarrollo

### Fase 0 — Preparación (0.5 día)

- [ ] Crear repositorio Git (`moodle-local_coursegradebadge`)
- [ ] Configurar `.gitignore`, `.editorconfig`, `grunt` para compilar AMD
      (documentar el comando de build `grunt amd` en el `README.md`)
- [ ] Configurar CI con **moodle-plugin-ci** desde el primer commit
      (PHPUnit, Behat, Moodle Code Checker, ESLint; matriz Moodle 5.0/5.1 × PHP 8.2/8.3)
- [ ] Levantar instancia de pruebas Moodle 5.x aislada (no producción)
- [ ] Cargar curso semilla con: notas visibles, notas ocultas, curso sin ítems calificables
- [ ] Definir política de versionado (`YYYYMMDDXX`)

**Entregable**: repo inicializado + entorno de pruebas reproducible.

---

### Fase 1 — Esqueleto del plugin (0.5 día)

- [ ] `version.php` con `component`, `requires = 2025041400` (Moodle 5.0.0),
      `maturity = MATURITY_ALPHA`
- [ ] `db/access.php` — capability `local/coursegradebadge:view`
      (`archetypes => student`, contexto `CONTEXT_COURSE`)
- [ ] `classes/privacy/provider.php` — `null_provider` (el plugin no almacena
      datos personales; requisito de GDPR y del Plugins Directory)
- [ ] Cadenas de idioma base (en + es_mx)
- [ ] Instalación limpia sin errores en `admin/index.php`

**Criterio de aceptación**: el plugin aparece en
*Administración del sitio → Plugins → Plugins locales* sin warnings.

---

### Fase 2 — Backend: función externa (1.5 días)

- [ ] `classes/grade_resolver.php`
  - [ ] `get_course_grade(int $courseid, int $userid): ?stdClass`
  - [ ] Usar `grade_item::fetch_course_item($courseid)`
  - [ ] Obtener `grade_grade` del usuario; respetar `is_hidden()` y `is_excluded()`
  - [ ] Formatear con `grade_format_gradevalue()` según `$gradeitem->get_displaytype()`
  - [ ] Retornar `null` si: sin nota, item oculto, o `$course->showgrades == 0`
- [ ] `classes/external/get_grades.php`
  - [ ] `execute_parameters()` — array de `courseid` (máx. 50 por llamada);
        **nunca aceptar `userid` del cliente**: operar siempre sobre `$USER->id`
  - [ ] Por cada curso: `validate_context(context_course::instance($id))`
  - [ ] Exigir `local/coursegradebadge:view` en el contexto del curso
  - [ ] Excluir usuarios con `moodle/grade:viewall` (docentes/gestores →
        `grade = null`, `reason = 'nopermission'`)
  - [ ] `execute_returns()` — `courseid`, `formatted`, `percentage`, `gradeurl`, `reason`
- [ ] `db/services.php` — declaración con `ajax => true`, `loginrequired => true`;
      **sin** servicio externo ni tokens (uso AJAX interno únicamente)
- [ ] PHPUnit de `grade_resolver` en esta misma fase (no diferir a Fase 5):
      nota visible, sin nota, ítem oculto, `showgrades = 0`, los tres display types
- [ ] **Rendimiento como requisito**: el resolver ejecuta **1 consulta agregada**
      para los N cursos (`IN` sobre `{grade_grades}` con `itemtype = 'course'`),
      no un loop de `grade_get_course_grades()`

**Criterio de aceptación**: `curl` / consola JS a
`/lib/ajax/service.php` devuelve JSON correcto para un estudiante
y `null` para un docente.

**Riesgo**: el rendimiento con usuarios matriculados en 30+ cursos.
*Mitigación*: la consulta agregada (requisito de esta fase) + caché MUC con
invalidación por evento (Fase 4); la prueba de carga (Fase 5) afirma un máximo
de consultas SQL, no solo el tiempo de respuesta.

---

### Fase 3 — Frontend: inyección del badge (1.5 días)

- [ ] `amd/src/injector.js`
  - [ ] Detectar contenedor `[data-region="courses-view"]`
  - [ ] `MutationObserver` para re-inyectar tras paginación, cambio de
        vista (tarjetas/lista/resumen) y filtros de `myoverview`
  - [ ] Extraer `courseid` de `[data-course-id]` en cada tarjeta
  - [ ] Debounce de 150 ms antes de disparar la llamada AJAX
  - [ ] Insertar el nodo del badge **después** de `.progress` /
        `[data-region="progress"]`, con fallback al pie de la tarjeta
  - [ ] Idempotencia: no duplicar si ya existe `.lcgb-badge`
- [ ] `templates/grade_badge.mustache` — badge accesible
      (`aria-label`, `aria-live="polite"` porque el contenido llega de forma
      asíncrona, no depender solo de color)
- [ ] `styles.css` — usar variables Bootstrap del tema, sin `!important`
- [ ] `classes/hook_callbacks.php` — cargar AMD solo en
      `/my/courses.php` y `/my/index.php`

**Criterio de aceptación**: badge visible y alineado bajo la barra de
progreso en vista *Tarjetas*, *Lista* y *Resumen*.

**Riesgo**: `myoverview` re-renderiza el DOM al cambiar de vista y borra el badge.
*Mitigación*: el `MutationObserver` es obligatorio, no opcional.

---

### Fase 4 — Configuración administrativa (0.5 día)

- [ ] `settings.php` con:
  - [ ] `enabled` — activar/desactivar globalmente
  - [ ] `showonlyifprogress` — mostrar solo si el curso tiene seguimiento
  - [ ] `categoryfilter` — restringir a categorías específicas (útil para SUAyED)
  - [ ] `badgestyle` — neutro / semáforo por rango de nota
  - [ ] `cachettl` — TTL del caché en segundos (default 300)
- [ ] `db/caches.php` — definición de caché `application` para calificaciones
      (clave por `userid + courseid`)
- [ ] `db/events.php` + `classes/event/observer.php` — observer de
      `\core\event\user_graded` que invalida la entrada de caché afectada;
      el TTL queda solo como red de seguridad

**Criterio de aceptación**: cambiar cada ajuste produce el efecto esperado
sin purgar cachés manualmente (salvo `cachettl`), y calificar una actividad
como docente actualiza el badge del estudiante sin esperar al TTL.

---

### Fase 5 — Pruebas (1 día)

- [ ] **PHPUnit** (`tests/get_grades_test.php`) — completar sobre la base de
      los tests de `grade_resolver` escritos en Fase 2:
  - [ ] Estudiante con nota → devuelve valor formateado
  - [ ] Estudiante sin nota → `null` con `reason = 'nograde'`
  - [ ] Ítem de curso oculto → `null` con `reason = 'hidden'`
  - [ ] `$course->showgrades = 0` → `null`
  - [ ] Docente → `null` con `reason = 'nopermission'`
  - [ ] Usuario no matriculado → excepción de contexto
  - [ ] Cada display type (real, porcentaje, letra) formatea correctamente
  - [ ] Letras con escala personalizada a nivel curso (override de letras)
  - [ ] Invalidación de caché al dispararse `\core\event\user_graded`
- [ ] **Behat** (`tests/behat/grade_badge.feature`)
  - [ ] Badge visible para estudiante en Mis cursos
  - [ ] Badge ausente para docente
  - [ ] Badge persiste al cambiar de vista tarjetas → lista
  - [ ] Badge aparece tras paginación / carga perezosa de tarjetas
- [ ] **Moodle Code Checker** + **PHPDoc checker** sin errores
- [ ] **ESLint** sobre `amd/src/` sin errores
- [ ] Prueba de carga: usuario con 40 cursos → afirmar tiempo de la llamada
      AJAX **y** un máximo de consultas SQL (consulta agregada, no N+1)

**Criterio de aceptación**: CI en verde, cobertura ≥ 80 % en `grade_resolver`.

---

### Fase 6 — Accesibilidad y responsive (0.5 día)

- [ ] Contraste WCAG AA en todos los estilos de badge
- [ ] Navegación por teclado si el badge enlaza al reporte de calificaciones
- [ ] Verificar en móvil (tarjetas apiladas) que el badge no rompa el layout
- [ ] Probar con la app Moodle Mobile (el badge **no** aparecerá ahí —
      documentarlo como limitación conocida)

---

### Fase 7 — Despliegue piloto (0.5 día)

- [ ] Desplegar en instancia de staging detrás de Nginx/Cloudflare
- [ ] Verificar que el endpoint AJAX no sea bloqueado por reglas de Cloudflare
- [ ] Piloto con 1–2 grupos de Psicología SUAyED
- [ ] Recolectar retroalimentación de estudiantes y docentes (1 semana)
- [ ] Métricas de salida del piloto (definidas de antemano): p95 de la llamada
      AJAX en producción, tasa de errores JS en consola, n.º de incidencias
      de soporte relacionadas con la nota mostrada (R5)
- [ ] Ajustes según hallazgos

---

### Fase 8 — Producción y documentación (0.5 día)

- [ ] `README.md` con instalación, capturas y matriz de compatibilidad
- [ ] `CHANGELOG.md` v1.0.0
- [ ] Tag `v1.0.0` en Git
- [ ] Cambiar `maturity` a `MATURITY_STABLE`
- [ ] Despliegue por semestre/instancia según ventana de mantenimiento
- [ ] Procedimiento de rollback documentado: desactivar vía setting `enabled`
      (sin desinstalar el plugin)
- [ ] (Opcional) Publicación en el Moodle Plugins Directory

---

## 5. Cronograma estimado

| Fase | Duración | Acumulado |
| --- | --- | --- |
| 0 — Preparación | 0.5 d | 0.5 d |
| 1 — Esqueleto | 0.5 d | 1.0 d |
| 2 — Backend | 1.5 d | 2.5 d |
| 3 — Frontend | 1.5 d | 4.0 d |
| 4 — Configuración | 0.5 d | 4.5 d |
| 5 — Pruebas | 1.0 d | 5.5 d |
| 6 — Accesibilidad | 0.5 d | 6.0 d |
| 7 — Piloto | 0.5 d + 1 sem. observación | 6.5 d |
| 8 — Producción | 0.5 d | 7.0 d |

**Total de esfuerzo activo**: ~7 días-persona.
**Calendario realista**: 3 semanas incluyendo el periodo de piloto.

---

## 6. Registro de riesgos

| # | Riesgo | Impacto | Prob. | Mitigación |
| --- | --- | --- | --- | --- |
| R1 | Cambio de markup de `block_myoverview` en un mayor de Moodle | Alto | Media | Selectores defensivos con fallback; prueba Behat que falle ruidosamente |
| R2 | Fuga de calificaciones a usuarios sin permiso | Crítico | Baja | `validate_context()` por curso + PHPUnit obligatorio antes de merge |
| R3 | Degradación de rendimiento con muchos cursos | Medio | Media | Consulta agregada + caché MUC con TTL configurable |
| R4 | Cloudflare bloquea o cachea `/lib/ajax/service.php` | Medio | Baja | Regla de bypass de caché; verificar en Fase 7 |
| R5 | Confusión estudiantil por nota parcial interpretada como final | Medio | Alta | Etiqueta explícita ("Calificación parcial") + enlace al reporte completo |
| R6 | Conflicto con temas de terceros que reescriben las tarjetas | Bajo | Media | Documentar temas verificados; degradar silenciosamente |
| R7 | Caché con notas obsoletas tras calificar | Medio | Media | Observer de `\core\event\user_graded` + TTL corto como red de seguridad |
| R8 | Tarjetas que entran al DOM por paginación / lazy-load sin disparar el observer en el orden esperado | Medio | Media | Caso Behat con paginación e infinite scroll, no solo cambio de vista |
| R9 | Display type "letra" con escalas personalizadas por curso | Bajo | Media | Caso PHPUnit con letras override a nivel curso |

---

## 7. Fuera de alcance (v1.0)

- Calificaciones por categoría o por actividad individual
- Vista para docentes (promedio del grupo)
- Soporte para la app Moodle Mobile
- Gráficas o histórico de evolución de la nota
- Exportación de datos

Estos puntos quedan como candidatos para v1.1+.

---

## 8. Definición de "terminado"

Una fase se considera completa cuando:

1. El código pasa Moodle Code Checker y ESLint sin errores.
2. Existen pruebas automatizadas para el comportamiento nuevo.
3. La funcionalidad fue verificada manualmente en Moodle 5.x con al menos
   un rol de estudiante y uno de docente.
4. Los cambios están commiteados con Conventional Commits y documentados
   en el `CHANGELOG.md`.
