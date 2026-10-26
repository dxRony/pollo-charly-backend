# Runbook de despliegue — Pollo Charly

Manual operativo para desplegar, verificar, revertir y recuperar el sistema Pollo Charly en AWS. Su propósito es que
cualquier integrante del equipo pueda ejecutar los procedimientos sin haber participado en la construcción de la
infraestructura.

## Control del documento

| Campo | Valor |
|---|---|
| Versión | 1.4 |
| Última revisión | 25 de octubre de 2026 |
| Alcance | API (Laravel) y frontend (React) desplegados en AWS, región `us-east-2` |
| Audiencia | Personal técnico con conocimientos básicos de terminal y de AWS |

**Convención de estado.** Cada procedimiento indica si fue ejecutado realmente:

- **Estado: Probado.** Se ejecutó en producción o con datos reales.
- **Estado: Documentado, no ejecutado.** Corresponde a un mecanismo estándar que no se ejecutó en producción para evitar
  una interrupción del servicio.

## Contenido

1. [Visión general](#1-visión-general)
2. [Requisitos y accesos](#2-requisitos-y-accesos)
3. [Despliegue normal](#3-despliegue-normal)
4. [Despliegue manual y reintentos](#4-despliegue-manual-y-reintentos)
5. [Verificación posterior al despliegue](#5-verificación-posterior-al-despliegue)
6. [Reversa (rollback)](#6-reversa-rollback)
7. [Operaciones frecuentes](#7-operaciones-frecuentes)
8. [Respaldos y restauración](#8-respaldos-y-restauración)
9. [Costos y créditos](#9-costos-y-créditos)
10. [Solución de problemas](#10-solución-de-problemas)
11. [Recreación del entorno desde cero](#11-recreación-del-entorno-desde-cero)
12. [Limitaciones conocidas](#12-limitaciones-conocidas)
13. [Desmontaje al finalizar el proyecto](#13-desmontaje-al-finalizar-el-proyecto)

---

## 1. Visión general

```
                    Internet (HTTPS)
                          │
                   ┌──────▼───────┐
                   │  CloudFront  │  un solo dominio para todo
                   └──┬────────┬──┘
        /  (frontend) │        │  /api/*  /broadcasting/*  /app/*
              ┌───────▼──┐  ┌──▼─────────────────────────────┐
              │ S3       │  │ Elastic Beanstalk (1 instancia) │
              │ frontend │  │  nginx → PHP-FPM (Laravel)      │
              └──────────┘  │        → Reverb (WebSocket)     │
                            └───────┬──────────┬─────────────┘
                                    │          │
                          ┌─────────▼──┐   ┌───▼──────────────┐
                          │ RDS MySQL  │   │ Parameter Store  │ secretos
                          │ (privada)  │   │ (SSM)            │
                          └────────────┘   └──────────────────┘
        Servicios externos: Gmail SMTP (correo) · Cloudinary (imágenes de platillos)
```

| Pieza | Nombre / valor |
|---|---|
| Sitio público | `https://d1nyq5ujkrljd1.cloudfront.net` |
| Entorno de Beanstalk (y aplicación) | `pollo-charly-prod-api` |
| Base de datos (RDS MySQL 8.4, `db.t3.micro`, una sola zona) | `pollo-charly-prod-mysql` |
| Bucket del frontend | `pollo-charly-prod-frontend-<ID-de-cuenta>` |
| Distribución de CloudFront | `E23ZPGIBRO5LWC` |
| Prefijo de secretos en SSM | `/pollo-charly/prod/` |
| Usuarios IAM de GitHub Actions | `pollo-charly-prod-gha-backend`, `pollo-charly-prod-gha-frontend` |

**Construcción del servidor.** Beanstalk ejecuta, en este orden, los *hooks* de [`.platform/hooks/`](../.platform/hooks):

1. `prebuild/01_generate_env.sh`: genera el `.env` a partir de **todos** los parámetros de SSM bajo el prefijo.
   Por esta razón, un secreto modificado en SSM no se aplica hasta el siguiente despliegue.
2. `predeploy/01_prepare_release.sh`: ajusta permisos, ejecuta `php artisan migrate --force` y el seeder de producción.
3. `postdeploy/01_finalize_release.sh`: almacena en caché la configuración y vuelve a crear y reiniciar el servicio `reverb`.

La configuración de nginx (rutas de Laravel, proxy del WebSocket y límite de subida de 5 MB) se encuentra en
[`.platform/nginx/conf.d/elasticbeanstalk/`](../.platform/nginx/conf.d/elasticbeanstalk).

**Zona horaria.** La aplicación usa `America/Guatemala` (UTC−6) por defecto, de modo que las fechas y horas de comandas,
ventas y reportes son las locales del restaurante. Se puede cambiar con la variable `APP_TIMEZONE`.

La infraestructura está definida con Terraform en [`infra/`](../infra). El estado de Terraform es **local** (ver
sección 12).

## 2. Requisitos y accesos

### Herramientas necesarias

| Herramienta | Uso |
|---|---|
| AWS CLI v2, configurada con un usuario con permisos sobre la cuenta y la región `us-east-2` | Todas las operaciones manuales |
| Terraform ≥ 1.10 | Cambios de infraestructura |
| `git`, `python3`, `curl` | Scripts de este repositorio |
| PHP 8.4, Composer, `rsync`, `zip` | Empaquetado manual (sección 4) |
| Node 22 y `npm` | Publicación manual del frontend |

Verificación del acceso:

```bash
aws sts get-caller-identity --query Arn --output text   # debe mostrar un usuario IAM, no el usuario raíz
aws configure get region                                  # debe ser us-east-2
```

Cada integrante opera con su **propio usuario IAM** (sección 7.10) y MFA; `julian-admin` queda para casos excepcionales.

### Archivos fuera del control de versiones

| Archivo | Contenido | Consecuencia si se pierde |
|---|---|---|
| `infra/terraform.tfstate` (y `.backup`) | Estado de Terraform | **Impide ejecutar `terraform destroy` de forma limpia.** Debe respaldarse en un lugar privado |
| `infra/terraform.tfvars` | `initial_admin_email`, `smtp_username` y variables opcionales | Se recrea según la sección 11 |

### GitHub

Cada repositorio (backend y frontend) dispone de un *environment* llamado `production`, restringido a la rama `main`, con
los secretos `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY` del usuario IAM correspondiente. Un despliegue solo se ejecuta
desde `main`.

## 3. Despliegue normal

El flujo de ramas es **feature → `develop` → `main`**. Los despliegues se ejecutan automáticamente al integrar cambios en
`main`.

1. Trabajar en una rama `feature/...` y abrir un *pull request* (PR) hacia `develop`. El CI debe finalizar correctamente:
   - **Pruebas (Pest)**, **Infraestructura (Terraform)** (formato y validación) y **Scripts de despliegue** (sintaxis y
     permisos de ejecución).
2. Tras la revisión, integrar el PR en `develop`.
3. Cuando `develop` esté listo para producción, abrir un PR `develop → main` e integrarlo.
4. Se inicia el workflow de despliegue correspondiente:

   | Repositorio | Workflow | Acciones |
   |---|---|---|
   | backend | **Desplegar API** | Empaqueta (`composer install --no-dev`), sube a S3, crea la versión en Beanstalk, la despliega y consulta `/api/ping` |
   | frontend | **Publicar frontend** | Ejecuta lint, compila, sincroniza con S3, invalida CloudFront y consulta el sitio |

   **Cuando existen cambios en ambos repositorios, se despliega primero el backend y después el frontend.** El backend
   aplica migraciones y modifica la API; un frontend nuevo contra un backend anterior puede fallar.

5. Verificar el resultado según la [sección 5](#5-verificación-posterior-al-despliegue).

> **Un despliegue en verde implica un resultado real.** El script `deploy-beanstalk.sh` compara la versión que quedó en el
> entorno con la solicitada y falla, mostrando los últimos errores de Beanstalk, si no coinciden.

**Estado: Probado** (ciclo completo, varias veces).

## 4. Despliegue manual y reintentos

### Reintento sin cambios de código

Se utiliza, por ejemplo, después de cargar un secreto nuevo. En GitHub: pestaña **Actions** → workflow **Desplegar API** →
**Run workflow** (rama `main`). Es el método preferido porque no depende de un equipo local.

**Estado: Probado.**

### Despliegue manual de la API

Este procedimiento aplica cuando GitHub Actions no está disponible. Desde la raíz del repositorio del backend, con la rama que se desea
publicar:

```bash
VERSION="manual-$(date +%Y%m%d-%H%M)"
./scripts/build-beanstalk-bundle.sh "$VERSION"
./scripts/deploy-beanstalk.sh "build/pollo-charly-api-$VERSION.zip"
```

La etiqueta de versión debe ser **única**: Beanstalk rechaza una etiqueta repetida.

**Estado: Probado.**

### Publicación manual del frontend

Desde la raíz del repositorio del frontend:

```bash
export VITE_REVERB_HOST=d1nyq5ujkrljd1.cloudfront.net            # dominio de CloudFront, sin https://
export VITE_REVERB_APP_KEY=$(aws ssm get-parameter --region us-east-2 \
  --name /pollo-charly/prod/REVERB_APP_KEY --query Parameter.Value --output text)
npm ci && ./scripts/build-production.sh

export FRONTEND_BUCKET=pollo-charly-prod-frontend-<ID-de-cuenta>
export CLOUDFRONT_DISTRIBUTION_ID=E23ZPGIBRO5LWC
./scripts/publish-frontend.sh
```

**Estado: Probado.**

## 5. Verificación posterior al despliegue

### Verificación automática (solo lectura, sin iniciar sesión)

```bash
./scripts/smoke-test.sh
```

El script comprueba la página principal, una ruta del frontend, `/api/ping`, que una ruta protegida exija sesión, que el menú del día
sea público **sin exponer recetas** y que el WebSocket acepte conexiones. Finaliza con error si alguna comprobación falla.

**Estado: Probado.**

### Estado del servidor

```bash
./scripts/run-on-server.sh "cd /var/app/current && runuser -u webapp -- php artisan migrate:status --no-ansi | grep -c Pending || true"   # 0 = sin migraciones pendientes
./scripts/run-on-server.sh "systemctl is-active reverb"                                                                                     # active
aws elasticbeanstalk describe-environments --environment-names pollo-charly-prod-api \
  --query 'Environments[0].[VersionLabel,Health,Status]' --output text                                                                      # versión nueva · Green · Ready
```

### Monitoreo

La arquitectura es de una sola instancia y no incluye balanceador, por lo que la vigilancia se apoya en comprobaciones
periódicas sencillas. Beanstalk con salud *basic* no publica métricas en CloudWatch y las métricas de CloudFront residen en
`us-east-1` (región no permitida en esta cuenta), de modo que la disponibilidad de la API se verifica con el *smoke test*.

| Qué se vigila | Cómo | Cuándo | Valor normal | Si es anormal |
|---|---|---|---|---|
| Disponibilidad pública (sitio, API, WebSocket) | `./scripts/smoke-test.sh` | Tras cada despliegue y antes de cada demostración | 6 comprobaciones correctas | Sección 10 |
| Salud del entorno de Beanstalk | `aws elasticbeanstalk describe-environments --environment-names pollo-charly-prod-api --query 'Environments[0].[Health,Status]' --output text` | Diario durante la evaluación | `Green  Ready` | Registros (7.1); reinicio del servidor de aplicación desde la consola de Beanstalk |
| Integración y despliegue | Pestaña **Actions** de cada repositorio | En cada PR y despliegue | Ejecuciones en verde | Abrir el registro del paso que falló |
| Respaldo semanal | Workflow *Respaldo semanal de la base de datos* y listado de snapshots (sección 8) | Cada domingo | Ejecución correcta y 4 snapshots `weekly-*` | Relanzar el workflow manualmente |
| Base de datos: disco, CPU y memoria | Alarmas de `infra/monitoring.tf` (opcional) o la pestaña *Monitoring* de RDS en la consola | Semanal | Más de 4 GB libres; CPU menor al 80 %; más de 100 MB de memoria libre | Revisar consultas lentas y el crecimiento de las tablas; considerar aumentar el almacenamiento |
| Créditos del plan gratuito | `aws freetier get-account-plan-state --region us-east-1` | Semanal | `accountPlanRemainingCredits` positivo y en descenso lento | Sección 9 |
| Errores de la aplicación | `laravel.log` (sección 7.1) | Tras cualquier incidente | Sin errores nuevos | Sección 10 |

**Alarmas de CloudWatch (opcional).** `infra/monitoring.tf` define tres alarmas sobre RDS (poco espacio libre, CPU alta y
poca memoria). Para recibir un correo cuando se activen: agregar `alert_email = "<correo>"` en `infra/terraform.tfvars`,
ejecutar `terraform apply` y **confirmar la suscripción** desde el mensaje que envía AWS. El plan gratuito incluye 10
alarmas y el envío de correos por SNS.

**Estado: Documentado, no ejecutado** (las alarmas se crean al aplicar el cambio de infraestructura).

### Verificación manual (con sesión de administrador)

Estas comprobaciones requieren iniciar sesión y no pueden automatizarse sin credenciales. Se recomienda ejecutarlas tras
cualquier cambio de importancia:

- [ ] Iniciar sesión (y completar 2FA, si el usuario lo tiene activado).
- [ ] Descargar un reporte en **PDF** y otro en **Excel**.
- [ ] Subir la imagen de un platillo de **más de 1 MB** (valida el límite de nginx).
- [ ] Crear un usuario con un correo real: debe recibirse el correo con la contraseña temporal.
- [ ] Con dos usuarios (mesero y cocinero): una comanda nueva debe aparecer en Cocina **sin recargar la página**.
- [ ] La dirección directa de Beanstalk (`<entorno>.us-east-2.elasticbeanstalk.com`) **no responde** (`000`): el único acceso es la URL de CloudFront. Si responde, ver la nota del grupo creado por Beanstalk en la sección 7.6.

## 6. Reversa (rollback)

La acción depende del tipo de problema:

| Situación | Acción |
|---|---|
| Un cambio de código afectó el funcionamiento | **Revertir en git** (preferido) o volver a una versión anterior de Beanstalk |
| Una migración dañó datos | **Restaurar la base de datos** (sección 8). Las migraciones solo avanzan: revertir el código no deshace los cambios en la base |

### Opción A — Revertir en git (recomendada)

Ejecutar `git revert <commit>` en una rama, abrir un PR hacia `develop` y otro hacia `main`. El despliegue normal publica la
versión corregida.

### Opción B — Volver a una versión ya publicada en Beanstalk (rápida)

```bash
# Versiones disponibles (la desplegada actualmente es la indicada en VersionLabel)
aws elasticbeanstalk describe-application-versions --application-name pollo-charly-prod-api \
  --query 'sort_by(ApplicationVersions,&DateCreated)[-6:].[VersionLabel,DateCreated]' --output text

aws elasticbeanstalk update-environment --environment-name pollo-charly-prod-api \
  --version-label <VersionLabel-anterior>
aws elasticbeanstalk wait environment-updated --environment-names pollo-charly-prod-api
./scripts/smoke-test.sh
```

Este procedimiento vuelve a ejecutar los *hooks* (incluidos `migrate` y la regeneración del `.env`) con el código anterior.

**Estado: Documentado, no ejecutado en producción.** Es el mecanismo estándar de Beanstalk; la consulta de versiones sí se
verificó. Se recomienda probarlo primero en una ventana sin usuarios activos.

> El frontend se revierte de forma equivalente: se vuelve a ejecutar el workflow **Publicar frontend** sobre el commit
> correcto.

## 7. Operaciones frecuentes

Todos los comandos se ejecutan desde la raíz del repositorio del backend. Para ejecutar un comando en el servidor sin SSH:

```bash
./scripts/run-on-server.sh "<comando>"      # se ejecuta como root mediante AWS Systems Manager
```

### 7.1 Consulta de registros (logs)

```bash
./scripts/run-on-server.sh "tail -n 40 /var/app/current/storage/logs/laravel.log"   # errores de la aplicación
./scripts/run-on-server.sh "tail -n 40 /var/log/nginx/error.log"                    # nginx
./scripts/run-on-server.sh "journalctl -u reverb -n 40 --no-pager"                  # WebSocket
./scripts/run-on-server.sh "tail -n 60 /var/log/eb-engine.log"                      # despliegues de Beanstalk
```

Si `laravel.log` no existe, no se ha registrado ningún error desde el último despliegue.

**Estado: Probado.**

### 7.2 Carga y cambio de secretos

Los secretos se almacenan en Parameter Store bajo `/pollo-charly/prod/`. **No deben escribirse en chats, repositorios ni
terminales compartidas.** Para cargar un valor sin mostrarlo en pantalla (zsh):

```bash
read -rs "VALOR?Valor del secreto: " && echo
aws ssm put-parameter --region us-east-2 --name /pollo-charly/prod/<NOMBRE> \
  --type SecureString --overwrite --value "$VALOR" > /dev/null && unset VALOR && echo "cargado"
```

Después se debe **volver a desplegar** (sección 4) para que el `.env` se regenere.

| Parámetro | Propósito | Origen |
|---|---|---|
| `APP_KEY`, `DB_*`, `REVERB_*`, `INITIAL_ADMIN_*` | Generados por Terraform | `terraform apply` |
| `MAIL_PASSWORD` | Contraseña de aplicación de Google | Manual (sección 7.3) |
| `CLOUDINARY_URL` | `cloudinary://<clave>:<secreto>@<cuenta>` **sin** los símbolos `<` ni `>` | Manual |

La contraseña inicial del administrador, necesaria para el primer acceso, se obtiene con:

```bash
aws ssm get-parameter --region us-east-2 --name /pollo-charly/prod/INITIAL_ADMIN_PASSWORD \
  --with-decryption --query Parameter.Value --output text
```

**Estado: Probado.**

### 7.3 Correo (Gmail y SES)

El sistema envía correo mediante **Gmail SMTP** (cuenta dedicada con contraseña de aplicación). Amazon SES permanece
configurado como respaldo, pero en modo *sandbox* solo entrega a direcciones verificadas.

- **Rotación de la contraseña de aplicación:** crear una nueva en la cuenta de Google, cargarla como `MAIL_PASSWORD`
  (sección 7.2), volver a desplegar y revocar la anterior en Google.
- **Cambio de proveedor:** establecer `mail_provider = "ses"` (o `"smtp"`) en `infra/terraform.tfvars`, ejecutar
  `terraform apply` y volver a desplegar. Con SES, cada destinatario debe figurar en `ses_extra_recipients` y haber
  confirmado el correo de verificación de AWS.
- **Primer envío tras un cambio:** Google puede enviar a la cuenta un aviso de inicio de sesión bloqueado, que debe
  confirmarse.

### 7.4 Reinicio de la base de datos (elimina TODOS los datos)

Se utiliza, por ejemplo, después de pruebas. El procedimiento conserva las migraciones, los catálogos y el administrador inicial:

```bash
WITH_SNAPSHOT=1 ./scripts/reset-database.sh     # crea antes un snapshot manual; solicita escribir RESETEAR
```

Las sesiones existentes dejan de ser válidas.

**Estado: Probado.**

### 7.5 Limpieza de imágenes huérfanas en Cloudinary

El script elimina las imágenes de platillos que ningún platillo utiliza (por ejemplo, las subidas y nunca guardadas):

```bash
DRY_RUN=1 ./scripts/prune-orphan-images.sh    # solo cuenta, sin borrar
./scripts/prune-orphan-images.sh              # elimina las huérfanas con más de 24 horas
```

Solo actúa en la carpeta del entorno (`pollo-charly/production/dishes`).

**Estado: Probado** (simulación y borrado en el entorno de pruebas).

### 7.6 Cambios de infraestructura

```bash
cd infra
terraform plan        # revisar el resultado antes de aplicar
terraform apply
```

- Cuando todo coincide, el plan indica `No changes`. **Si propone destruir o reemplazar un recurso (RDS, Beanstalk,
  CloudFront), no debe aplicarse** hasta analizar el motivo.
- Las actualizaciones de la plataforma de Beanstalk están **ignoradas deliberadamente** (`ignore_changes`): AWS publica
  versiones nuevas y un `apply` no debe reemplazar el servidor de forma inadvertida. La plataforma se actualiza de forma
  explícita, según la sección 7.8.
- **Entrada a la instancia.** El grupo `SG-web` solo acepta HTTP (puerto 80) desde la lista de prefijos administrada de
  CloudFront (`com.amazonaws.global.cloudfront.origin-facing`), por lo que la dirección directa de Beanstalk no responde.
  Durante el `apply` la entrada puede interrumpirse unos segundos. **Reversa inmediata:**
  `terraform apply -var restrict_origin_to_cloudfront=false` restablece la entrada abierta anterior.
- **Grupo de seguridad creado por Beanstalk.** En los entornos de instancia única, Beanstalk crea además su propio grupo
  (`awseb-e-…-AWSEBSecurityGroup`) con el puerto 80 abierto a `0.0.0.0/0`. Lo administra CloudFormation y **Terraform no
  lo ve**, de modo que la dirección directa de Beanstalk sigue respondiendo hasta quitar esa regla a mano:

  ```bash
  SG=$(aws ec2 describe-security-groups --region us-east-2 \
    --filters Name=group-name,Values='awseb-*AWSEBSecurityGroup*' --query 'SecurityGroups[0].GroupId' --output text)
  aws ec2 revoke-security-group-ingress --region us-east-2 --group-id "$SG" --protocol tcp --port 80 --cidr 0.0.0.0/0
  # Reversa:  aws ec2 authorize-security-group-ingress --region us-east-2 --group-id "$SG" --protocol tcp --port 80 --cidr 0.0.0.0/0
  ```

  El ajuste **no es permanente**: una actualización de la plataforma o la recreación del entorno (secciones 7.8 y 11) puede
  restablecer la regla. Tras cualquiera de ellas, repetir el comando y comprobar que
  `curl -m 8 http://<entorno>.us-east-2.elasticbeanstalk.com/api/ping` no obtiene respuesta (código `000`).

  **Estado: Probado el efecto de la regla** (la dirección directa respondía 200 con ese grupo abierto).
- **Cabeceras de seguridad.** CloudFront añade HSTS (180 días, sin subdominios), `X-Content-Type-Options`, `X-Frame-Options`
  y `Referrer-Policy` a las respuestas del sitio (`aws_cloudfront_response_headers_policy.security`). La actualización de
  la distribución tarda entre 5 y 10 minutos y no interrumpe el servicio.

### 7.7 Rotación de las llaves de GitHub Actions

Para cada usuario (`pollo-charly-prod-gha-backend` y `pollo-charly-prod-gha-frontend`):

1. Crear una llave nueva con `aws iam create-access-key --user-name <usuario>` y conservarla en un lugar privado.
2. Actualizar los secretos `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY` del *environment* `production` del repositorio.
3. Ejecutar el workflow de despliegue y verificar que finaliza correctamente.
4. Eliminar la llave anterior con `aws iam delete-access-key --user-name <usuario> --access-key-id <llave-anterior>`.

Un usuario IAM admite como máximo dos llaves simultáneas.

**Estado: Documentado, no ejecutado como rotación completa.** La creación y eliminación de llaves de estos usuarios sí se
realizó durante la puesta en marcha.

### 7.8 Actualización de la plataforma de Beanstalk (PHP y sistema operativo)

La versión de PHP, nginx y el sistema operativo dependen de la **plataforma** de Elastic Beanstalk. Terraform la ignora
deliberadamente, por lo que las actualizaciones, incluidas las de seguridad, se aplican de forma manual y explícita.

**Cuándo actualizar:**

- Al publicarse una vulnerabilidad crítica en PHP o en el sistema operativo (por ejemplo, PHP2Shell, CVE-2026-109618 y
  CVE-2026-109619, corregida en PHP 8.4.27).
- De forma periódica (se recomienda una vez al mes), para recibir parches de seguridad menores.

**Procedimiento:**

```bash
# 1. Versión actual y versiones disponibles
aws elasticbeanstalk describe-environments --environment-names pollo-charly-prod-api \
  --query 'Environments[0].SolutionStackName' --output text
aws elasticbeanstalk list-platform-versions \
  --filters 'Type=PlatformName,Operator=contains,Values=PHP 8.4 running on 64bit Amazon Linux 2023' \
  --query 'sort_by(PlatformSummaryList,&PlatformVersion)[-3:].[PlatformVersion,PlatformStatus]' --output text

# 2. Confirmar qué versión de PHP incluye la plataforma elegida (historial de plataformas de PHP de AWS):
#    https://docs.aws.amazon.com/elasticbeanstalk/latest/platforms/platform-history-php.html

# 3. Actualizar (reemplaza la instancia; interrupción de unos 3 a 5 minutos)
aws elasticbeanstalk update-environment --environment-name pollo-charly-prod-api \
  --solution-stack-name "64bit Amazon Linux 2023 v<versión> running PHP 8.4"
aws elasticbeanstalk wait environment-updated --environment-names pollo-charly-prod-api

# 4. Verificar
./scripts/run-on-server.sh "php -v | head -1"
./scripts/smoke-test.sh
```

**Consideraciones:**

- Se recomienda realizarlo **fuera de horarios de uso** y no durante una demostración o prueba en curso.
- La base de datos, los secretos, los respaldos y la versión de la aplicación **no se modifican**: los *hooks* regeneran el
  `.env` y reinician el servicio `reverb` en la instancia nueva.
- Tras la actualización, `terraform plan` debe indicar `No changes`.
- Verificar además que el correo y la subida de imágenes sigan funcionando (sección 5, verificación manual).

**Estado: Probado.** El 22 de octubre de 2026 se actualizó de la plataforma 4.13.9 (PHP 8.4.25) a la 4.13.13 (PHP 8.4.27).
La actualización completó en unos 3 minutos; después se verificaron PHP 8.4.27, el estado de los servicios, las migraciones,
la configuración cargada en la instancia nueva, la ausencia de errores y las comprobaciones de `smoke-test.sh`.

### 7.9 Datos de demostración

Permite llenar el sistema con **datos ficticios** para mostrar su funcionamiento con información realista (por ejemplo, en
una presentación). Los datos se generan a través del propio API, con las reglas reales del sistema, por lo que el stock, los
movimientos, las alertas y los reportes son coherentes entre sí.

**Qué se carga** (con 30 días de historial, aproximadamente):

- 18 insumos con stock inicial, 13 platillos con receta, 6 complementos, 8 mesas y 3 proveedores; menú del día publicado.
- Unas 280 comandas que pasan por cocina y caja (con cancelaciones, descuentos y distintos métodos de pago), distribuidas
  dentro del horario de atención y con más movimiento los fines de semana.
- Consumo de insumos, alertas de stock bajo, solicitudes y órdenes de compra con sus recepciones (algunas con incidencia),
  mermas y ajustes de inventario aprobados y rechazados.
- Tareas pendientes para mostrar los flujos de aprobación: una solicitud de compra por aprobar, una orden de compra por
  recibir, dos alertas de reposición y un ajuste de inventario por revisar.
- Cuatro usuarios de demostración (2 meseros y 2 cocineros) con contraseñas aleatorias. **Las contraseñas se muestran una
  sola vez al terminar** y no se guardan en ningún otro lugar.

**Qué no incluye:** imágenes de platillos (deben subirse desde la pantalla de Platillos) ni comandas abiertas (las mesas
quedan libres, para crear comandas en vivo durante la presentación).

**Procedimiento en producción:**

```bash
WITH_SNAPSHOT=1 ./scripts/reset-database.sh    # deja la base limpia (solo el administrador inicial)
./scripts/seed-demo-data.sh                     # solicita escribir CARGAR; tarda unos minutos
```

- `DAYS=14 ./scripts/seed-demo-data.sh` cambia los días de historial (de 1 a 90).
- Si la base ya contiene datos, la carga se **rechaza** sin modificar nada.
- Las contraseñas también quedan en el historial de Systems Manager, visible solo para usuarios IAM con acceso a la
  instancia. Se invalidan al reiniciar la base de datos.
- **Para retirar la demostración**, se ejecuta de nuevo `./scripts/reset-database.sh`.

**Procedimiento en desarrollo:**

Permite ver los cambios con información realista sin desplegar. **Borra todos los datos de la base local** (`migrate:fresh`) y
conserva los usuarios de desarrollo habituales (`admin@pollocharly.com`, `mesero@pollocharly.com` y `cocinero@pollocharly.com`):

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=CatalogSeeder --force
php artisan demo:seed --days=30
```

Los datos se generan relativos a la fecha en que se cargan; para actualizarlos basta repetir el procedimiento. Quien prefiera
conservar su base de desarrollo puede apuntar estos comandos a otra base con `DB_DATABASE=<otra_base>` (creada antes).

La carga no envía correos ni emite eventos de tiempo real. La misma semilla (`--seed=2026`) produce siempre los mismos
datos.

**Estado: Probado** en desarrollo y con pruebas automatizadas (coherencia del inventario, de las ventas y de las fechas).
**Documentado, no ejecutado en producción.**

### 7.10 Accesos de los integrantes (IAM)

Cada integrante tiene su **propio usuario IAM** (`pollo-charly-prod-julian` y `pollo-charly-prod-rony`), definido en
`infra/iam_team.tf`, con una política propia: permisos de consulta (`ViewOnlyAccess`) y unas pocas operaciones acotadas
(crear *snapshots* manuales de la base de datos, reiniciar el servidor de aplicación de Beanstalk y obtener los registros
del entorno). **Sin un dispositivo MFA activo no pueden hacer nada** salvo configurarlo. El usuario `julian-admin`
(*AdministratorAccess*) se conserva solo para situaciones excepcionales.

**Limitación de la cuenta: sin acceso por consola.** La política de la organización del plan gratuito niega
`iam:CreateLoginProfile` y `iam:UpdateLoginProfile` (*explicit deny*), por lo que **ningún usuario IAM puede tener
contraseña de consola**; tampoco puede usarse IAM Identity Center sin convertir el plan a de pago. Estos usuarios solo
pueden operar por **CLI con llaves de acceso y MFA**. Las llaves son credenciales de larga duración: **no se crean hasta
que una persona las necesite** (sin llaves, el usuario no puede hacer nada y no representa riesgo).

**Uso por CLI (solo cuando haga falta).**

1. El administrador crea la llave del integrante y se la entrega por un canal privado; el secreto se muestra una sola vez:

   ```bash
   aws iam create-access-key --user-name pollo-charly-prod-rony
   ```

   La persona la guarda en un perfil de `~/.aws/credentials` (nunca en el chat ni en el repositorio).
2. La persona registra su MFA virtual (la política lo permite aun sin MFA) y lo activa con dos códigos consecutivos:

   ```bash
   aws iam create-virtual-mfa-device --virtual-mfa-device-name pollo-charly-prod-rony \
     --outfile mfa-qr.png --bootstrap-method QRCodePNG        # escanear con una app TOTP y borrar mfa-qr.png
   aws iam enable-mfa-device --user-name pollo-charly-prod-rony \
     --serial-number arn:aws:iam::<ID de la cuenta>:mfa/pollo-charly-prod-rony \
     --authentication-code1 <código 1> --authentication-code2 <código 2>
   ```

3. En cada sesión de trabajo se obtienen credenciales temporales con MFA, que son las únicas con permisos efectivos:

   ```bash
   aws sts get-session-token --serial-number arn:aws:iam::<ID de la cuenta>:mfa/pollo-charly-prod-rony \
     --token-code <código actual> --duration-seconds 43200
   ```

**Baja.** Quitar el nombre de `team_members` y ejecutar `terraform apply`: se elimina el usuario junto con sus llaves y su MFA.

**Estado: Documentado, no ejecutado.** Los usuarios y sus políticas están aplicados y las políticas se validaron con el
simulador de IAM (con y sin MFA). El registro del MFA virtual por CLI puede estar restringido por la organización: si
`create-virtual-mfa-device` se rechaza, el usuario permanece sin uso (sin llaves) y se conserva `julian-admin` para operar.

## 8. Respaldos y restauración

### Respaldos existentes

| Capa | Mecanismo | Frecuencia | Retención |
|---|---|---|---|
| **Full Backup** | Snapshot manual completo `weekly-AAAAMMDD-HHMM`, creado por el workflow **Respaldo semanal de la base de datos** | Semanal (domingos, 07:00 UTC) | Los **4** más recientes |
| **Incremental Backup** | Respaldo automático de RDS: *snapshot* de almacenamiento que solo guarda los bloques modificados desde el anterior (ventana 07:00–08:00 UTC) | Diario | **1 día** |
| **Transactional Log Backup** | RDS envía de forma continua los logs de transacciones (aproximadamente cada 5 minutos); permite la **restauración a un punto específico en el tiempo** | Continuo | **1 día** |

La restauración a un punto específico (*Point-in-Time Recovery*) cubre cualquier instante de las **últimas 24 horas**
(`LatestRestorableTime` se mantiene a pocos minutos del momento actual). Para daños anteriores se recurre al snapshot
semanal más cercano.

> La retención automática es de un solo día porque la política de la cuenta del plan gratuito rechaza valores mayores. El
> snapshot semanal compensa esa limitación y cubre los requisitos de respaldo del proyecto (RNF-07, RNF-08 y RNF-09).

Consulta de los snapshots manuales y creación de uno a demanda:

```bash
aws rds describe-db-snapshots --db-instance-identifier pollo-charly-prod-mysql --snapshot-type manual \
  --query 'DBSnapshots[].[DBSnapshotIdentifier,Status,SnapshotCreateTime]' --output table

# A demanda: Actions → "Respaldo semanal de la base de datos" → Run workflow   (o manualmente:)
aws rds create-db-snapshot --db-instance-identifier pollo-charly-prod-mysql \
  --db-snapshot-identifier "manual-$(date -u +%Y%m%d-%H%M)"
```

**Estado: Probado.**

### Procedimiento de restauración y prueba de recuperación

La restauración se realiza **siempre en una instancia nueva**, nunca sobre producción. El costo es de centavos si la
instancia se elimina al finalizar. **Requiere la autorización de quien administra la cuenta**, ya que crea recursos
temporales.

```bash
SNAP=weekly-AAAAMMDD-HHMM       # snapshot que se desea recuperar
SGDB=$(aws ec2 describe-security-groups --filters Name=group-name,Values=pollo-charly-prod-sg-db \
  --query 'SecurityGroups[0].GroupId' --output text)

# 1. Restaurar en una instancia temporal y privada
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier pollo-charly-restore-test --db-snapshot-identifier "$SNAP" \
  --db-instance-class db.t3.micro --db-subnet-group-name pollo-charly-prod-db-subnets \
  --vpc-security-group-ids "$SGDB" --no-publicly-accessible --no-multi-az \
  --tags Key=Project,Value=pollo-charly Key=Purpose,Value=restore-test

# 2. Esperar (aproximadamente 10 a 15 minutos) y obtener el endpoint
aws rds wait db-instance-available --db-instance-identifier pollo-charly-restore-test
EP=$(aws rds describe-db-instances --db-instance-identifier pollo-charly-restore-test \
  --query 'DBInstances[0].Endpoint.Address' --output text)

# 3. Comparar con producción (solo lectura)
./scripts/verify-restore.sh "$EP"

# 4. Eliminar la instancia temporal
aws rds delete-db-instance --db-instance-identifier pollo-charly-restore-test \
  --skip-final-snapshot --delete-automated-backups
```

El script `verify-restore.sh` falla si el número de tablas o el contenido de los catálogos no coincide. Las tablas con datos
cambiantes (usuarios, pedidos, sesiones) pueden diferir porque se modificaron después del snapshot; esa información es
solo indicativa.

> Al eliminar la instancia temporal, RDS conserva un snapshot automático residual que **no puede borrarse manualmente**;
> desaparece al vencer su retención.

**Recuperación efectiva de producción** (no solo prueba): tras validar la instancia restaurada, se apunta la aplicación a
ella modificando `DB_HOST` en SSM y volviendo a desplegar, o se exportan los datos necesarios. Se trata de una decisión de
incidente, no de un paso rutinario.

**Resultado de la última prueba (1 de octubre de 2026): Probado.** Se restauró `weekly-20261001-0833`: 53 tablas idénticas,
filas y *checksums* de los catálogos idénticos, y la instancia temporal se eliminó sin afectar a producción. La prueba de
recuperación debe repetirse al menos una vez por semestre y documentarse.

## 9. Costos y créditos

La cuenta utiliza el **plan gratuito de AWS**: no genera cargos monetarios, pero opera con créditos de vigencia limitada
(seis meses). Si los créditos se agotan, AWS **suspende la cuenta**.

```bash
aws freetier get-account-plan-state --region us-east-1 \
  --query '{creditos:accountPlanRemainingCredits.amount,vence:accountPlanExpirationDate}' --output json
```

- Consumo observado de Pollo Charly: aproximadamente **0.80 USD diarios** (unos 24 USD al mes).
- El presupuesto `gasto-cero` descuenta los créditos, por lo que **no emite avisos mientras estos existan**. Se recomienda
  consultar los créditos restantes con el comando anterior al menos una vez por semana.
- **La cuenta no debe cambiarse a un plan de pago, ni asociarse una tarjeta, ni unirse a otra organización.**
- Recursos que generan mayor gasto si se crean por error: NAT Gateway (≈ 33 USD/mes), balanceadores (≈ 17 a 20 USD/mes),
  RDS Multi-AZ e instancias de gran tamaño.

**Estado: Probado.**

## 10. Solución de problemas

Incidentes ocurridos durante la puesta en marcha, con su causa y resolución.

| Síntoma | Causa probable | Resolución |
|---|---|---|
| El workflow finaliza en verde pero la versión no cambió | (Antes) el script no verificaba el resultado | Actualmente se verifica. Consultar los últimos errores: `aws elasticbeanstalk describe-events --environment-name pollo-charly-prod-api --severity ERROR --max-items 5` |
| El despliegue falla con `AccessDenied` | Al usuario IAM de GitHub le falta un permiso que Beanstalk utiliza con sus credenciales (CloudFormation, S3, autoescalado) | El mensaje de error nombra la acción. Se agrega en `infra/github_actions.tf` y se aplica. Se añadieron varias de forma iterativa durante la puesta en marcha |
| `Application Version ... already exists` | Reintento con una etiqueta repetida | Eliminar la versión huérfana: `aws elasticbeanstalk delete-application-version --application-name pollo-charly-prod-api --version-label <etiqueta> --delete-source-bundle`, o relanzar el workflow (la etiqueta incluye el número de intento) |
| Todas las rutas de la API responden 404, salvo `/` | nginx no redirige a `index.php` | Revisar `.platform/nginx/conf.d/elasticbeanstalk/laravel.conf` (`try_files $uri $uri/ /index.php?$query_string`) |
| Error de configuración tras cambiar un secreto | El `.env` se genera únicamente durante el despliegue | Volver a desplegar (sección 4). Los valores con espacios se entrecomillan en el hook |
| **El correo no llega** | Contraseña de aplicación revocada o aún en `sin-configurar`; Google bloqueó el acceso; o SES en *sandbox* | 1) Verificar que `MAIL_PASSWORD` esté cargado (comando bajo la tabla). 2) Revisar `laravel.log` (sección 7.1). 3) Revisar en la cuenta de Gmail un aviso de seguridad. 4) Alternativa: SES (sección 7.3) |
| Al crear un usuario aparece "no se pudo enviar el correo" | El envío falló, pero el usuario sí se creó | Corregir la causa anterior y utilizar **Restablecer contraseña** sobre ese usuario |
| La subida de imagen responde **503** | `CLOUDINARY_URL` sin cargar o con formato inválido | Cargarlo (sección 7.2); no debe contener `<` ni `>` |
| La subida de imagen responde **502** | Cloudinary rechazó la carga o no respondió | Reintentar; revisar `laravel.log` y el estado de Cloudinary |
| La subida de imagen responde **413** | La imagen supera el límite de nginx (5 MB) | Reducir el tamaño (el sistema acepta hasta 2 MB) |
| Cocina no se actualiza en tiempo real | WebSocket detenido o mal enrutado | Ejecutar `./scripts/smoke-test.sh` (debe responder 101). Revisar `systemctl is-active reverb` y `journalctl -u reverb`. Una prueba de WebSocket con `curl` debe usar `--http1.1` |
| Error 500 en la aplicación | Error de PHP | Revisar `laravel.log` (sección 7.1) |
| El sitio muestra una versión anterior | Caché de CloudFront o del navegador | Los archivos con huella digital se almacenan un año (comportamiento correcto); `index.html`, cinco minutos. Para invalidar: `aws cloudfront create-invalidation --distribution-id E23ZPGIBRO5LWC --paths "/*"` |
| `terraform plan` propone actualizar Beanstalk o reemplazar recursos | Deriva de configuración o versión nueva de plataforma | No aplicar. Analizar el plan; la plataforma se ignora deliberadamente |
| Un usuario ve **«Demasiados intentos. Espera un minuto…»** (HTTP 429) | Límite de intentos por correo: inicio de sesión 5 por minuto, recuperación de contraseña 3 y restablecimiento 5 | No es una falla: esperar un minuto. El límite es por correo y no por IP, porque detrás de CloudFront todas las peticiones llegan con la IP de CloudFront |
| Un usuario ve **«Tu sesión expiró»** | Los tokens vencen a las 12 horas (`SANCTUM_TOKEN_EXPIRATION_MINUTES`, 720 por defecto) | Iniciar sesión nuevamente |
| La dirección directa de Beanstalk (`…elasticbeanstalk.com`) no responde | `SG-web` solo acepta tráfico de CloudFront | Comportamiento esperado: usar la URL de CloudFront. Para depurar la instancia, `./scripts/run-on-server.sh` (SSM). Reversa: `terraform apply -var restrict_origin_to_cloudfront=false` y la reversa de la sección 7.6 (grupo de Beanstalk). Si en cambio **sí responde**, la regla del grupo `awseb-…` volvió a abrirse (sección 7.6) |
| Tras reiniciar la base de datos todos los usuarios pierden la sesión | Los tokens se eliminan junto con los datos | Comportamiento esperado: se debe iniciar sesión nuevamente |

Verificación de que la contraseña del correo está cargada, sin mostrarla:

```bash
aws ssm get-parameter --region us-east-2 --name /pollo-charly/prod/MAIL_PASSWORD --with-decryption \
  --query Parameter.Value --output text | grep -q sin-configurar && echo "NO cargada" || echo "cargada"
```

## 11. Recreación del entorno desde cero

Aplica a un entorno nuevo o posterior a un `terraform destroy`. Duración aproximada: entre 30 y 45 minutos, en su mayoría
de espera por AWS.

1. **Cuenta y acceso:** un usuario IAM con permisos suficientes, MFA y la AWS CLI configurada en `us-east-2`.
2. **Variables:** crear `infra/terraform.tfvars` (no se versiona):
   ```hcl
   initial_admin_email = "<correo-del-administrador-inicial>"
   smtp_username       = "<cuenta-de-gmail-dedicada>"   # o mail_provider = "ses"
   ```
3. **Infraestructura:**
   ```bash
   cd infra && terraform init && terraform plan && terraform apply
   ```
   Crea la red, RDS, Beanstalk, S3, CloudFront, los parámetros de SSM, los usuarios de GitHub Actions y los presupuestos.
4. **Secretos manuales:** cargar `MAIL_PASSWORD` y `CLOUDINARY_URL` (sección 7.2).
5. **Llaves de GitHub Actions:** crear una llave para cada usuario IAM (`aws iam create-access-key --user-name ...`) y
   guardarlas como secretos del *environment* `production` de cada repositorio (restringido a `main`).
6. **Valores fijos en los workflows.** Al recrear el entorno **cambian** el dominio de CloudFront, el bucket y la
   distribución. Deben actualizarse, con las salidas de `terraform output`, los valores escritos en:
   - backend: `.github/workflows/deploy.yml` → `SITE_URL`.
   - frontend: `.github/workflows/deploy.yml` → `SITE_URL`, `VITE_REVERB_HOST`, `VITE_REVERB_APP_KEY`, `FRONTEND_BUCKET`,
     `CLOUDFRONT_DISTRIBUTION_ID`.

   `APP_URL` y `FRONTEND_URL` del servidor no requieren modificación: Terraform los calcula con el dominio de CloudFront y
   los guarda en SSM.
7. **Primer despliegue:** ejecutar **Desplegar API** y, a continuación, **Publicar frontend** (sección 4).
8. **Primer acceso:** obtener la contraseña inicial del administrador (sección 7.2) y cambiarla desde el perfil.
9. **Verificación:** ejecutar `./scripts/smoke-test.sh` y la lista manual de la sección 5.

**Estado: Probado** en el despliegue inicial.

## 12. Limitaciones conocidas

Restricciones y decisiones que explican la arquitectura actual:

| Limitación | Motivo | Impacto |
|---|---|---|
| Una sola región (`us-east-2`) | Política de la organización de la cuenta gratuita | Sin redundancia regional |
| GitHub Actions utiliza **usuarios IAM con llaves**, no OIDC | La política de la cuenta impide crear el proveedor OIDC | Las llaves deben rotarse manualmente (sección 7.7) |
| Retención automática de RDS de un día | La cuenta gratuita rechaza valores mayores | Compensado con snapshots semanales |
| Una sola instancia, sin balanceador ni Multi-AZ | Objetivo de costo cero | Un fallo de la instancia implica unos minutos de indisponibilidad |
| Sin dominio propio: URL de CloudFront | Costo | No hay dominio personalizado; el certificado lo provee CloudFront |
| Estado de Terraform local | Evita costo e infraestructura adicional | Debe respaldarse; lo opera una sola persona |
| Correo por Gmail SMTP (aproximadamente 500 destinatarios por día) | SES en *sandbox* exige verificar cada destinatario | Suficiente para el proyecto; SES como alternativa |
| Sin colas ni procesos en segundo plano | `QUEUE_CONNECTION=sync` | Los correos se envían dentro de la petición |
| Tramo CloudFront → instancia por HTTP | Con una instancia única y sin dominio propio no se puede emitir un certificado para el origen | El tramo es interno de AWS y la instancia solo acepta tráfico de CloudFront (`SG-web`) |
| Conexión de la API a RDS sin TLS obligatorio | Exigirlo requiere un grupo de parámetros propio y reiniciar la base de datos | Mitigado: subred privada sin ruta a internet y `SG-db` limitado a `SG-web`. Mejora futura |
| Sesiones de 12 horas, sin renovación automática | Simplicidad del esquema de tokens | Pasadas 12 horas el usuario debe iniciar sesión de nuevo (la pantalla lo indica) |
| Los usuarios IAM no pueden tener acceso por consola | La política de la organización del plan gratuito niega `iam:CreateLoginProfile` | Operan por CLI con llaves y MFA, solo cuando es necesario (sección 7.10) |
| La regla abierta del grupo de seguridad que crea Beanstalk se cierra a mano | Lo administra CloudFormation, no Terraform | Repetir el ajuste de la sección 7.6 tras actualizar la plataforma o recrear el entorno |
| Métricas de CloudFront y de Beanstalk no disponibles en CloudWatch | CloudFront publica en `us-east-1` (región no permitida) y Beanstalk usa salud *basic* | La disponibilidad se verifica con `scripts/smoke-test.sh` |

## 13. Desmontaje al finalizar el proyecto

Se debe ejecutar **únicamente una vez concluida la evaluación del proyecto**. El procedimiento es irreversible.

1. **Respaldo final** (opcional): crear un snapshot manual (sección 8). `terraform destroy` elimina la base de datos **sin**
   snapshot final (`db_skip_final_snapshot = true`).
2. **Vaciado del bucket del frontend** (no se elimina si contiene archivos):
   ```bash
   aws s3 rm s3://pollo-charly-prod-frontend-<ID-de-cuenta> --recursive
   ```
3. **Destrucción de la infraestructura:**
   ```bash
   cd infra && terraform destroy
   ```
   Se debe revisar el listado de recursos antes de confirmar. CloudFront tarda aproximadamente 15 minutos en deshabilitarse.
4. **Elementos que Terraform no administra** y que deben eliminarse manualmente:
   - El bucket de paquetes de Beanstalk: `elasticbeanstalk-us-east-2-<ID-de-cuenta>` (vaciar y eliminar).
   - Los snapshots restantes: los manuales (`weekly-*`, `pre-reset-*`, `manual-*`) y los automáticos que permanezcan.
   - Las llaves de acceso de los usuarios IAM de GitHub (los usuarios se eliminan con `terraform destroy`; verificar que no
     queden llaves sueltas).
   - Los archivos de la carpeta `pollo-charly/production/dishes` en Cloudinary.
   - Los secretos del *environment* `production` en GitHub.
5. **Verificación final:** al día siguiente, comprobar en la consola de facturación que no existan servicios activos, y que
   no permanezcan instancias, bases de datos, direcciones IP elásticas, volúmenes ni snapshots.

**Estado: Documentado, no ejecutado.**
