#!/usr/bin/env bash
# Ejecuta un comando en la instancia de producción (como root) mediante AWS Systems Manager y muestra su salida.
# No requiere SSH ni abrir puertos. Termina con error si el comando falla en el servidor.
# Uso: scripts/run-on-server.sh "tail -n 20 /var/app/current/storage/logs/laravel.log"
set -euo pipefail
export AWS_PAGER=""

if [ $# -ne 1 ]; then
  echo "Uso: scripts/run-on-server.sh \"<comando>\"" >&2
  exit 2
fi

ENVIRONMENT="${EB_ENVIRONMENT:-pollo-charly-prod-api}"

INSTANCE_ID="$(aws ec2 describe-instances \
  --filters "Name=tag:elasticbeanstalk:environment-name,Values=$ENVIRONMENT" "Name=instance-state-name,Values=running" \
  --query 'Reservations[0].Instances[0].InstanceId' --output text)"

if [ -z "$INSTANCE_ID" ] || [ "$INSTANCE_ID" = "None" ]; then
  echo "No hay ninguna instancia en ejecución para el entorno $ENVIRONMENT." >&2
  exit 1
fi

PARAMETERS="$(python3 -c 'import json, sys; print(json.dumps({"commands": [sys.argv[1]]}))' "$1")"

COMMAND_ID="$(aws ssm send-command \
  --instance-ids "$INSTANCE_ID" \
  --document-name AWS-RunShellScript \
  --comment "run-on-server" \
  --parameters "$PARAMETERS" \
  --query 'Command.CommandId' --output text)"

aws ssm wait command-executed --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" 2>/dev/null || true

STATUS="$(aws ssm get-command-invocation --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" --query 'Status' --output text)"
aws ssm get-command-invocation --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" --query 'StandardOutputContent' --output text
aws ssm get-command-invocation --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" --query 'StandardErrorContent' --output text >&2

[ "$STATUS" = "Success" ] || { echo "El comando terminó con estado: $STATUS" >&2; exit 1; }
