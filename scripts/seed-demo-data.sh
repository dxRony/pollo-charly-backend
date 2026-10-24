#!/usr/bin/env bash
# Carga datos ficticios de demostración en producción (catálogo, ventas, compras e inventario de los últimos días),
# ejecutando `php artisan demo:seed` en la instancia mediante SSM. Los datos se generan con las reglas reales del
# sistema, de modo que reportes, stock y alertas son coherentes entre sí.
#
# Requisitos: la base de datos debe estar recién reiniciada (scripts/reset-database.sh); si ya tiene datos, la carga
# se rechaza sin modificar nada. Para quitar la demostración después, se vuelve a ejecutar reset-database.sh.
#
# Las contraseñas de los usuarios demo se muestran UNA sola vez al final y no se guardan en el servidor. Quedan en el
# historial de Systems Manager (visible solo para usuarios IAM con acceso a esta instancia).
#
# Uso: scripts/seed-demo-data.sh                     (30 días de historial)
#      DAYS=14 scripts/seed-demo-data.sh             (otro historial, de 1 a 90 días)
#      WITH_SNAPSHOT=1 scripts/seed-demo-data.sh     (crea antes un snapshot manual de RDS)
set -euo pipefail
export AWS_PAGER=""

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_INSTANCE="${DB_INSTANCE_ID:-pollo-charly-prod-mysql}"
DAYS="${DAYS:-30}"
TIMEOUT_MINUTES=20

if ! [[ "$DAYS" =~ ^[0-9]+$ ]] || [ "$DAYS" -lt 1 ] || [ "$DAYS" -gt 90 ]; then
  echo "DAYS debe ser un número entre 1 y 90." >&2
  exit 1
fi

read -r -p "Se cargarán datos FICTICIOS ($DAYS días) en PRODUCCIÓN. Escribe CARGAR para continuar: " CONFIRM
if [ "$CONFIRM" != "CARGAR" ]; then
  echo "Cancelado."
  exit 1
fi

if [ "${WITH_SNAPSHOT:-0}" = "1" ]; then
  SNAPSHOT="pre-demo-$(date +%Y%m%d%H%M%S)"
  echo "Creando snapshot $SNAPSHOT (puede tardar unos minutos)..."
  aws rds create-db-snapshot --db-instance-identifier "$DB_INSTANCE" --db-snapshot-identifier "$SNAPSHOT" > /dev/null
  aws rds wait db-snapshot-available --db-snapshot-identifier "$SNAPSHOT"
fi

LOG=/tmp/demo-seed.log
EXIT_FILE=/tmp/demo-seed.exit

echo "Iniciando la carga en el servidor..."
"$ROOT/scripts/run-on-server.sh" "rm -f $LOG $EXIT_FILE; setsid nohup sh -c 'cd /var/app/current && (runuser -u webapp -- php artisan demo:seed --days=$DAYS --force --no-interaction --no-ansi > $LOG 2>&1; echo \$? > $EXIT_FILE)' > /dev/null 2>&1 &" > /dev/null

EXIT_CODE=""
for _ in $(seq 1 $((TIMEOUT_MINUTES * 6))); do
  sleep 10
  EXIT_CODE="$("$ROOT/scripts/run-on-server.sh" "cat $EXIT_FILE 2>/dev/null || true" | tr -d '[:space:]')"
  [ -n "$EXIT_CODE" ] && break
  printf '.'
done
echo

if [ -z "$EXIT_CODE" ]; then
  echo "La carga sigue en curso después de $TIMEOUT_MINUTES minutos. Revisa el avance con:" >&2
  echo "  ./scripts/run-on-server.sh \"cat $LOG\"" >&2
  exit 1
fi

"$ROOT/scripts/run-on-server.sh" "cat $LOG; rm -f $LOG $EXIT_FILE"

if [ "$EXIT_CODE" != "0" ]; then
  echo "La carga terminó con errores (código $EXIT_CODE)." >&2
  exit 1
fi
