# AWS Deployment Guide for Mori Cakes Website

## Overview
This guide provides step-by-step instructions for deploying the Mori Cakes website on AWS infrastructure. The deployment follows the AWS Academy Learner Lab scoring requirements.

## Architecture Diagram
```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  EC2 Instance   │────▶│  RDS MySQL      │     │  S3 Bucket      │
│  (Web Server)   │     │  (Database)     │     │  (Static Files) │
└─────────────────┘     └─────────────────┘     └─────────────────┘
        │                         │                         │
        ▼                         ▼                         ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  Elastic Load   │     │  Auto Scaling   │     │  Route 53       │
│  Balancer       │     │  Group          │     │  (DNS)          │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```

## Phase 1: Website Functionality (5 marks)
The website already includes:
- ✅ Homepage with responsive design
- ✅ Add items to cart functionality
- ✅ User authentication system
- ✅ Order history for logged-in users

## Phase 2: Database Implementation (5 marks)
### RDS MySQL Setup
1. Create a MySQL RDS instance:
   - Engine: MySQL 8.0
   - Instance class: db.t3.micro (free tier eligible)
   - Storage: 20GB gp2
   - Multi-AZ: No (for development)
   - Security group: Allow MySQL port 3306 from EC2 security group

2. Database Configuration:
   ```sql
   CREATE DATABASE mori_cakes;
   CREATE USER 'mori_admin'@'%' IDENTIFIED BY 'SecurePassword123!';
   GRANT ALL PRIVILEGES ON mori_cakes.* TO 'mori_admin'@'%';
   FLUSH PRIVILEGES;
   ```

3. Import database schema:
   ```bash
   mysql -h [RDS_ENDPOINT] -u mori_admin -p mori_cakes < php/database_schema.sql
   ```

## Phase 3: Web Application Deployment (5 marks)
### EC2 Instance Setup
1. Launch EC2 instance:
   - AMI: Amazon Linux 2023
   - Instance type: t2.micro (free tier eligible)
   - Security group: Allow HTTP (80), HTTPS (443), SSH (22)
   - Storage: 8GB gp2

2. Install required software:
   ```bash
   sudo yum update -y
   sudo yum install -y httpd php php-mysqlnd php-fpm php-cli php-mbstring php-xml php-gd php-zip
   sudo systemctl start httpd
   sudo systemctl enable httpd
   ```

3. Deploy website files:
   ```bash
   cd /var/www/html
   sudo git clone [your-repo-url] .
   sudo chown -R apache:apache /var/www/html
   sudo chmod -R 755 /var/www/html
   ```

4. Update database configuration:
   ```bash
   sudo nano php/config.php
   # Update with RDS credentials
   ```

## Phase 4: Scalability and High Availability (10 marks)
### Auto Scaling Group
1. Create launch template from existing EC2 instance
2. Create Auto Scaling group:
   - Desired capacity: 2 instances
   - Minimum capacity: 1 instance
   - Maximum capacity: 4 instances
   - Scaling policies:
     - Target tracking: Average CPU utilization 70%
     - Step scaling: Add 1 instance when CPU > 80%

### Elastic Load Balancer
1. Create Application Load Balancer:
   - Listeners: HTTP:80, HTTPS:443
   - Availability zones: Multiple AZs
   - Security group: Allow HTTP/HTTPS
   - Target group: Register Auto Scaling group

### Route 53 DNS
1. Create hosted zone for your domain
2. Create A record pointing to ALB
3. Create CNAME records for subdomains if needed

## Security Configuration
1. Configure security groups:
   - EC2: Allow traffic from ALB only
   - RDS: Allow traffic from EC2 security group only
   - ALB: Allow public HTTP/HTTPS traffic

2. Enable HTTPS:
   - Request SSL certificate from ACM
   - Configure ALB to use SSL certificate

## Monitoring and Logging
1. Enable CloudWatch monitoring:
   - CPU utilization
   - Memory usage
   - Request count
   - HTTP 5xx errors

2. Set up CloudWatch alarms:
   - CPU > 80% for 5 minutes
   - HTTP 5xx errors > 10% for 3 minutes

## Backup Strategy
1. RDS automated backups:
   - Daily backups
   - Retention period: 7 days
2. Manual snapshots before major changes

## Cost Optimization
1. Use reserved instances for baseline capacity
2. Implement auto scaling to reduce costs during low traffic
3. Use S3 Intelligent-Tiering for static assets
4. Clean up unused resources

## Deployment Checklist
- [ ] RDS MySQL instance created
- [ ] Database schema imported
- [ ] EC2 instance launched and configured
- [ ] Website files deployed
- [ ] Auto Scaling group configured
- [ ] Load Balancer created
- [ ] DNS records updated
- [ ] Security groups configured
- [ ] HTTPS enabled
- [ ] Monitoring set up
- [ ] Backup strategy implemented

## Troubleshooting
Common issues and solutions:
1. **Database connection errors**: Check security groups and RDS endpoint
2. **500 Internal Server Error**: Check PHP error logs at `/var/log/php-fpm/`
3. **404 Not Found**: Verify file permissions and Apache configuration
4. **High CPU usage**: Check for traffic spikes or resource-intensive processes

## Performance Optimization
1. Enable PHP OPcache
2. Configure Apache caching
3. Optimize database queries
4. Use CDN for static assets
5. Implement database indexing

## Disaster Recovery
1. Multi-AZ deployment for RDS
2. Cross-region backups
3. Regular disaster recovery testing
4. Documented recovery procedures

This deployment architecture meets all the requirements specified in the AWS Academy Learner Lab scoring rubric while maintaining a cost-effective and maintainable infrastructure.