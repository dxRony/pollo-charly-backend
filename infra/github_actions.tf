locals {
  account_id          = data.aws_caller_identity.current.account_id
  eb_artifacts_bucket = "elasticbeanstalk-${var.aws_region}-${local.account_id}"
  eb_application_name = aws_elastic_beanstalk_application.api.name
  eb_environment_name = aws_elastic_beanstalk_environment.api.name
}

data "aws_iam_policy_document" "github_backend_deploy" {
  statement {
    sid = "PublicarVersionesEnBeanstalk"
    actions = [
      "elasticbeanstalk:CreateApplicationVersion",
      "elasticbeanstalk:UpdateEnvironment",
    ]
    resources = [
      "arn:aws:elasticbeanstalk:${var.aws_region}:${local.account_id}:application/${local.eb_application_name}",
      "arn:aws:elasticbeanstalk:${var.aws_region}:${local.account_id}:applicationversion/${local.eb_application_name}/*",
      "arn:aws:elasticbeanstalk:${var.aws_region}:${local.account_id}:environment/${local.eb_application_name}/${local.eb_environment_name}",
    ]
  }

  statement {
    sid = "ConsultarEstadoDelDespliegue"
    actions = [
      "elasticbeanstalk:DescribeEnvironments",
      "elasticbeanstalk:DescribeApplicationVersions",
      "elasticbeanstalk:DescribeEvents",
      "elasticbeanstalk:DescribeEnvironmentHealth",
      "elasticbeanstalk:DescribeEnvironmentResources",
      "elasticbeanstalk:DescribeConfigurationSettings",
      "elasticbeanstalk:CreateStorageLocation",
    ]
    resources = ["*"]
  }

  statement {
    sid       = "SubirPaquetesAlBucketDeBeanstalk"
    actions   = ["s3:PutObject", "s3:GetObject", "s3:ListBucket", "s3:GetBucketLocation"]
    resources = ["arn:aws:s3:::${local.eb_artifacts_bucket}", "arn:aws:s3:::${local.eb_artifacts_bucket}/*"]
  }
}

data "aws_iam_policy_document" "github_frontend_deploy" {
  statement {
    sid       = "ListarBucketDelFrontend"
    actions   = ["s3:ListBucket", "s3:GetBucketLocation"]
    resources = [aws_s3_bucket.frontend.arn]
  }

  statement {
    sid       = "PublicarArchivosDelFrontend"
    actions   = ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"]
    resources = ["${aws_s3_bucket.frontend.arn}/*"]
  }

  statement {
    sid       = "InvalidarCacheDeCloudFront"
    actions   = ["cloudfront:CreateInvalidation", "cloudfront:GetInvalidation"]
    resources = [aws_cloudfront_distribution.main.arn]
  }
}

# La cuenta del plan gratuito bloquea iam:CreateOpenIDConnectProvider, por lo que GitHub Actions
# no puede usar OIDC. Se usa un usuario IAM por repositorio, con permisos mínimos y llaves creadas
# fuera de Terraform (para que no queden guardadas en el estado).
resource "aws_iam_user" "github_backend" {
  name          = "${local.name}-gha-backend"
  force_destroy = true
}

resource "aws_iam_user_policy" "github_backend_deploy" {
  name   = "deploy-beanstalk"
  user   = aws_iam_user.github_backend.name
  policy = data.aws_iam_policy_document.github_backend_deploy.json
}

resource "aws_iam_user" "github_frontend" {
  name          = "${local.name}-gha-frontend"
  force_destroy = true
}

resource "aws_iam_user_policy" "github_frontend_deploy" {
  name   = "publish-frontend"
  user   = aws_iam_user.github_frontend.name
  policy = data.aws_iam_policy_document.github_frontend_deploy.json
}
