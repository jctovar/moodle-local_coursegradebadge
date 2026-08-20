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
