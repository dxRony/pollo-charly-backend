locals {
  ses_sender_email = coalesce(var.ses_sender_email, var.initial_admin_email)
  ses_identities   = toset(concat([local.ses_sender_email], var.ses_extra_recipients))
}

# Las identidades de SES se conservan aunque el proveedor activo sea SMTP: no cuestan nada y permiten
# volver a SES cambiando solo `mail_provider`, sin repetir las verificaciones.
resource "aws_sesv2_email_identity" "verified" {
  for_each = local.ses_identities

  email_identity = each.value
}
