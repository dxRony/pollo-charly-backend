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
    sid       = "LeerBucketDePaquetesDeBeanstalk"
    actions   = ["s3:ListBucket*", "s3:GetBucket*"]
    resources = ["arn:aws:s3:::${local.eb_artifacts_bucket}"]
  }

  # Beanstalk copia cada versión a una carpeta interna del bucket usando las credenciales de quien
  # despliega, por lo que necesita leer, escribir, cambiar permisos y borrar objetos ahí.
  statement {
    sid       = "GestionarPaquetesDentroDelBucketDeBeanstalk"
    actions   = ["s3:*Object*"]
    resources = ["arn:aws:s3:::${local.eb_artifacts_bucket}/*"]
  }

  # Al actualizar un entorno, Beanstalk verifica con las credenciales de quien llama que puede
  # leer los recursos subyacentes. Solo lectura, y la pila de CloudFormation acotada al entorno.
  statement {
    sid = "LeerPilaDeCloudFormationDelEntorno"
    actions = [
      "cloudformation:DescribeStacks",
      "cloudformation:DescribeStackResource",
      "cloudformation:DescribeStackResources",
      "cloudformation:GetTemplate",
      "cloudformation:ListStackResources",
    ]
    resources = ["arn:aws:cloudformation:${var.aws_region}:${local.account_id}:stack/awseb-${aws_elastic_beanstalk_environment.api.id}-stack/*"]
  }

  statement {
    sid = "LeerRecursosSubyacentesDelEntorno"
    actions = [
      "cloudformation:ListStacks",
      "cloudformation:ValidateTemplate",
      "autoscaling:DescribeAccountLimits",
      "autoscaling:DescribeAutoScalingGroups",
      "autoscaling:DescribeAutoScalingInstances",
      "autoscaling:DescribeLaunchConfigurations",
      "autoscaling:DescribeScalingActivities",
      "ec2:DescribeAccountAttributes",
      "ec2:DescribeAddresses",
      "ec2:DescribeAvailabilityZones",
      "ec2:DescribeImages",
      "ec2:DescribeInstanceAttribute",
      "ec2:DescribeInstances",
      "ec2:DescribeInstanceStatus",
      "ec2:DescribeKeyPairs",
      "ec2:DescribeLaunchTemplates",
      "ec2:DescribeLaunchTemplateVersions",
      "ec2:DescribeSecurityGroups",
      "ec2:DescribeSubnets",
      "ec2:DescribeVpcs",
      "elasticloadbalancing:DescribeLoadBalancers",
      "elasticloadbalancing:DescribeTargetGroups",
      "elasticloadbalancing:DescribeTargetHealth",
      "cloudwatch:DescribeAlarms",
      "cloudwatch:GetMetricStatistics",
      "cloudwatch:ListMetrics",
    ]
    resources = ["*"]
  }

  # Durante un despliegue Beanstalk pausa y reanuda los procesos del grupo de autoescalado
  # para que no reemplace la instancia a mitad de la actualización.
  statement {
    sid       = "PausarAutoescaladoDuranteElDespliegue"
    actions   = ["autoscaling:SuspendProcesses", "autoscaling:ResumeProcesses"]
    resources = ["arn:aws:autoscaling:${var.aws_region}:${local.account_id}:autoScalingGroup:*:autoScalingGroupName/awseb-${aws_elastic_beanstalk_environment.api.id}-stack-AWSEBAutoScalingGroup-*"]
  }

  statement {
    sid = "LeerRolesDelEntorno"
    actions = [
      "iam:GetRole",
      "iam:ListAttachedRolePolicies",
      "iam:ListRolePolicies",
    ]
    resources = [aws_iam_role.api_instance.arn, aws_iam_role.eb_service.arn]
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

# Administrada (no en línea): las políticas en línea de un usuario admiten solo 2048 bytes.
resource "aws_iam_policy" "github_backend_deploy" {
  name        = "${local.name}-gha-backend-deploy"
  description = "Despliegue de la API en Elastic Beanstalk desde GitHub Actions"
  policy      = data.aws_iam_policy_document.github_backend_deploy.json
}

resource "aws_iam_user_policy_attachment" "github_backend_deploy" {
  user       = aws_iam_user.github_backend.name
  policy_arn = aws_iam_policy.github_backend_deploy.arn
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
