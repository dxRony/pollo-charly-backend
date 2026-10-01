resource "aws_db_subnet_group" "main" {
  name       = "${local.name}-db-subnets"
  subnet_ids = aws_subnet.private[*].id

  tags = { Name = "${local.name}-db-subnets" }
}

resource "random_password" "db" {
  length  = 32
  special = false
}

resource "aws_db_instance" "main" {
  identifier = "${local.name}-mysql"

  engine         = "mysql"
  engine_version = "8.4"
  instance_class = var.db_instance_class

  allocated_storage = var.db_allocated_storage
  storage_type      = "gp3"
  storage_encrypted = true

  db_name  = var.db_name
  username = var.db_username
  password = random_password.db.result

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.db.id]
  publicly_accessible    = false
  multi_az               = false

  backup_retention_period = var.db_backup_retention_days
  backup_window           = "07:00-08:00"
  maintenance_window      = "sun:08:30-sun:09:30"
  copy_tags_to_snapshot   = true

  auto_minor_version_upgrade   = true
  performance_insights_enabled = false
  deletion_protection          = false
  skip_final_snapshot          = var.db_skip_final_snapshot
  apply_immediately            = true

  tags = { Name = "${local.name}-mysql" }
}
