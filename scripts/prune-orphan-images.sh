#!/usr/bin/env bash
# Elimina de Cloudinary las imágenes de platillos que ningún platillo usa (huérfanas), ejecutando
# `php artisan images:prune-orphans` en la instancia de producción mediante SSM.
# Uso: scripts/prune-orphan-images.sh             (elimina las huérfanas con más de 24 horas)
#      DRY_RUN=1 scripts/prune-orphan-images.sh   (solo muestra cuántas habría, sin borrar nada)
#      HOURS=72 scripts/prune-orphan-images.sh    (cambia la antigüedad mínima)
set -euo pipefail
export AWS_PAGER=""

ENVIRONMENT="${EB_ENVIRONMENT:-pollo-charly-prod-api}"
HOURS="${HOURS:-24}"

if ! [[ "$HOURS" =~ ^[0-9]+$ ]]; then
  echo "HOURS debe ser un número entero." >&2
  exit 1
fi

OPTIONS="--hours=$HOURS"
if [ "${DRY_RUN:-0}" = "1" ]; then
  OPTIONS="$OPTIONS --dry-run"
fi

INSTANCE_ID="$(aws ec2 describe-instances \
  --filters "Name=tag:elasticbeanstalk:environment-name,Values=$ENVIRONMENT" "Name=instance-state-name,Values=running" \
  --query 'Reservations[0].Instances[0].InstanceId' --output text)"

COMMAND_ID="$(aws ssm send-command \
  --instance-ids "$INSTANCE_ID" \
  --document-name AWS-RunShellScript \
  --comment "Limpieza de imágenes huérfanas" \
  --parameters "commands=[\"cd /var/app/current\",\"runuser -u webapp -- php artisan images:prune-orphans $OPTIONS --no-interaction --no-ansi\"]" \
  --query 'Command.CommandId' --output text)"

aws ssm wait command-executed --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" || true

aws ssm get-command-invocation \
  --command-id "$COMMAND_ID" \
  --instance-id "$INSTANCE_ID" \
  --query '[Status,StandardOutputContent,StandardErrorContent]' \
  --output text
