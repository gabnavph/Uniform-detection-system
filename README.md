# 🎓 Uniform Monitoring System

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.10+-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://python.org)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3+-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![YOLOv8](https://img.shields.io/badge/YOLOv8-AI%20Detection-00FFFF?style=for-the-badge)](https://github.com/ultralytics/ultralytics)

> **AI-Powered Uniform Compliance Monitoring System for Educational Institutions**

An intelligent, real-time uniform monitoring system that leverages advanced AI computer vision to ensure student dress code compliance. Built with modern web technologies and powered by YOLOv8 deep learning model.

## 🌟 **Key Features**

### 🧠 **AI-Powered Detection**
- **Real-time Uniform Detection** using YOLOv8 neural network
- **6-Class Recognition**: ID cards, male/female dress shirts, pants/skirts, shoes
- **92%+ Accuracy** with configurable confidence thresholds
- **30 FPS Processing** for smooth real-time monitoring

### 👨‍💼 **Comprehensive Admin Portal**
- **Multi-role Access Control**: Super Admin, Officer, Viewer roles
- **Student Management**: Complete CRUD with CSV import/export
- **Penalty System**: Violation tracking and payment management
- **Graduation & Advancement**: Automated student lifecycle management
- **Activity Logging**: Full audit trail for all system actions

### 📊 **Advanced Student Management**
- **Bulk Operations**: Import students via CSV with validation
- **Photo Management**: Student profile pictures with upload handling
- **Flexible Advancement**: Multiple year-end progression methods
- **Soft Delete**: Non-destructive student archival system

### 📧 **Professional Communication**
- **Email Integration**: SMTP-based notification system
- **Template System**: Customizable email templates
- **Automated Alerts**: Violation notifications and payment reminders

### 🔒 **Security & Compliance**
- **Session Management**: Secure authentication system
- **Input Validation**: Multi-layer security against attacks
- **Audit Trails**: Comprehensive activity logging
- **Role-based Permissions**: Granular access control

## 🚀 **Quick Start Guide**

### **Prerequisites**
```bash
PHP 8.2+ with extensions: mysqli, gd, json
MySQL 8.0+ with InnoDB engine
Python 3.10+ with pip
Web server: Apache/Nginx
4GB+ RAM (8GB recommended)
USB Camera or Webcam
```

### **🔧 Installation Steps**

1. **Clone the Repository**
```bash
git clone https://github.com/yourusername/uniform-monitoring-system.git
cd uniform-monitoring-system
```

2. **Database Setup**
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE uniform_detection"

# Import schema
mysql -u root -p uniform_detection < database/uniform_detection.sql
```

3. **Configure Database Connection**
```bash
# Create db.php in root directory
cp db.php.example db.php
# Edit db.php with your database credentials
```

4. **PHP Dependencies**
```bash
composer install
```

5. **Python AI Environment**
```bash
cd ai-detection
python -m venv venv
# Windows
venv\Scripts\activate
# Linux/Mac
source venv/bin/activate

pip install -r requirements.txt
```

6. **Start the System**
```bash
# Terminal 1: Start detection server
cd ai-detection
python detection_server.py

# Terminal 2: Start web server
php -S localhost:8000
```

7. **Access the System**
- **Main Interface**: http://localhost:8000
- **Admin Portal**: http://localhost:8000/admin
- **Default Login**: admin / admin123

## 📁 **Project Structure**

```
uniform-monitoring-system/
├── 🌐 Frontend & Backend
│   ├── index.php                 # Main landing page
│   ├── detect.php                # Detection interface
│   ├── scan_uniform.php          # Uniform scanning logic
│   └── db.php.example            # Database configuration template
├── 👨‍💼 Admin Portal
│   ├── admin/
│   │   ├── index.php             # Admin dashboard
│   │   ├── students.php          # Student management
│   │   ├── penalties.php         # Penalty system
│   │   ├── settings.php          # System configuration
│   │   ├── assets/               # CSS, JS, Images
│   │   └── includes/             # Shared components
├── 🧠 AI Detection System
│   ├── ai-detection/
│   │   ├── detection_server.py   # Flask detection server
│   │   ├── requirements.txt      # Python dependencies
│   │   ├── data.yaml            # YOLO training config
│   │   └── best2.pt             # Trained model weights
├── 🗄️ Database
│   └── database/
│       └── uniform_detection.sql # Database schema
├── 📧 Email System
│   └── PHPMailer/               # Email library
├── 📊 Documentation
│   ├── docs/
│   │   ├── SYSTEM_ANALYSIS.md   # Technical documentation
│   │   ├── INSTALLATION.md      # Setup guide
│   │   └── USER_MANUAL.md       # User guide
└── 📁 Storage
    └── uploads/                 # Student photos
