#!/usr/bin/env bash
# Borra TODOS los datos del entorno de producción y lo deja como un despliegue nuevo:
# migraciones aplicadas, catálogos y administrador inicial (la contraseña sigue siendo la de Parameter Store).
# Uso: scripts/reset-database.sh            (pide confirmación)
#      WITH_SNAPSHOT=1 scripts/reset-database.sh   (antes crea un snapshot manual de RDS como respaldo)
set -euo pipefail
export AWS_PAGER=""

ENVIRONMENT="${EB_ENVIRONMENT:-pollo-charly-prod-api}"
DB_INSTANCE="${DB_INSTANCE_ID:-pollo-charly-prod-mysql}"

read -r -p "Esto BORRA todos los datos de $ENVIRONMENT. Escribe RESETEAR para continuar: " CONFIRM
if [ "$CONFIRM" != "RESETEAR" ]; then
  echo "Cancelado."
  exit 1
fi

if [ "${WITH_SNAPSHOT:-0}" = "1" ]; then
  SNAPSHOT="pre-reset-$(date +%Y%m%d%H%M%S)"
  echo "Creando snapshot $SNAPSHOT (puede tardar unos minutos)..."
  aws rds create-db-snapshot --db-instance-identifier "$DB_INSTANCE" --db-snapshot-identifier "$SNAPSHOT" > /dev/null
  aws rds wait db-snapshot-available --db-snapshot-identifier "$SNAPSHOT"
fi

INSTANCE_ID="$(aws ec2 describe-instances \
  --filters "Name=tag:elasticbeanstalk:environment-name,Values=$ENVIRONMENT" "Name=instance-state-name,Values=running" \
  --query 'Reservations[0].Instances[0].InstanceId' --output text)"

COMMAND_ID="$(aws ssm send-command \
  --instance-ids "$INSTANCE_ID" \
  --document-name AWS-RunShellScript \
  --comment "Reinicio de la base de datos" \
  --parameters 'commands=["cd /var/app/current","runuser -u webapp -- php artisan migrate:fresh --seed --seeder=ProductionSeeder --force --no-interaction --no-ansi"]' \
  --query 'Command.CommandId' --output text)"

aws ssm wait command-executed --command-id "$COMMAND_ID" --instance-id "$INSTANCE_ID" || true

aws ssm get-command-invocation \
  --command-id "$COMMAND_ID" \
  --instance-id "$INSTANCE_ID" \
  --query '[Status,StandardErrorContent]' \
  --output text

echo "Listo. Las sesiones anteriores quedaron invalidadas: vuelve a iniciar sesión."
