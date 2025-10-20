# 👤 User Manual - Uniform Monitoring System

> **Complete guide for administrators, officers, and staff to use the Uniform Monitoring System effectively**

## 📚 **Table of Contents**

1. [Getting Started](#-getting-started)
2. [Admin Portal Overview](#-admin-portal-overview)
3. [Student Management](#-student-management)
4. [Penalty System](#-penalty-system)
5. [AI Detection Interface](#-ai-detection-interface)
6. [Reports & Analytics](#-reports--analytics)
7. [System Configuration](#-system-configuration)
8. [User Roles & Permissions](#-user-roles--permissions)
9. [Best Practices](#-best-practices)
10. [FAQ & Troubleshooting](#-faq--troubleshooting)

## 🚀 **Getting Started**

### **System Access**
- **Main Website**: http://your-domain/
- **Admin Portal**: http://your-domain/admin/
- **Detection Interface**: http://your-domain/detect.php

### **Default Login Credentials**
```
Username: admin
Password: admin123
```
> ⚠️ **Important**: Change default password immediately after first login!

### **First Time Setup Checklist**
- [ ] Change default admin password
- [ ] Configure email settings
- [ ] Add admin users
- [ ] Import student data
- [ ] Test camera detection
- [ ] Configure system settings

## 🏛️ **Admin Portal Overview**

### **Dashboard Elements**

#### **📊 Statistics Cards**
- **Total Students**: Current active student count
- **Pending Penalties**: Unpaid violation fines
- **Total Penalties**: All penalty records
- **Recent Activities**: Latest system actions

#### **📈 Quick Actions**
- **Add New Student**: Direct link to student registration
- **View Penalties**: Access penalty management
- **System Logs**: Recent activity monitoring
- **Settings**: System configuration access

#### **🔔 Notifications Panel**
- Recent login activities
- System alerts and warnings
- Pending approvals or actions

### **Navigation Menu**
- **🏠 Dashboard**: Main overview page
- **👥 Students**: Student management interface
- **💰 Penalties**: Violation and payment tracking
- **📊 Reports**: Analytics and reporting tools
- **📧 Logs**: Activity and system logs
- **💳 Payments**: Payment processing (if enabled)
- **⚙️ Settings**: System configuration
- **🚪 Logout**: Secure session termination

## 👥 **Student Management**

### **Adding New Students**

#### **Manual Entry**
1. Navigate to **Students** → **Add New Student**
2. Fill in required information:
   - **Personal Details**: Name, gender, date of birth
   - **Contact Information**: Phone, email, address
   - **Academic Information**: Year level, section
   - **Emergency Contacts**: Guardian details
   - **Photo Upload**: Profile picture (optional)

3. Click **"Add Student"** to save

#### **CSV Import Process**
1. Go to **Students** → **Import Students**
2. Download the CSV template
3. Fill the template with student data:
```csv
student_id,first_name,last_name,middle_name,gender,date_of_birth,contact_number,email,address,emergency_contact,emergency_phone,guardian_name,guardian_phone,guardian_email,year_level,section
2024001,John,Doe,Michael,MALE,2006-05-15,+1234567890,john.doe@school.edu,123 Main St,Jane Doe,+1234567891,Robert Doe,+1234567892,robert.doe@email.com,11,A
```
4. Upload the CSV file
5. Review import preview
6. Confirm import to add students

### **Managing Existing Students**

#### **Viewing Student List**
- **Search**: Use the search bar to find specific students
- **Filter**: Filter by year level, section, or status
- **Sort**: Click column headers to sort data
- **Pagination**: Navigate through multiple pages

#### **Student Actions**
- **👁️ View**: See complete student profile
- **✏️ Edit**: Modify student information
- **📧 Email**: Send direct email to student/guardian
- **🗑️ Archive**: Soft delete student record
- **📄 History**: View activity history

#### **Bulk Operations**
- **Select Multiple**: Use checkboxes to select students
- **Bulk Email**: Send emails to selected students
- **Bulk Archive**: Archive multiple students
- **Export Selected**: Download student data as CSV

### **Student Profile Management**

#### **Photo Upload**
1. Click student's profile image or **"Upload Photo"**
2. Select image file (JPG, PNG, GIF)
3. Crop if needed
4. Save changes

#### **Information Updates**
- Edit any field by clicking the **✏️ Edit** button
- Changes are automatically logged in activity history
- Email notifications sent for significant changes

### **Year-End Student Management**

#### **Graduation Process**
1. Navigate to **Settings** → **Graduation**
2. Choose graduation method:
   - **CSV Upload**: Bulk graduate via CSV
   - **Manual Selection**: Select individual students
3. Review selected students
4. Confirm graduation to archive students

#### **Student Advancement**
1. Go to **Settings** → **Advancement**
2. Select advancement method:
   - **Advance All Students**: Automatic year level progression
   - **Failed Students CSV**: Upload list of students to retain
   - **Individual Selection**: Manual student selection
3. Process advancement
4. Verify year level updates

## 💰 **Penalty System**

### **Creating Penalties**

#### **Manual Penalty Entry**
1. Navigate to **Penalties** → **Add New Penalty**
2. Select student from dropdown
3. Enter violation details:
   - **Violation Type**: Choose from predefined types
   - **Description**: Detailed violation description
   - **Penalty Amount**: Fine amount
   - **Due Date**: Payment deadline
4. Save penalty record

#### **Automated Detection Penalties**
- System automatically creates penalties for uniform violations
- Based on AI detection results
- Configurable penalty amounts per violation type

### **Managing Penalties**

#### **Penalty Status Tracking**
- **🟡 PENDING**: Awaiting payment
- **🟢 PAID**: Payment completed
- **🔵 WAIVED**: Penalty waived by admin

#### **Payment Processing**
1. Locate penalty record
2. Click **"Mark as Paid"**
3. Enter payment details:
   - **Payment Method**: Cash, bank transfer, etc.
   - **Payment Notes**: Transaction reference
   - **Payment Date**: When payment was received
4. Save payment information

#### **Payment Reports**
- **Daily Reports**: Daily payment summaries
- **Monthly Reports**: Monthly financial reports
- **Student Reports**: Individual payment history
- **Export Options**: PDF and CSV formats

### **Violation Types Configuration**
Common violation types include:
- **Missing ID Card**
- **Improper Uniform Top**
- **Wrong Bottom Garment**
- **Missing or Wrong Shoes**
- **Incomplete Uniform**
- **Non-compliant Accessories**

## 🤖 **AI Detection Interface**

### **Real-time Monitoring**

#### **Detection Screen Layout**
- **Live Video Feed**: Real-time camera stream
- **Detection Results**: Current scan results
- **Compliance Status**: Pass/Fail indicator
- **Action Buttons**: Manual controls

#### **Detection Process**
1. Student stands in front of camera
2. AI analyzes uniform components:
   - **ID Card Detection**
   - **Top Garment Analysis**
   - **Bottom Garment Check**
   - **Footwear Verification**
3. Results displayed in real-time
4. Automatic penalty creation for violations

### **Manual Override Options**
- **Force Pass**: Override AI decision
- **Force Fail**: Mark as non-compliant
- **Retake Scan**: Restart detection process
- **Add Notes**: Additional observations

### **Detection Settings**
- **Confidence Threshold**: AI sensitivity adjustment
- **Detection Classes**: Enable/disable specific items
- **Auto-Penalty**: Automatic violation recording
- **Save Images**: Store detection screenshots

## 📊 **Reports & Analytics**

### **Student Reports**
- **Student List**: Complete student directory
- **Graduation Report**: Students ready for graduation
- **Demographics**: Student distribution by year/section
- **Contact Lists**: Emergency contact information

### **Penalty Reports**
- **Daily Penalty Report**: Daily violation summary
- **Monthly Financial Report**: Payment tracking
- **Outstanding Penalties**: Unpaid violations
- **Violation Trends**: Pattern analysis

### **System Reports**
- **Activity Logs**: User action history
- **Login Reports**: Access tracking
- **Detection Statistics**: AI performance metrics
- **Error Logs**: System error tracking

### **Exporting Data**
- **PDF Reports**: Professional formatted reports
- **CSV Export**: Data for external analysis
- **Email Reports**: Automated report delivery
- **Scheduled Reports**: Regular report generation

## ⚙️ **System Configuration**

### **General Settings**

#### **School Information**
- **School Name**: Institution name
- **Address**: Complete school address
- **Contact Information**: Phone and email
- **Academic Year**: Current academic year

#### **System Preferences**
- **Timezone**: Local timezone setting
- **Date Format**: Preferred date display
- **Currency**: Payment currency
- **Language**: Interface language

### **Email Configuration**

#### **SMTP Settings**
```
SMTP Host: smtp.gmail.com
SMTP Port: 587
Security: STARTTLS
Username: your-email@school.edu
Password: your-app-password
```

#### **Email Templates**
- **Welcome Email**: New student notifications
- **Penalty Notice**: Violation notifications
- **Payment Reminder**: Overdue payment alerts
- **System Alerts**: Administrative notifications

### **Detection Settings**

#### **AI Configuration**
- **Model Selection**: Choose detection model
- **Confidence Threshold**: Detection sensitivity (0.1-1.0)
- **Processing Speed**: Frame rate settings
- **Save Detections**: Store detection images

#### **Violation Rules**
- **Required Items**: Mandatory uniform components
- **Optional Items**: Configurable requirements
- **Penalty Amounts**: Fine amounts per violation
- **Grace Periods**: Warning before penalties

### **User Management**

#### **Admin User Roles**
- **Super Admin**: Full system access
- **Officer**: Student and penalty management
- **Viewer**: Read-only access

#### **Adding New Admins**
1. Navigate to **Settings** → **Admin Users**
2. Click **"Add New Admin"**
3. Enter user details:
   - **Username**: Unique identifier
   - **Email**: Contact email
   - **Role**: Permission level
   - **Password**: Initial password
4. Save new admin account

## 👤 **User Roles & Permissions**

### **Super Admin**
- ✅ Full system access
- ✅ User management
- ✅ System configuration
- ✅ Database management
- ✅ All reports and exports

### **Officer**
- ✅ Student management (CRUD)
- ✅ Penalty management
- ✅ Basic reports
- ✅ Detection interface
- ❌ System settings
- ❌ User management

### **Viewer**
- ✅ View students (read-only)
- ✅ View penalties (read-only)
- ✅ Basic reports
- ✅ Detection monitoring
- ❌ Data modification
- ❌ System configuration

## 💡 **Best Practices**

### **Data Management**
- **Regular Backups**: Weekly database backups
- **Data Validation**: Verify imported data accuracy
- **Consistent Naming**: Use standard naming conventions
- **Photo Standards**: Consistent photo quality and size

### **Security Guidelines**
- **Strong Passwords**: Use complex passwords
- **Regular Updates**: Change passwords quarterly
- **Session Security**: Always logout when finished
- **Access Control**: Limit user permissions appropriately

### **Detection Optimization**
- **Proper Lighting**: Ensure adequate lighting
- **Camera Position**: Optimal camera placement
- **Background**: Use consistent background
- **Student Positioning**: Clear view of uniform components

### **System Maintenance**
- **Log Monitoring**: Regular log file review
- **Performance Checks**: Monitor system performance
- **Update Management**: Keep system components updated
- **User Training**: Regular staff training sessions

## ❓ **FAQ & Troubleshooting**

### **Common Questions**

#### **Q: How do I reset a forgotten password?**
A: Use the "Forgot Password" link on the login page, or contact a Super Admin to reset your password.

#### **Q: Why isn't the camera detecting uniforms properly?**
A: Check lighting conditions, camera position, and ensure the detection confidence threshold is properly configured.

#### **Q: How do I bulk import students?**
A: Use the CSV import feature in the Students section. Download the template and follow the format exactly.

#### **Q: Can I modify penalty amounts after creation?**
A: Yes, Officers and Super Admins can edit penalty amounts before payment is processed.

### **Common Issues**

#### **Login Problems**
- Verify username and password
- Check browser cookies and cache
- Ensure JavaScript is enabled
- Contact administrator if account is locked

#### **Detection Issues**
- Check camera connection
- Verify Python detection server is running
- Test camera with other applications
- Review detection logs for errors

#### **Data Import Errors**
- Verify CSV format matches template
- Check for duplicate student IDs
- Ensure all required fields are populated
- Review import error messages

#### **Email Not Working**
- Verify SMTP settings in configuration
- Check internet connectivity
- Test with known working email credentials
- Review email service provider requirements

### **Getting Additional Help**
- **Documentation**: Check the complete documentation in `/docs/`
- **Contact Support**: Email technical support
- **System Logs**: Review logs for error details
- **User Community**: Check GitHub issues and discussions

---

**System Version**: 2.0  
**Last Updated**: October 2025  
**Next Update**: Check GitHub repository for latest releases

*This manual covers all major features of the Uniform Monitoring System. For advanced configuration and development information, refer to the technical documentation.*