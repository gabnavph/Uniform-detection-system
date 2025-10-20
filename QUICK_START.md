# 🚀 Quick Deployment Guide

> **Fast-track setup for experienced developers**

## ⚡ **30-Second Setup (Local Development)**

```bash
# 1. Extract and navigate
unzip uniform-monitoring-system-v2.0.0.zip
cd uniform-monitoring-system

# 2. Database setup
mysql -u root -p -e "CREATE DATABASE uniform_detection"
mysql -u root -p uniform_detection < database/uniform_detection.sql

# 3. Configure database
cp db.php.example db.php
# Edit db.php with your credentials

# 4. Install dependencies
composer install

# 5. Python AI setup
cd ai-detection
python -m venv venv
venv\Scripts\activate  # Windows
source venv/bin/activate  # Linux/Mac
pip install -r requirements.txt

# 6. Start system
python detection_server.py  # Terminal 1
php -S localhost:8000  # Terminal 2 (from root)
```

## 🎯 **Quick Access**
- **Website**: http://localhost:8000
- **Admin**: http://localhost:8000/admin
- **Login**: admin / admin123

## 📋 **Production Checklist**
- [ ] Change default admin password
- [ ] Configure email settings
- [ ] Set proper file permissions
- [ ] Enable SSL/HTTPS
- [ ] Configure backup strategy
- [ ] Test camera detection

## 🔧 **Common Issues**
- **Camera not detected**: Check USB connection, try different camera index
- **Database error**: Verify credentials in db.php
- **Python errors**: Ensure virtual environment is activated
- **Permission denied**: Set proper folder permissions (755 for directories)

## 📱 **Mobile Access**
The system is mobile-responsive. Access admin panel from tablets/phones using the same URLs.

---
*For detailed setup instructions, see [docs/INSTALLATION.md](docs/INSTALLATION.md)*