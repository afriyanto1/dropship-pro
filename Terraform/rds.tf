# RDS Subnet Group (AWS mensyaratkan minimal 2 subnet di AZ berbeda)
resource "aws_db_subnet_group" "rds_subnet_group" {
  name        = "rds-subnet-group"
  subnet_ids  = data.aws_subnets.default.ids
  description = "DB subnet group for test1 instance"

  tags = {
    Name = "rds-subnet-group"
  }
}

# RDS MySQL Community (Free Tier configuration)
resource "aws_db_instance" "mysql_db" {
  identifier             = "test1"
  engine                 = "mysql"
  engine_version         = "8.0"
  instance_class         = "db.t3.micro" # Free tier eligible size
  allocated_storage      = 20            # Minimum free tier storage is 20 GB for RDS
  storage_type           = "gp2"
  db_subnet_group_name   = aws_db_subnet_group.rds_subnet_group.name
  vpc_security_group_ids = [aws_security_group.web_db_sg.id]

  username               = "admin"
  password               = "Test2026."
  parameter_group_name   = "default.mysql8.0"

  skip_final_snapshot    = true
  publicly_accessible    = false

  tags = {
    Name = "test1-rds"
  }
}