# 🛠️ Installation Guide - Uniform Monitoring System

> **Complete step-by-step guide to set up the Uniform Monitoring System**

## 📋 **Table of Contents**

1. [System Requirements](#-system-requirements)
2. [Pre-installation Setup](#-pre-installation-setup)
3. [Database Configuration](#-database-configuration)
4. [PHP Application Setup](#-php-application-setup)
5. [Python AI Environment](#-python-ai-environment)
6. [Web Server Configuration](#-web-server-configuration)
7. [Initial System Setup](#-initial-system-setup)
8. [Testing & Verification](#-testing--verification)
9. [Troubleshooting](#-troubleshooting)

## 💻 **System Requirements**

### **Minimum Requirements**
| Component | Specification |
|-----------|---------------|
| **Operating System** | Windows 10+, Ubuntu 18.04+, macOS 10.15+ |
| **PHP** | 8.2+ with extensions: mysqli, gd, json, session |
| **MySQL** | 8.0+ with InnoDB engine |
| **Python** | 3.10+ with pip package manager |
| **RAM** | 4GB minimum (8GB recommended) |
| **Storage** | 2GB available space |
| **Camera** | USB camera or built-in webcam |
| **Browser** | Chrome 90+, Firefox 88+, Safari 14+, Edge 90+ |

### **Recommended Production Setup**
- **CPU**: Quad-core 2.4GHz or better
- **RAM**: 8GB+ for optimal performance
- **Storage**: SSD for database operations
- **Camera**: 720p+ resolution for better detection
- **Network**: Broadband connection for real-time processing

## 🚀 **Pre-installation Setup**

### **1. Download and Extract**
```bash
# Download the system files
git clone https://github.com/yourusername/uniform-monitoring-system.git
cd uniform-monitoring-system

# Or extract from ZIP file
unzip uniform-monitoring-system.zip
cd uniform-monitoring-system
```

### **2. Install XAMPP (Windows) or LAMP Stack (Linux)**

#### **Windows - XAMPP Installation**
```bash
# Download XAMPP from https://www.apachefriends.org/
# Run installer and select:
# ✅ Apache
# ✅ MySQL
# ✅ PHP
# ✅ phpMyAdmin

# Start XAMPP Control Panel and enable:
# - Apache Web Server
# - MySQL Database
```

#### **Linux - LAMP Stack Installation**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2 mysql-server php php-mysqli php-gd

# CentOS/RHEL
sudo yum install httpd mariadb-server php php-mysqli php-gd

# Start services
sudo systemctl start apache2 mysql
sudo systemctl enable apache2 mysql
```

### **3. Install Python**
```bash
# Windows - Download from python.org
# Linux/Mac
sudo apt install python3 python3-pip python3-venv  # Ubuntu
brew install python3  # macOS
```

## 🗄️ **Database Configuration**

### **1. Create Database**
```bash
# Access MySQL command line
mysql -u root -p

# Create database and user
CREATE DATABASE uniform_detection;
CREATE USER 'uniform_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON uniform_detection.* TO 'uniform_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### **2. Import Database Schema**
```bash
# Import the database structure
mysql -u uniform_user -p uniform_detection < database/uniform_detection.sql
```

### **3. Verify Database Setup**
```bash
# Check tables were created
mysql -u uniform_user -p uniform_detection -e "SHOW TABLES;"

# Expected output:
# +-----------------------------+
# | Tables_in_uniform_detection |
# +-----------------------------+
# | activity_logs               |
# | admin_users                 |
# | detection_results           |
# | email_settings              |
# | penalties                   |
# | students                    |
# | system_settings             |
# +-----------------------------+
```

## 🐘 **PHP Application Setup**

### **1. Configure Database Connection**
```bash
# Copy example configuration
cp db.php.example db.php

# Edit db.php with your credentials
nano db.php  # Linux/Mac
notepad db.php  # Windows
```

**db.php Configuration:**
```php
<?php
// Database configuration
$host = 'localhost';
$username = 'uniform_user';  // Your MySQL username
$password = 'your_secure_password';  // Your MySQL password
$database = 'uniform_detection';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");
?>
```

### **2. Install PHP Dependencies**
```bash
# Install Composer if not already installed
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer  # Linux/Mac

# Install dependencies
composer install
```

### **3. Set Directory Permissions**
```bash
# Linux/Mac - Set proper permissions
chmod 755 uniform-monitoring-system/
chmod 755 uploads/
chmod 755 admin/assets/
chmod 644 *.php
chmod 644 admin/*.php

# Windows - Ensure IIS_IUSRS has read/write access to uploads folder
```

## 🐍 **Python AI Environment**

### **1. Create Virtual Environment**
```bash
cd ai-detection

# Create virtual environment
python -m venv venv

# Activate virtual environment
# Windows
venv\Scripts\activate
# Linux/Mac
source venv/bin/activate
```

### **2. Install Python Dependencies**
```bash
# Ensure virtual environment is activated
pip install --upgrade pip
pip install -r requirements.txt
```

### **3. Verify AI Installation**
```bash
# Test YOLO installation
python -c "import ultralytics; print('YOLOv8 installed successfully')"

# Test OpenCV
python -c "import cv2; print('OpenCV version:', cv2.__version__)"

# Test Flask
python -c "import flask; print('Flask installed successfully')"
```

### **4. Test Camera Access**
```bash
# Test camera detection
python -c "
import cv2
cap = cv2.VideoCapture(0)
if cap.isOpened():
    print('Camera detected successfully')
    cap.release()
else:
    print('Camera not detected - check connections')
"
```

## 🌐 **Web Server Configuration**

### **1. Apache Configuration (XAMPP)**
```apache
# Edit httpd.conf or create .htaccess in project root
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

# Enable necessary modules
LoadModule rewrite_module modules/mod_rewrite.so
```

### **2. Virtual Host Setup (Optional)**
```apache
# Add to httpd-vhosts.conf
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/uniform-monitoring-system"
    ServerName uniform.local
    <Directory "C:/xampp/htdocs/uniform-monitoring-system">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### **3. PHP Configuration**
```ini
# Edit php.ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 512M
session.gc_maxlifetime = 3600

# Enable extensions
extension=mysqli
extension=gd
extension=json
```

## ⚙️ **Initial System Setup**

### **1. Create Admin Account**
```sql
# Insert default admin user
INSERT INTO admin_users (username, password, email, role, created_at) 
VALUES ('admin', MD5('admin123'), 'admin@school.edu', 'super_admin', NOW());
```

### **2. Configure Email Settings**
Access: `http://your-domain/admin/settings.php`

```
SMTP Host: smtp.gmail.com
SMTP Port: 587
SMTP Username: your-email@gmail.com
SMTP Password: your-app-password
From Email: your-email@gmail.com
From Name: Uniform Monitoring System
Use SSL/TLS: Yes
Use Authentication: Yes
```

### **3. Upload Directory Structure**
```bash
# Create upload directories
mkdir -p uploads/students
chmod 755 uploads/students

# Create logs directory
mkdir -p logs
chmod 755 logs
```

### **4. Test Database Connection**
```bash
# Navigate to your web server
http://localhost/uniform-monitoring-system/admin/

# Login with:
# Username: admin
# Password: admin123
```

## 🧪 **Testing & Verification**

### **1. Start Detection Server**
```bash
cd ai-detection
source venv/bin/activate  # Linux/Mac
venv\Scripts\activate     # Windows

python detection_server.py
```

**Expected Output:**
```
 * Running on http://127.0.0.1:5000
 * Camera initialized successfully
 * YOLO model loaded: best2.pt
 * Detection server ready
```

### **2. Test Web Interface**
- **Main Page**: http://localhost/uniform-monitoring-system/
- **Admin Portal**: http://localhost/uniform-monitoring-system/admin/
- **Detection Interface**: http://localhost/uniform-monitoring-system/detect.php

### **3. Verify System Components**

#### **Database Test**
```bash
# Check database connection
php -r "
include 'db.php';
echo 'Database connection: ' . ($conn->ping() ? 'SUCCESS' : 'FAILED') . PHP_EOL;
"
```

#### **AI Detection Test**
```bash
# Test detection endpoint
curl http://localhost:5000/detections
```

#### **Email Test**
```bash
# Send test email from admin panel
# Navigate to Settings > Email Configuration > Send Test Email
```

### **4. Performance Verification**
```bash
# Check system resources
top        # Linux/Mac
taskmgr    # Windows

# Monitor detection server logs
tail -f logs/detection.log
```

## 🔧 **Troubleshooting**

### **Common Issues and Solutions**

#### **Database Connection Failed**
```bash
# Check MySQL service
sudo systemctl status mysql    # Linux
net start mysql               # Windows

# Verify credentials
mysql -u uniform_user -p uniform_detection -e "SELECT 1;"
```

#### **Camera Not Detected**
```bash
# List available cameras (Linux)
lsusb | grep -i camera
v4l2-ctl --list-devices

# Test camera access
python -c "
import cv2
for i in range(10):
    cap = cv2.VideoCapture(i)
    if cap.isOpened():
        print(f'Camera found at index {i}')
        cap.release()
"
```

#### **Python Dependencies Issues**
```bash
# Reinstall requirements
pip uninstall -r requirements.txt -y
pip install -r requirements.txt

# Check CUDA support (optional)
python -c "
import torch
print('CUDA available:', torch.cuda.is_available())
print('CUDA version:', torch.version.cuda if torch.cuda.is_available() else 'N/A')
"
```

#### **Permission Denied Errors**
```bash
# Fix file permissions (Linux/Mac)
chmod -R 755 uniform-monitoring-system/
chown -R www-data:www-data uniform-monitoring-system/  # Ubuntu
chown -R apache:apache uniform-monitoring-system/     # CentOS

# Windows - Check IIS permissions
icacls uniform-monitoring-system /grant IIS_IUSRS:F /T
```

#### **Memory or Performance Issues**
```bash
# Increase PHP memory limit
echo "memory_limit = 1G" >> php.ini

# Optimize MySQL
echo "innodb_buffer_pool_size = 1G" >> my.cnf
```

### **Log File Locations**
- **Apache Logs**: `/var/log/apache2/error.log` (Linux) or `C:\xampp\apache\logs\error.log` (Windows)
- **MySQL Logs**: `/var/log/mysql/error.log` (Linux) or `C:\xampp\mysql\data\mysql_error.log` (Windows)
- **PHP Logs**: Check `php.ini` for `log_errors` and `error_log` settings
- **Detection Server**: `logs/detection.log`

### **Getting Help**
- 📧 **Email Support**: support@uniformmonitoring.com
- 💻 **GitHub Issues**: [Create an issue](https://github.com/yourusername/uniform-monitoring-system/issues)
- 📖 **Documentation**: Check [docs/](docs/) folder for additional guides

---

**Installation Complete! 🎉**

Your Uniform Monitoring System should now be fully operational. Access the admin panel to start configuring students and begin monitoring uniform compliance.