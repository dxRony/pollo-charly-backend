output "vpc_id" {
  description = "ID de la VPC."
  value       = aws_vpc.main.id
}

output "public_subnet_id" {
  description = "Subred pública de la API."
  value       = aws_subnet.public.id
}

output "private_subnet_ids" {
  description = "Subredes privadas de la base de datos."
  value       = aws_subnet.private[*].id
}

output "web_security_group_id" {
  description = "Grupo de seguridad de la API (SG-web)."
  value       = aws_security_group.web.id
}

output "db_endpoint" {
  description = "Endpoint de la base de datos RDS."
  value       = aws_db_instance.main.address
}

output "api_environment_name" {
  description = "Nombre del entorno de Elastic Beanstalk."
  value       = aws_elastic_beanstalk_environment.api.name
}

output "api_origin_domain" {
  description = "Dominio del entorno de Beanstalk (origen de la API para CloudFront)."
  value       = aws_elastic_beanstalk_environment.api.cname
}

output "ssm_prefix" {
  description = "Prefijo de los parámetros de configuración en Parameter Store."
  value       = local.ssm_prefix
}
