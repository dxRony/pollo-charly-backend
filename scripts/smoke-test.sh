#!/usr/bin/env bash
# Verificación posterior al despliegue: comprueba desde internet que el sitio, la API y el WebSocket responden.
# Solo lectura: no inicia sesión ni modifica datos.
# Uso: scripts/smoke-test.sh                  (usa SITE_URL o el sitio de producción)
#      SITE_URL=https://otro-sitio scripts/smoke-test.sh
set -uo pipefail
export AWS_PAGER=""

SITE_URL="${SITE_URL:-https://d1nyq5ujkrljd1.cloudfront.net}"
SSM_PREFIX="${SSM_PREFIX:-/pollo-charly/prod}"
FAILS=0

check() {
  local description="$1" expected="$2" actual="$3"
  if [ "$actual" = "$expected" ]; then
    printf '  OK    %s\n' "$description"
  else
    printf '  FALLA %s (esperado: %s, obtenido: %s)\n' "$description" "$expected" "$actual"
    FAILS=$((FAILS + 1))
  fi
}

status() { curl -s -o /dev/null -m 15 -w '%{http_code}' "$@" || true; }

echo "Verificando $SITE_URL"
check "La página principal responde" 200 "$(status "$SITE_URL/")"
check "Una ruta del frontend (/login) responde" 200 "$(status "$SITE_URL/login")"
check "La API responde (/api/ping)" 200 "$(status "$SITE_URL/api/ping")"
check "Una ruta protegida exige sesión (/api/me)" 401 "$(status -H 'Accept: application/json' "$SITE_URL/api/me")"

MENU="$(curl -s -m 15 "$SITE_URL/api/daily-menu" || true)"
if printf '%s' "$MENU" | python3 -c 'import sys, json; d = json.load(sys.stdin); assert isinstance(d, list)' 2>/dev/null; then
  check "El menú del día es público y no expone recetas" "sin-recetas" \
    "$(printf '%s' "$MENU" | python3 -c 'import sys; print("con-recetas" if "recipes" in sys.stdin.read() else "sin-recetas")')"
else
  check "El menú del día devuelve una lista JSON" "lista" "respuesta-invalida"
fi

REVERB_KEY="${REVERB_APP_KEY:-$(aws ssm get-parameter --region us-east-2 --name "$SSM_PREFIX/REVERB_APP_KEY" --query Parameter.Value --output text 2>/dev/null || true)}"
if [ -n "$REVERB_KEY" ]; then
  # Los navegadores abren los WebSockets con HTTP/1.1; con HTTP/2 CloudFront responde 500.
  check "El WebSocket (Cocina en tiempo real) acepta conexiones" 101 \
    "$(status --http1.1 --max-time 5 -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Version: 13' \
      -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' -H "Origin: $SITE_URL" \
      "$SITE_URL/app/$REVERB_KEY?protocol=7&client=js&version=8.4.0")"
else
  echo "  OMITIDO el WebSocket (define REVERB_APP_KEY o configura el acceso a AWS)"
fi

if [ "$FAILS" -gt 0 ]; then
  echo "Resultado: $FAILS comprobación(es) fallaron."
  exit 1
fi
echo "Resultado: todo correcto."
