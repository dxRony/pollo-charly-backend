#!/bin/bash
# Cachea la configuración (ya con la ruta definitiva) y reinicia el servidor WebSocket.
set -euo pipefail

cd /var/app/current

artisan() {
  runuser -u webapp -- php artisan "$@"
}

artisan config:cache
artisan event:cache
artisan view:cache

cat > /etc/systemd/system/reverb.service <<'EOF'
[Unit]
Description=Laravel Reverb (servidor WebSocket)
After=network.target

[Service]
User=webapp
Group=webapp
WorkingDirectory=/var/app/current
ExecStart=/usr/bin/php artisan reverb:start --no-interaction
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable reverb
systemctl restart reverb
