#!/usr/bin/env bash
#
# Genera el .zip instalable del plugin Navidad TVS para subir a WordPress.
#
# Equivalente POSIX de build-zip.ps1. Copia solo los archivos de produccion a
# una carpeta temporal llamada "navidad-tvs" y la comprime en
# dist/navidad-tvs-<version>.zip. La version se lee del header "Version:" de
# navidad-tvs.php.
#
# Uso:
#   ./build-zip.sh
#   ./build-zip.sh --suffix rc1
#   ./build-zip.sh --out-dir /tmp/entregas --version 1.2.0

set -euo pipefail

SLUG="navidad-tvs"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUT_DIR="$ROOT/dist"
VERSION=""
SUFFIX=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --out-dir) OUT_DIR="$2"; shift 2 ;;
        --version) VERSION="$2";  shift 2 ;;
        --suffix)  SUFFIX="$2";   shift 2 ;;
        -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
        *) echo "Opcion desconocida: $1" >&2; exit 1 ;;
    esac
done

# --- Whitelist: solo esto entra al zip ------------------------------------
INCLUDE_FILES=(
    "navidad-tvs.php"
    "README.md"
)
INCLUDE_DIRS=(
    "includes"
    "assets"
    "templates"
    "languages"
)

# Patrones que nunca entran, aunque esten dentro de una carpeta incluida
EXCLUDE_PATTERNS=(
    ".gitkeep"
    ".DS_Store"
    "Thumbs.db"
    "*.log"
    "*.map"
    "*.psd"
    "*.zip"
    "*.csv"
)

esta_excluido() {
    local nombre="$1"
    for p in "${EXCLUDE_PATTERNS[@]}"; do
        # shellcheck disable=SC2053
        [[ "$nombre" == $p ]] && return 0
    done
    return 1
}

# --- Version ---------------------------------------------------------------
MAIN_FILE="$ROOT/navidad-tvs.php"
if [[ ! -f "$MAIN_FILE" ]]; then
    echo "No se encontro navidad-tvs.php en $ROOT. Corre el script desde la raiz del plugin." >&2
    exit 1
fi

if [[ -z "$VERSION" ]]; then
    VERSION="$(head -n 30 "$MAIN_FILE" | sed -n 's/^[[:space:]]*\*\?[[:space:]]*Version:[[:space:]]*\(.*[^[:space:]]\)[[:space:]]*$/\1/p' | head -n 1)"
    if [[ -z "$VERSION" ]]; then
        echo "No se pudo leer 'Version:' del header de navidad-tvs.php." >&2
        exit 1
    fi
fi

NAME="$SLUG-$VERSION"
[[ -n "$SUFFIX" ]] && NAME="$NAME-$SUFFIX"
ZIP_PATH="$OUT_DIR/$NAME.zip"

# --- Staging ---------------------------------------------------------------
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
STAGE_WP="$STAGE/$SLUG"
mkdir -p "$STAGE_WP"

copied=0

for f in "${INCLUDE_FILES[@]}"; do
    if [[ ! -f "$ROOT/$f" ]]; then
        echo "Aviso: falta $f (se omite)." >&2
        continue
    fi
    esta_excluido "$(basename "$f")" && continue
    cp "$ROOT/$f" "$STAGE_WP/$f"
    copied=$((copied + 1))
done

for d in "${INCLUDE_DIRS[@]}"; do
    [[ -d "$ROOT/$d" ]] || continue
    while IFS= read -r -d '' src; do
        base="$(basename "$src")"
        esta_excluido "$base" && continue
        rel="${src#"$ROOT/"}"
        mkdir -p "$STAGE_WP/$(dirname "$rel")"
        cp "$src" "$STAGE_WP/$rel"
        copied=$((copied + 1))
    done < <(find "$ROOT/$d" -type f -print0)
done

if [[ $copied -eq 0 ]]; then
    echo "No se copio ningun archivo. Revisa la whitelist." >&2
    exit 1
fi

# --- Chequeos de seguridad antes de empaquetar -----------------------------
if [[ ! -f "$STAGE_WP/navidad-tvs.php" ]]; then
    echo "El staging quedo sin navidad-tvs.php." >&2
    exit 1
fi

# Carpetas de desarrollo que nunca pueden viajar. Se comparan contra el PRIMER
# segmento de la ruta relativa, no contra la ruta completa: "game" en la raiz es
# el fuente TypeScript y no entra, pero "assets/game" es el bundle compilado y
# tiene que entrar.
CARPETAS_PROHIBIDAS="dev docker dist docs ayudas imagenes_apoyo game .git node_modules"

leaks=""
while IFS= read -r -d '' archivo; do
    rel="${archivo#"$STAGE_WP/"}"
    base="$(basename "$archivo")"
    primero="${rel%%/*}"

    case "$base" in
        docker-compose.yml|docker-compose.yaml|DOCKER.md|CLAUDE.md|*.sql|*.csv|build-zip.*)
            leaks="$leaks$rel"$'\n'; continue ;;
    esac

    for prohibida in $CARPETAS_PROHIBIDAS; do
        if [[ "$primero" == "$prohibida" ]]; then
            leaks="$leaks$rel"$'\n'
            break
        fi
    done
done < <(find "$STAGE_WP" -type f -print0)

if [[ -n "$leaks" ]]; then
    echo "Archivos de desarrollo en el paquete:" >&2
    echo "$leaks" >&2
    exit 1
fi

# --- Zip --------------------------------------------------------------------
mkdir -p "$OUT_DIR"
rm -f "$ZIP_PATH"

( cd "$STAGE" && zip -rq "$ZIP_PATH" "$SLUG" )

size_kb="$(du -k "$ZIP_PATH" | cut -f1)"

echo
echo "OK  $ZIP_PATH"
echo "    version: $VERSION | archivos: $copied | tamano: ${size_kb} KB"
echo "    raiz del zip: $SLUG/"
echo
echo "Subir en WordPress: Plugins > Anadir nuevo > Subir plugin > Instalar ahora."
