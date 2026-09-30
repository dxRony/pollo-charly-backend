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

resource "random_password" "initial_admin" {
  length  = 20
  special = false
}

resource "aws_ssm_parameter" "initial_admin_email" {
  name  = "${local.ssm_prefix}/INITIAL_ADMIN_EMAIL"
  type  = "String"
  value = var.initial_admin_email
}

resource "aws_ssm_parameter" "initial_admin_name" {
  name  = "${local.ssm_prefix}/INITIAL_ADMIN_NAME"
  type  = "String"
  value = var.initial_admin_name
}

resource "aws_ssm_parameter" "initial_admin_password" {
  name  = "${local.ssm_prefix}/INITIAL_ADMIN_PASSWORD"
  type  = "SecureString"
  value = random_password.initial_admin.result
}

resource "random_string" "reverb_app_key" {
  length  = 20
  special = false
  upper   = false
}

resource "random_password" "reverb_app_secret" {
  length  = 32
  special = false
}

resource "aws_ssm_parameter" "reverb_app_id" {
  name  = "${local.ssm_prefix}/REVERB_APP_ID"
  type  = "String"
  value = "pollo-charly"
}

resource "aws_ssm_parameter" "reverb_app_key" {
  name  = "${local.ssm_prefix}/REVERB_APP_KEY"
  type  = "String"
  value = random_string.reverb_app_key.result
}

resource "aws_ssm_parameter" "reverb_app_secret" {
  name  = "${local.ssm_prefix}/REVERB_APP_SECRET"
  type  = "SecureString"
  value = random_password.reverb_app_secret.result
}
