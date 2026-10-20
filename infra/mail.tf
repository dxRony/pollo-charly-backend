locals {
  use_smtp       = var.mail_provider == "smtp"
  smtp_available = var.smtp_username != null
}

resource "aws_ssm_parameter" "mail_mailer" {
  name  = "${local.ssm_prefix}/MAIL_MAILER"
  type  = "String"
  value = local.use_smtp ? "smtp" : "ses"
}

# Con Gmail el remitente siempre es la propia cuenta, por lo que debe coincidir con el usuario SMTP.
resource "aws_ssm_parameter" "mail_from_address" {
  name  = "${local.ssm_prefix}/MAIL_FROM_ADDRESS"
  type  = "String"
  value = local.use_smtp ? var.smtp_username : local.ses_sender_email
}

# Los datos SMTP existen siempre que se configure la cuenta (aunque el proveedor activo sea SES), para
# que cambiar de proveedor no obligue a volver a cargar el secreto.
resource "aws_ssm_parameter" "mail_host" {
  count = local.smtp_available ? 1 : 0

  name  = "${local.ssm_prefix}/MAIL_HOST"
  type  = "String"
  value = var.smtp_host
}

resource "aws_ssm_parameter" "mail_port" {
  count = local.smtp_available ? 1 : 0

  name  = "${local.ssm_prefix}/MAIL_PORT"
  type  = "String"
  value = tostring(var.smtp_port)
}

resource "aws_ssm_parameter" "mail_username" {
  count = local.smtp_available ? 1 : 0

  name  = "${local.ssm_prefix}/MAIL_USERNAME"
  type  = "String"
  value = var.smtp_username
}

# La contraseña de aplicación NO pasa por Terraform: se carga después con
# `aws ssm put-parameter --overwrite` para que no quede en el estado ni en git.
resource "aws_ssm_parameter" "mail_password" {
  count = local.smtp_available ? 1 : 0

  name  = "${local.ssm_prefix}/MAIL_PASSWORD"
  type  = "SecureString"
  value = "sin-configurar"

  lifecycle {
    ignore_changes = [value]
  }
}
