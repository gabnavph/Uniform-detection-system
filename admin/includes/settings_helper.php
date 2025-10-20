<?php
// ================================
// settings_helper.php
// Core Settings Functions
// ================================

// Get setting value by key
if (!function_exists('get_setting')) {
    function get_setting(mysqli $conn, string $key, $default = '') {
        $k = $conn->real_escape_string($key);
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='{$k}' LIMIT 1");
        if ($res && $res->num_rows) {
            $row = $res->fetch_assoc();
            return $row['setting_value'];
        }
        return $default;
    }
}

// Set or update setting value
if (!function_exists('set_setting')) {
    function set_setting(mysqli $conn, string $key, $value) {
        $k = $conn->real_escape_string($key);
        $v = $conn->real_escape_string((string)$value);
        $conn->query("
            INSERT INTO settings (setting_key, setting_value)
            VALUES ('{$k}', '{$v}')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
    }
}

// Check if the current user is Super Admin
if (!function_exists('is_super_admin')) {
    function is_super_admin(mysqli $conn): bool {
        if (!isset($_SESSION['admin_id'])) return false;
        $aid = (int)$_SESSION['admin_id'];

        // If admins table has role column, check it
        $res = $conn->query("SHOW COLUMNS FROM admins LIKE 'role'");
        if ($res && $res->num_rows) {
            $q = $conn->query("SELECT role FROM admins WHERE id={$aid} LIMIT 1");
            if ($q && $q->num_rows) {
                $role = $q->fetch_assoc()['role'] ?? 'admin';
                return $role === 'super';
            }
        }

        // Fallback: user with ID 1 is super admin
        return $aid === 1;
    }
}
?>
