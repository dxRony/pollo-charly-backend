# Usuarios IAM de operación, uno por integrante. Cada uno tiene su propia política (permisos de consulta más unas pocas
# acciones operativas acotadas) y no puede hacer nada hasta activar un dispositivo MFA.
#
# La contraseña de consola NO se define aquí (quedaría en el estado de Terraform): la crea el administrador con
# `aws iam create-login-profile` (runbook, sección 7.10).

data "aws_iam_policy_document" "team_operator" {
  for_each = var.team_members

  statement {
    sid = "AdministrarSuPropiaCuentaYMfa"
    actions = [
      "iam:ChangePassword",
      "iam:GetUser",
      "iam:ListMFADevices",
      "iam:CreateVirtualMFADevice",
      "iam:EnableMFADevice",
      "iam:ResyncMFADevice",
      "iam:DeactivateMFADevice",
      "iam:DeleteVirtualMFADevice",
      "iam:ListVirtualMFADevices",
      "iam:GetAccountPasswordPolicy",
    ]
    resources = [
      "arn:aws:iam::${local.account_id}:user/$${aws:username}",
      "arn:aws:iam::${local.account_id}:mfa/$${aws:username}",
      "arn:aws:iam::${local.account_id}:mfa/*",
    ]
  }

  statement {
    sid       = "CrearSnapshotsManualesDeLaBaseDeDatos"
    actions   = ["rds:CreateDBSnapshot", "rds:AddTagsToResource"]
    resources = [aws_db_instance.main.arn, "arn:aws:rds:${var.aws_region}:${local.account_id}:snapshot:manual-*"]
  }

  statement {
    sid = "OperarElEntornoDeLaApi"
    actions = [
      "elasticbeanstalk:RestartAppServer",
      "elasticbeanstalk:RequestEnvironmentInfo",
      "elasticbeanstalk:RetrieveEnvironmentInfo",
    ]
    resources = ["arn:aws:elasticbeanstalk:${var.aws_region}:${local.account_id}:environment/${aws_elastic_beanstalk_application.api.name}/${aws_elastic_beanstalk_environment.api.name}"]
  }

  # Todo lo demás exige haber iniciado sesión con MFA.
  statement {
    sid         = "ExigirMfaParaTodoLoDemas"
    effect      = "Deny"
    not_actions = ["iam:ChangePassword", "iam:GetUser", "iam:ListMFADevices", "iam:CreateVirtualMFADevice", "iam:EnableMFADevice", "iam:ResyncMFADevice", "iam:ListVirtualMFADevices", "iam:GetAccountPasswordPolicy", "sts:GetCallerIdentity"]
    resources   = ["*"]

    condition {
      test     = "BoolIfExists"
      variable = "aws:MultiFactorAuthPresent"
      values   = ["false"]
    }
  }
}

resource "aws_iam_user" "team" {
  for_each = var.team_members

  name          = "${local.name}-${each.key}"
  force_destroy = true

  tags = { Purpose = "operacion-integrante" }
}

resource "aws_iam_policy" "team_operator" {
  for_each = var.team_members

  name        = "${local.name}-${each.key}-operator"
  description = "Consulta de la infraestructura y operaciones acotadas para ${each.key} (requiere MFA)"
  policy      = data.aws_iam_policy_document.team_operator[each.key].json
}

resource "aws_iam_user_policy_attachment" "team_operator" {
  for_each = var.team_members

  user       = aws_iam_user.team[each.key].name
  policy_arn = aws_iam_policy.team_operator[each.key].arn
}

resource "aws_iam_user_policy_attachment" "team_view_only" {
  for_each = var.team_members

  user       = aws_iam_user.team[each.key].name
  policy_arn = "arn:aws:iam::aws:policy/job-function/ViewOnlyAccess"
}
