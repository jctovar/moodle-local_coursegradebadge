#!/usr/bin/env bash
#
# Genera el ZIP distribuible del plugin.
#
# El instalador de Moodle exige que el ZIP contenga un unico directorio raiz
# con el nombre exacto de la carpeta destino bajo local/, es decir
# "coursegradebadge" (no "local_coursegradebadge" ni el nombre del repo).
# Este script lo garantiza usando `git archive --prefix`.
#
# Solo se empaquetan ficheros commiteados. Los ficheros de desarrollo se
# excluyen mediante las reglas export-ignore de .gitattributes.
#
# Uso:
#   bin/package.sh [ref] [--allow-dirty]
#
# Ejemplos:
#   bin/package.sh            # empaqueta HEAD
#   bin/package.sh v0.1.1     # empaqueta el tag v0.1.1
#
# Copyright 2026 FES Iztacala, UNAM - Psicologia SUAyED
# License https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later

set -euo pipefail

PLUGIN_DIR="coursegradebadge"
REF="HEAD"
ALLOW_DIRTY=0

for arg in "$@"; do
    case "$arg" in
        --allow-dirty) ALLOW_DIRTY=1 ;;
        -h|--help) sed -n '3,22p' "$0"; exit 0 ;;
        *) REF="$arg" ;;
    esac
done

cd "$(git rev-parse --show-toplevel)"

if ! git rev-parse --verify --quiet "$REF^{commit}" >/dev/null; then
    echo "error: la referencia '$REF' no existe" >&2
    exit 1
fi

if [ "$ALLOW_DIRTY" -eq 0 ] && [ "$REF" = "HEAD" ] && ! git diff-index --quiet HEAD --; then
    echo "error: hay cambios sin commitear; el ZIP solo incluiria lo commiteado." >&2
    echo "       commitea primero, o pasa --allow-dirty si sabes lo que haces." >&2
    exit 1
fi

# La version viaja en version.php, no en el nombre del fichero, pero se usa
# para nombrar el ZIP de forma reconocible.
RELEASE=$(git show "$REF:version.php" | sed -n "s/^\$plugin->release *= *'\([^']*\)'.*/\1/p")
VERSION=$(git show "$REF:version.php" | sed -n "s/^\$plugin->version *= *\([0-9]*\).*/\1/p")

if [ -z "$RELEASE" ] || [ -z "$VERSION" ]; then
    echo "error: no se pudo leer \$plugin->release / \$plugin->version de version.php en $REF" >&2
    exit 1
fi

# Si se empaqueta un tag vX.Y.Z, debe coincidir con el release declarado.
if [[ "$REF" =~ ^v[0-9] ]] && [ "${REF#v}" != "$RELEASE" ]; then
    echo "error: el tag $REF no coincide con \$plugin->release = '$RELEASE'" >&2
    exit 1
fi

mkdir -p dist
ZIP="dist/${PLUGIN_DIR}-${RELEASE}.zip"
rm -f "$ZIP"

git archive --format=zip --prefix="${PLUGIN_DIR}/" -o "$ZIP" "$REF"

# Verificacion: todo debe colgar de un unico directorio raiz correcto, y los
# ficheros de desarrollo no deben haberse colado.
#
# El listado se captura una sola vez. Encadenar `unzip -Z1 | grep -q` mata unzip
# con SIGPIPE en cuanto grep encuentra la coincidencia, y con `set -o pipefail`
# eso hace fallar la comprobacion segun donde caiga la coincidencia en la lista.
ENTRIES=$(unzip -Z1 "$ZIP")

BAD_ROOT=$(printf '%s\n' "$ENTRIES" | grep -v "^${PLUGIN_DIR}/" || true)
if [ -n "$BAD_ROOT" ]; then
    echo "error: hay entradas fuera de ${PLUGIN_DIR}/:" >&2
    echo "$BAD_ROOT" >&2
    exit 1
fi

for unwanted in "${PLUGIN_DIR}/.github/" "${PLUGIN_DIR}/bin/" "${PLUGIN_DIR}/docs/" \
                "${PLUGIN_DIR}/PLAN.md" "${PLUGIN_DIR}/node_modules/"; do
    if printf '%s\n' "$ENTRIES" | grep -q "^${unwanted}"; then
        echo "error: '$unwanted' no deberia estar en el ZIP (revisa .gitattributes)" >&2
        exit 1
    fi
done

for required in "${PLUGIN_DIR}/version.php" "${PLUGIN_DIR}/LICENSE" \
                "${PLUGIN_DIR}/amd/build/injector.min.js" \
                "${PLUGIN_DIR}/lang/en/local_coursegradebadge.php"; do
    if ! printf '%s\n' "$ENTRIES" | grep -q "^${required}$"; then
        echo "error: falta '$required' en el ZIP" >&2
        exit 1
    fi
done

echo "OK  $ZIP"
echo "    release   $RELEASE"
echo "    version   $VERSION"
echo "    ref       $REF ($(git rev-parse --short "${REF}^{commit}"))"
echo "    ficheros  $(printf "%s\n" "$ENTRIES" | grep -cv "/$")"
echo "    raiz      ${PLUGIN_DIR}/"
