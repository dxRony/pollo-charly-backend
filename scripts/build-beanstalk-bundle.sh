#!/usr/bin/env bash
# Empaqueta la API (con dependencias de producción ya instaladas) en un .zip para Elastic Beanstalk.
# Uso: scripts/build-beanstalk-bundle.sh [etiqueta-de-version]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-$(date +%Y%m%d%H%M%S)}"
OUT="$ROOT/build"
STAGE="$OUT/app"
BUNDLE="$OUT/pollo-charly-api-$VERSION.zip"

rm -rf "$OUT"
mkdir -p "$STAGE"

rsync -a \
  --exclude='/.git/' \
  --exclude='/.github/' \
  --exclude='/build/' \
  --exclude='/infra/' \
  --exclude='/tests/' \
  --exclude='/node_modules/' \
  --exclude='/vendor/' \
  --exclude='/public/storage' \
  --exclude='/.env' \
  --exclude='/.env.*' \
  --exclude='/storage/logs/*' \
  --exclude='/storage/framework/cache/data/*' \
  --exclude='/storage/framework/sessions/*' \
  --exclude='/storage/framework/views/*' \
  --exclude='/bootstrap/cache/*.php' \
  --exclude='/database/*.sqlite' \
  "$ROOT/" "$STAGE/"

composer install \
  --working-dir="$STAGE" \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction

find "$STAGE/.platform/hooks" -type f -name '*.sh' -exec chmod +x {} +

(cd "$STAGE" && zip -qr "$BUNDLE" .)

echo "Paquete generado: $BUNDLE ($(du -h "$BUNDLE" | cut -f1))"
