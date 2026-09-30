#!/bin/bash
# Genera el .env de producción leyendo la configuración de Parameter Store.
set -euo pipefail

cd /var/app/staging

get_property() {
  /opt/elasticbeanstalk/bin/get-config environment -k "$1" 2>/dev/null || true
}

SSM_PREFIX="$(get_property SSM_PREFIX)"
REGION="$(get_property AWS_DEFAULT_REGION)"

if [ -z "$SSM_PREFIX" ] || [ -z "$REGION" ]; then
  echo "Faltan SSM_PREFIX o AWS_DEFAULT_REGION en las propiedades del entorno" >&2
  exit 1
fi

umask 077

cat > .env <<'EOF'
APP_NAME="Pollo Charly"
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
BCRYPT_ROUNDS=12
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=mysql
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_APP_ACTIVITY_TIMEOUT=20
REVERB_APP_PING_INTERVAL=30
EOF

aws ssm get-parameters-by-path \
  --path "$SSM_PREFIX" \
  --recursive \
  --with-decryption \
  --region "$REGION" \
  --query 'Parameters[].[Name,Value]' \
  --output text |
  while IFS=$'\t' read -r name value; do
    printf '%s="%s"\n' "${name##*/}" "$value"
  done >> .env

add_default() {
  grep -q "^$1=" .env || printf '%s="%s"\n' "$1" "$2" >> .env
}

add_default APP_URL "http://localhost"
add_default FRONTEND_URL "http://localhost"
add_default MAIL_MAILER "log"
add_default MAIL_FROM_ADDRESS "no-reply@localhost"
add_default MAIL_FROM_NAME "Pollo Charly"

chown webapp:webapp .env
