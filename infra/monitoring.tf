# Alarmas de CloudWatch sobre la base de datos (OPCIONAL). El plan gratuito incluye 10 alarmas y el envío de correos
# por SNS. Las métricas de CloudFront viven en us-east-1 (región no permitida en esta cuenta) y Beanstalk con salud
# "basic" no publica métricas, por eso la disponibilidad de la API se vigila con scripts/smoke-test.sh (runbook, sección 5).
#
# Para recibir correos: define alert_email en terraform.tfvars, aplica y confirma la suscripción desde el mensaje de AWS.

resource "aws_sns_topic" "alerts" {
  count = var.alert_email == null ? 0 : 1

  name = "${local.name}-alertas"
}

resource "aws_sns_topic_subscription" "alerts_email" {
  count = var.alert_email == null ? 0 : 1

  topic_arn = aws_sns_topic.alerts[0].arn
  protocol  = "email"
  endpoint  = var.alert_email
}

locals {
  alarm_actions = var.alert_email == null ? [] : [aws_sns_topic.alerts[0].arn]
}

resource "aws_cloudwatch_metric_alarm" "db_free_storage" {
  alarm_name          = "${local.name}-bd-poco-espacio"
  alarm_description   = "Quedan menos de 4 GB libres en la base de datos (de 20 GB)."
  namespace           = "AWS/RDS"
  metric_name         = "FreeStorageSpace"
  dimensions          = { DBInstanceIdentifier = aws_db_instance.main.identifier }
  statistic           = "Average"
  period              = 300
  evaluation_periods  = 2
  comparison_operator = "LessThanThreshold"
  threshold           = 4 * 1024 * 1024 * 1024
  treat_missing_data  = "notBreaching"
  alarm_actions       = local.alarm_actions
  ok_actions          = local.alarm_actions
}

resource "aws_cloudwatch_metric_alarm" "db_cpu" {
  alarm_name          = "${local.name}-bd-cpu-alta"
  alarm_description   = "La CPU de la base de datos supera el 80 % durante 15 minutos."
  namespace           = "AWS/RDS"
  metric_name         = "CPUUtilization"
  dimensions          = { DBInstanceIdentifier = aws_db_instance.main.identifier }
  statistic           = "Average"
  period              = 300
  evaluation_periods  = 3
  comparison_operator = "GreaterThanThreshold"
  threshold           = 80
  treat_missing_data  = "notBreaching"
  alarm_actions       = local.alarm_actions
  ok_actions          = local.alarm_actions
}

resource "aws_cloudwatch_metric_alarm" "db_memory" {
  alarm_name          = "${local.name}-bd-poca-memoria"
  alarm_description   = "Quedan menos de 100 MB de memoria libre en la base de datos durante 10 minutos."
  namespace           = "AWS/RDS"
  metric_name         = "FreeableMemory"
  dimensions          = { DBInstanceIdentifier = aws_db_instance.main.identifier }
  statistic           = "Average"
  period              = 300
  evaluation_periods  = 2
  comparison_operator = "LessThanThreshold"
  threshold           = 100 * 1024 * 1024
  treat_missing_data  = "notBreaching"
  alarm_actions       = local.alarm_actions
  ok_actions          = local.alarm_actions
}
