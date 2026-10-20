#!/usr/bin/env bash
# Compara una base de datos restaurada desde un snapshot con la de producción (solo lectura en ambas).
# Se ejecuta en el servidor de la API, que es la única máquina con acceso a las bases de datos.
# Falla si el número de tablas o el contenido de los catálogos no coincide; las demás tablas (usuarios,
# pedidos, sesiones...) pueden diferir legítimamente porque cambiaron después del snapshot.
# Uso: scripts/verify-restore.sh <endpoint-de-la-instancia-restaurada>
set -euo pipefail

RESTORED_HOST="${1:?Uso: scripts/verify-restore.sh <endpoint-de-la-instancia-restaurada>}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PHP_SCRIPT='<?php
$env = parse_ini_file("/var/app/current/.env", false, INI_SCANNER_RAW);
$clean = fn ($v) => trim((string) $v, "\"");
$user = $clean($env["DB_USERNAME"]); $pass = $clean($env["DB_PASSWORD"]); $db = $clean($env["DB_DATABASE"]);
$connect = fn (string $host) => new PDO("mysql:host=$host;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try { $prod = $connect($clean($env["DB_HOST"])); $restored = $connect($argv[1]); }
catch (PDOException $e) { fwrite(STDERR, "No se pudo conectar a una de las bases: " . $e->getMessage() . "\n"); exit(2); }
$tables = fn (PDO $c) => $c->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$catalogs = ["roles", "categories", "measurement_units", "order_statuses", "payment_methods"];
$ok = true;
$tp = $tables($prod); $tr = $tables($restored);
printf("Tablas: produccion=%d restaurada=%d  %s\n", count($tp), count($tr), count($tp) === count($tr) ? "OK" : "DIFIERE");
$ok = $ok && count($tp) === count($tr);
foreach ($catalogs as $t) {
    if (!in_array($t, $tp, true)) continue;
    $cs = fn (PDO $c) => $c->query("CHECKSUM TABLE `$t`")->fetch(PDO::FETCH_ASSOC)["Checksum"];
    $same = $cs($prod) === $cs($restored);
    printf("Catalogo %-18s checksum %s\n", $t, $same ? "IGUAL" : "DIFIERE");
    $ok = $ok && $same;
}
echo "\nFilas por tabla (informativo; puede diferir lo cambiado despues del snapshot):\n";
foreach ($tp as $t) {
    $n = fn (PDO $c) => (int) $c->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    [$a, $b] = [$n($prod), in_array($t, $tr, true) ? $n($restored) : -1];
    if ($a !== $b) printf("  %-30s produccion=%d restaurada=%d\n", $t, $a, $b);
}
echo $ok ? "\nRESULTADO: la restauracion es consistente.\n" : "\nRESULTADO: HAY DIFERENCIAS en tablas o catalogos.\n";
exit($ok ? 0 : 1);'

ENCODED="$(printf '%s' "$PHP_SCRIPT" | base64 -w0)"
"$ROOT/scripts/run-on-server.sh" "echo $ENCODED | base64 -d > /tmp/verify-restore.php && runuser -u webapp -- php /tmp/verify-restore.php '$RESTORED_HOST'; RC=\$?; rm -f /tmp/verify-restore.php; exit \$RC"
