resource "aws_instance" "web_server" {
  ami                         = data.aws_ami.ubuntu.id
  instance_type               = "t3.micro"
  subnet_id                   = data.aws_subnet.rds_pvt_subnet_1.id
  associate_public_ip_address = true
  vpc_security_group_ids      = [aws_security_group.web_db_sg.id]
  key_name                    = null # Proceed without keypair

  # Storage 10 GB gp3
  root_block_device {
    volume_size           = 10
    volume_type           = "gp3"
    delete_on_termination = true
  }

  # Enable resource-based ipv4 (A record) DNS request
  private_dns_name_options {
    enable_resource_name_dns_a_record = true
  }

  # Skrip bash otomatis untuk instalasi paket
  user_data = <<-EOF
              #!/bin/bash
              export DEBIAN_FRONTEND=noninteractive
              
              apt update -y
              apt upgrade -y
              apt install nginx -y
              systemctl status nginx || true
              apt install git -y
              apt install php php-cli php-common php-mysql php-curl php-gd php-mbstring php-xml php-zip -y
              apt install php8.5-fpm -y
              apt install php8.5-mysql -y
              EOF

  tags = {
    Name = "test1"
  }
}