locals {
  ssm_prefix = "/${var.project}/${var.environment}"
}

resource "random_id" "app_key" {
  byte_length = 32
}

resource "aws_ssm_parameter" "app_key" {
  name  = "${local.ssm_prefix}/APP_KEY"
  type  = "SecureString"
  value = "base64:${random_id.app_key.b64_std}"
}

resource "aws_ssm_parameter" "db_host" {
  name  = "${local.ssm_prefix}/DB_HOST"
  type  = "String"
  value = aws_db_instance.main.address
}

resource "aws_ssm_parameter" "db_port" {
  name  = "${local.ssm_prefix}/DB_PORT"
  type  = "String"
  value = tostring(aws_db_instance.main.port)
}

resource "aws_ssm_parameter" "db_database" {
  name  = "${local.ssm_prefix}/DB_DATABASE"
  type  = "String"
  value = var.db_name
}

resource "aws_ssm_parameter" "db_username" {
  name  = "${local.ssm_prefix}/DB_USERNAME"
  type  = "String"
  value = var.db_username
}

resource "aws_ssm_parameter" "db_password" {
  name  = "${local.ssm_prefix}/DB_PASSWORD"
  type  = "SecureString"
  value = random_password.db.result
}
