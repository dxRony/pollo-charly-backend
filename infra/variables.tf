variable "aws_region" {
  description = "Región de AWS donde se despliega todo (la cuenta del plan gratuito solo permite us-east-2)."
  type        = string
  default     = "us-east-2"
}

variable "project" {
  description = "Nombre corto del proyecto, usado como prefijo de los recursos."
  type        = string
  default     = "pollo-charly"
}

variable "environment" {
  description = "Nombre del entorno."
  type        = string
  default     = "prod"
}

variable "vpc_cidr" {
  description = "Rango de direcciones de la VPC."
  type        = string
  default     = "10.0.0.0/16"
}

variable "public_subnet_cidr" {
  description = "Rango de la subred pública donde corre la API."
  type        = string
  default     = "10.0.1.0/24"
}

variable "private_subnet_cidrs" {
  description = "Rangos de las subredes privadas (RDS exige subredes en dos zonas de disponibilidad)."
  type        = list(string)
  default     = ["10.0.2.0/24", "10.0.3.0/24"]
}

variable "api_instance_type" {
  description = "Tipo de instancia EC2 que ejecuta la API."
  type        = string
  default     = "t3.micro"
}

variable "initial_admin_email" {
  description = "Correo del administrador inicial del sistema (debe ser un buzón real para recibir 2FA y recuperación de contraseña)."
  type        = string
}

variable "initial_admin_name" {
  description = "Nombre del administrador inicial."
  type        = string
  default     = "Administrador Pollo Charly"
}

variable "db_name" {
  description = "Nombre de la base de datos de la aplicación."
  type        = string
  default     = "pollo_charly"
}

variable "db_username" {
  description = "Usuario administrador de la base de datos."
  type        = string
  default     = "pollo_charly"
}

variable "db_instance_class" {
  description = "Tamaño de la instancia RDS."
  type        = string
  default     = "db.t3.micro"
}

variable "db_allocated_storage" {
  description = "Almacenamiento de la base de datos en GB."
  type        = number
  default     = 20
}

variable "db_backup_retention_days" {
  description = "Días de retención de los respaldos automáticos de RDS (el plan gratuito de AWS rechaza valores mayores a 1)."
  type        = number
  default     = 1
}

variable "db_skip_final_snapshot" {
  description = "Si es true, al destruir la base de datos no se crea un snapshot final (evita costos residuales)."
  type        = bool
  default     = true
}
