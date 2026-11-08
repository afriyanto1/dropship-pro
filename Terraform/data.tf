# Mengambil Default VPC
data "aws_vpc" "default" {
  default = true
}

# Mengambil semua subnet di default VPC (untuk kebutuhan RDS Subnet Group multi-AZ)
data "aws_subnets" "default" {
  filter {
    name   = "vpc-id"
    values = [data.aws_vpc.default.id]
  }
}

# Mengambil subnet spesifik berdasarkan nama tag "RDS-Pvt-subnet-1"
data "aws_subnet" "rds_pvt_subnet_1" {
  filter {
    name   = "tag:Name"
    values = ["RDS-Pvt-subnet-1"]
  }
  vpc_id = data.aws_vpc.default.id
}

# Mengambil AMI Ubuntu Server 26.04 LTS terbaru dari Canonical
data "aws_ami" "ubuntu" {
  most_recent = true
  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-*-26.04-amd64-server-*"]
  }
  filter {
    name   = "virtualization-type"
    values = ["hvm"]
  }
  owners = ["099720109477"] # Canonical ID
}