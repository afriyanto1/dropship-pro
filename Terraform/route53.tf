# Membuat Public Hosted Zone
resource "aws_route53_zone" "public_zone" {
  name = "test-pro.duckdns.org"

  tags = {
    Name = "test-pro.duckdns.org-hosted-zone"
  }
}

# Membuat A Record untuk "www" (Simple Routing, Non-Alias, TTL 300)
resource "aws_route53_record" "www_record" {
  zone_id = aws_route53_zone.public_zone.zone_id
  name    = "www.test-pro.duckdns.org"
  type    = "A"
  ttl     = 300
  records = ["52.64.70.182"]
}