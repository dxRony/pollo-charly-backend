#!/usr/bin/env bash
# Sube un paquete a Elastic Beanstalk y despliega esa versión en el entorno.
# Uso: scripts/deploy-beanstalk.sh build/pollo-charly-api-<version>.zip
set -euo pipefail
export AWS_PAGER=""

BUNDLE="${1:?Uso: scripts/deploy-beanstalk.sh build/<paquete>.zip}"
APPLICATION="${EB_APPLICATION:-pollo-charly-prod-api}"
ENVIRONMENT="${EB_ENVIRONMENT:-pollo-charly-prod-api}"
VERSION="$(basename "$BUNDLE" .zip)"

BUCKET="$(aws elasticbeanstalk create-storage-location --query S3Bucket --output text)"
KEY="$APPLICATION/$VERSION.zip"

aws s3 cp "$BUNDLE" "s3://$BUCKET/$KEY"

aws elasticbeanstalk create-application-version \
  --application-name "$APPLICATION" \
  --version-label "$VERSION" \
  --source-bundle "S3Bucket=$BUCKET,S3Key=$KEY" > /dev/null

aws elasticbeanstalk update-environment \
  --environment-name "$ENVIRONMENT" \
  --version-label "$VERSION" > /dev/null

echo "Desplegando $VERSION en $ENVIRONMENT (puede tardar unos minutos)..."
aws elasticbeanstalk wait environment-updated --environment-names "$ENVIRONMENT"

DEPLOYED="$(aws elasticbeanstalk describe-environments \
  --environment-names "$ENVIRONMENT" \
  --query 'Environments[0].VersionLabel' \
  --output text)"

if [ "$DEPLOYED" != "$VERSION" ]; then
  echo "El despliegue falló: el entorno sigue en '$DEPLOYED' y se esperaba '$VERSION'. Últimos errores:" >&2
  aws elasticbeanstalk describe-events \
    --environment-name "$ENVIRONMENT" \
    --severity ERROR \
    --max-items 5 \
    --query 'Events[].[EventDate,Message]' \
    --output text >&2
  exit 1
fi

echo "Desplegado: $DEPLOYED"