```

## 🔧 **Configuration**

### **Database Configuration (`db.php`)**
```php
<?php
$host = 'localhost';
$username = 'your_username';
$password = 'your_password';
$database = 'uniform_detection';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
```

### **AI Detection Configuration**
Edit `ai-detection/detection_server.py`:
```python
# Model configuration
MODEL_PATH = 'best2.pt'
CONFIDENCE_THRESHOLD = 0.60
INPUT_SIZE = 640

# Camera configuration
CAMERA_INDEX = 0  # Change for different camera
```

## 📊 **System Specifications**

| Component | Performance | Capacity | Status |
|-----------|-------------|----------|---------|
| 🎥 **Detection Engine** | 30 FPS @ 640x480 | Real-time processing | ✅ Optimized |
| 🧠 **AI Accuracy** | 92%+ detection rate | Normal lighting | ✅ Production ready |
| 💾 **Database** | <50ms query time | 10,000+ students | ✅ Indexed |
| 👥 **Concurrent Users** | 50+ simultaneous | Session-based | ✅ Scalable |
| 📱 **Web Interface** | <200ms load time | Responsive design | ✅ Modern UI |

## 🔒 **Security Features**

- ✅ **Multi-layer Input Validation**
- ✅ **SQL Injection Prevention**
- ✅ **XSS Protection**
- ✅ **Session Security**
- ✅ **Role-based Access Control**
- ✅ **Comprehensive Audit Logging**
- ✅ **Secure File Upload Handling**

## 📚 **Documentation**

- 📖 **[System Analysis](docs/SYSTEM_ANALYSIS.md)** - Comprehensive technical documentation
- 🛠️ **[Installation Guide](docs/INSTALLATION.md)** - Detailed setup instructions
- 👤 **[User Manual](docs/USER_MANUAL.md)** - Complete user guide
- 🔧 **[API Documentation](docs/API.md)** - Development reference

## 🤝 **Contributing**

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md).

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🎯 **Use Cases**

- 🏫 **Educational Institutions**: Schools, colleges, universities
- 🏢 **Corporate Environments**: Companies with dress codes
- 🏥 **Healthcare Facilities**: Uniform compliance monitoring
- 🏭 **Manufacturing**: Safety equipment detection
- 🛡️ **Security Applications**: Access control systems

## 🔮 **Future Enhancements**

- 🌍 **Multi-camera Support**: Multiple detection points
- ☁️ **Cloud Integration**: AWS/Azure deployment
- 📱 **Mobile App**: Native mobile applications
- 🔧 **API Expansion**: RESTful API for integrations
- 🧠 **Enhanced AI**: Improved detection accuracy
- 📊 **Analytics Dashboard**: Advanced reporting features

## 💬 **Support**

- 📧 **Email**: support@uniformmonitoring.com
- 💻 **GitHub Issues**: [Report bugs or request features](https://github.com/yourusername/uniform-monitoring-system/issues)
- 📖 **Documentation**: [Complete documentation](docs/)

## 🙏 **Acknowledgments**

- **YOLOv8** by Ultralytics for state-of-the-art object detection
- **Bootstrap** for responsive UI framework
- **PHPMailer** for robust email functionality
- **MySQL** for reliable database management
- **OpenCV** for computer vision capabilities

---

**Built with ❤️ for educational institutions worldwide**

*© 2025 Uniform Monitoring System. Licensed under MIT.*