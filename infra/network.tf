locals {
  name = "${var.project}-${var.environment}"
  azs  = slice(data.aws_availability_zones.available.names, 0, 2)
}

data "aws_availability_zones" "available" {
  state = "available"

  filter {
    name   = "opt-in-status"
    values = ["opt-in-not-required"]
  }
}

resource "aws_vpc" "main" {
  cidr_block           = var.vpc_cidr
  enable_dns_support   = true
  enable_dns_hostnames = true

  tags = { Name = "${local.name}-vpc" }
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id

  tags = { Name = "${local.name}-igw" }
}

resource "aws_subnet" "public" {
  vpc_id                  = aws_vpc.main.id
  cidr_block              = var.public_subnet_cidr
  availability_zone       = local.azs[0]
  map_public_ip_on_launch = true

  tags = { Name = "${local.name}-public-${local.azs[0]}" }
}

resource "aws_subnet" "private" {
  count = length(var.private_subnet_cidrs)

  vpc_id            = aws_vpc.main.id
  cidr_block        = var.private_subnet_cidrs[count.index]
  availability_zone = local.azs[count.index]

  tags = { Name = "${local.name}-private-${local.azs[count.index]}" }
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }

  tags = { Name = "${local.name}-public-rt" }
}

resource "aws_route_table_association" "public" {
  subnet_id      = aws_subnet.public.id
  route_table_id = aws_route_table.public.id
}

resource "aws_route_table" "private" {
  vpc_id = aws_vpc.main.id

  tags = { Name = "${local.name}-private-rt" }
}

resource "aws_route_table_association" "private" {
  count = length(aws_subnet.private)

  subnet_id      = aws_subnet.private[count.index].id
  route_table_id = aws_route_table.private.id
}

data "aws_ec2_managed_prefix_list" "cloudfront" {
  name = "com.amazonaws.global.cloudfront.origin-facing"
}

resource "aws_security_group" "web" {
  name        = "${local.name}-sg-web"
  description = "Entrada HTTP y HTTPS hacia la instancia de la API"
  vpc_id      = aws_vpc.main.id

  # La instancia solo debe recibir tráfico de CloudFront (la API y el WebSocket se publican por su dominio).
  # La lista de prefijos administrada por AWS se actualiza sola cuando CloudFront cambia de direcciones.
  # La descripción del grupo no se modifica porque AWS no permite cambiarla sin reemplazar el grupo.
  dynamic "ingress" {
    for_each = var.restrict_origin_to_cloudfront ? [1] : []
    content {
      description     = "HTTP solo desde CloudFront"
      from_port       = 80
      to_port         = 80
      protocol        = "tcp"
      prefix_list_ids = [data.aws_ec2_managed_prefix_list.cloudfront.id]
    }
  }

  # Entrada abierta anterior: solo se usa si restrict_origin_to_cloudfront = false (reversa rápida).
  dynamic "ingress" {
    for_each = var.restrict_origin_to_cloudfront ? [] : [80, 443]
    content {
      description = "Entrada abierta (puerto ${ingress.value})"
      from_port   = ingress.value
      to_port     = ingress.value
      protocol    = "tcp"
      cidr_blocks = ["0.0.0.0/0"]
    }
  }

  egress {
    description = "Salida sin restricciones"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = { Name = "${local.name}-sg-web" }
}

resource "aws_security_group" "db" {
  name        = "${local.name}-sg-db"
  description = "MySQL solo desde el grupo de seguridad de la API"
  vpc_id      = aws_vpc.main.id

  ingress {
    description     = "MySQL desde SG-web"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }

  tags = { Name = "${local.name}-sg-db" }
}
