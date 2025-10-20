<?php
// admin/includes/activity_logger.php
// Activity logging helper functions

function log_activity($conn, $action, $target_type = null, $target_id = null, $target_name = null, $details = null) {
    // Get admin info from session
    $admin_id = $_SESSION['admin_id'] ?? null;
    $admin_username = $_SESSION['username'] ?? 'system';
    
    // Get client info
    $ip_address = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Truncate user agent if too long
    if (strlen($user_agent) > 500) {
        $user_agent = substr($user_agent, 0, 500);
    }
    
    // Prepare SQL
    $sql = "INSERT INTO activity_logs (admin_id, admin_username, action, target_type, target_id, target_name, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(
            "issssisss", 
            $admin_id, 
            $admin_username, 
            $action, 
            $target_type, 
            $target_id, 
            $target_name, 
            $details, 
            $ip_address, 
            $user_agent
        );
        $stmt->execute();
        $stmt->close();
    }
}

function get_client_ip() {
    // Check for various headers that might contain the real IP
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            // If it's a comma-separated list, get the first valid IP
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                foreach ($ips as $single_ip) {
                    $single_ip = trim($single_ip);
                    if (filter_var($single_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $single_ip;
                    }
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

// Predefined action constants for consistency
class ActivityActions {
    // Authentication
    const LOGIN = 'User Login';
    const LOGOUT = 'User Logout';
    const LOGIN_FAILED = 'Login Failed';
    const PASSWORD_RESET_REQUEST = 'Password Reset Requested';
    const PASSWORD_RESET_COMPLETED = 'Password Reset Completed';
    
    // Student Management
    const STUDENT_CREATED = 'Student Created';
    const STUDENT_UPDATED = 'Student Updated';
    const STUDENT_DELETED = 'Student Deleted';
    const STUDENT_PHOTO_UPLOADED = 'Student Photo Uploaded';
    
    // Uniform Scanning
    const UNIFORM_SCANNED = 'Uniform Scanned';
    const PENALTY_CREATED = 'Penalty Created';
    const VIOLATION_DETECTED = 'Violation Detected';
    const COMPLIANCE_VERIFIED = 'Compliance Verified';
    
    // Payment Management
    const PENALTY_PAID = 'Penalty Marked as Paid';
    const PENALTY_UNPAID = 'Penalty Marked as Unpaid';
    const PAYMENT_RECORDED = 'Payment Recorded';
    
    // Admin Management
    const ADMIN_CREATED = 'Admin User Created';
    const ADMIN_UPDATED = 'Admin User Updated';
    const ADMIN_DELETED = 'Admin User Deleted';
    const ADMIN_STATUS_CHANGED = 'Admin Status Changed';
    
    // Settings
    const SETTINGS_UPDATED = 'System Settings Updated';
    const CONFIG_CHANGED = 'Configuration Changed';
    
    // Reports
    const REPORT_GENERATED = 'Report Generated';
    const DATA_EXPORTED = 'Data Exported';
    
    // System
    const SYSTEM_BACKUP = 'System Backup';
    const SYSTEM_MAINTENANCE = 'System Maintenance';
}

// Target types for better organization
class ActivityTargets {
    const STUDENT = 'student';
    const PENALTY = 'penalty';
    const PAYMENT = 'payment';
    const ADMIN = 'admin';
    const SETTING = 'setting';
    const REPORT = 'report';
    const SYSTEM = 'system';
}
?>