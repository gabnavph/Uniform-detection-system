<?php
// logout.php
session_start();
require_once("../db.php");
require_once("includes/activity_logger.php");

// Log logout activity before destroying session
if (isset($_SESSION['admin_id'])) {
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'Unknown';
    log_activity($conn, ActivityActions::LOGOUT, ActivityTargets::ADMIN, $_SESSION['admin_id'], $admin_name, "User logged out");
}

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
