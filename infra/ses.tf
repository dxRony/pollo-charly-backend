locals {
  ses_sender_email = coalesce(var.ses_sender_email, var.initial_admin_email)
  ses_identities   = toset(concat([local.ses_sender_email], var.ses_extra_recipients))
}

resource "aws_sesv2_email_identity" "verified" {
  for_each = local.ses_identities

  email_identity = each.value
}

resource "aws_ssm_parameter" "mail_mailer" {
  name  = "${local.ssm_prefix}/MAIL_MAILER"
  type  = "String"
  value = "ses"
}

resource "aws_ssm_parameter" "mail_from_address" {
  name  = "${local.ssm_prefix}/MAIL_FROM_ADDRESS"
  type  = "String"
  value = local.ses_sender_email
}
