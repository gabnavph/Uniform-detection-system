<?php
session_start();
require_once("../db.php");
require_once("includes/settings_helper.php");
require_once("includes/activity_logger.php");

// Load dynamic settings
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');
$school_logo = get_setting($conn, 'school_logo', '');

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$error = "";
$step = 1; // 1 = enter code, 2 = enter new password

// Process reset code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_code'])) {
        // Step 1: Validate reset code
        $reset_code = trim($_POST['reset_code'] ?? '');
        
        if ($reset_code === '') {
            $error = "Please enter the reset code.";
        } elseif (!preg_match('/^\d{6}$/', $reset_code)) {
            $error = "Reset code must be exactly 6 digits.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, fullname FROM admins WHERE reset_token = ? AND status = 'active'");
            $stmt->bind_param("s", $reset_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_code'] = $reset_code;
                $step = 2;
            } else {
                $error = "Invalid reset code. Please request a new password reset.";
            }
        }
    } elseif (isset($_POST['new_password']) && isset($_SESSION['reset_user_id'])) {
        // Step 2: Reset password
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $user_id = $_SESSION['reset_user_id'];
        $reset_code = $_SESSION['reset_code'];
        
        if ($new_password === '' || $confirm_password === '') {
            $error = "Please fill in all fields.";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 2;
        } else {
            // Update password and clear reset code
            $hashed_password = md5($new_password);
            $stmt = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ? AND reset_token = ?");
            $stmt->bind_param("sis", $hashed_password, $user_id, $reset_code);
            
            if ($stmt->execute()) {
                // Get user info for logging
                $user_query = $conn->prepare("SELECT username, fullname FROM admins WHERE id = ?");
                $user_query->bind_param("i", $user_id);
                $user_query->execute();
                $user_result = $user_query->get_result();
                $user = $user_result->fetch_assoc();
                
                $message = "Password has been successfully reset. You can now login with your new password.";
                
                // Log the password reset
                log_activity($conn, ActivityActions::PASSWORD_RESET_COMPLETED, ActivityTargets::ADMIN, $user_id, $user['fullname'], "Password reset completed for user: {$user['username']}");
                
                // Clear session variables
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_code']);
                $step = 3; // Success step
            } else {
                $error = "Failed to reset password. Please try again.";
                $step = 2;
            }
        }
    }
}

// Check if we're continuing from step 1
if (isset($_SESSION['reset_user_id']) && !isset($_POST['reset_code'])) {
    $step = 2;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($system_name); ?> — Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        .auth-card {
            max-width: 450px;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>

<body style="background:#f7f7f9;">

<div class="auth-card">
    <div class="text-center mb-3">
        <!-- Dynamic Logo -->
        <?php if ($school_logo): ?>
            <img src="../<?php echo htmlspecialchars($school_logo); ?>" 
                 alt="Logo" style="width:68px;height:68px;border-radius:50%;border:2px solid #D4AF37;"
                 onerror="this.style.display='none'">
        <?php endif; ?>

        <!-- Dynamic System Name -->
        <h4 class="mt-2 mb-0" style="color:#7B1113;">
            Reset Password
        </h4>

        <!-- Dynamic School Name -->
        <div style="font-size:12px;color:#888;">
            <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $message; ?>
            <br><br>
            <a href="login.php" class="btn btn-primary btn-sm">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Login
            </a>
        </div>
    <?php endif; ?>

    <!-- Error Alert -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1 && !$message): ?>
    <!-- Step 1: Enter Reset Code -->
    <div class="alert alert-info" style="font-size: 14px;">
        <i class="fas fa-envelope me-2"></i>
        Enter the 6-digit reset code that was sent to your email address.
    </div>

    <form method="post" autocomplete="off">
        <div class="mb-3">
            <label class="form-label">Reset Code</label>
            <input type="text" name="reset_code" class="form-control text-center" 
                   style="font-size: 18px; letter-spacing: 2px;" 
                   placeholder="000000" 
                   maxlength="6" 
                   pattern="\d{6}" 
                   required>
            <div class="form-text">
                <i class="fas fa-info-circle me-1"></i>
                The code remains valid until used
            </div>
        </div>

        <button class="btn btn-primary w-100" type="submit">
            <i class="fas fa-check me-2"></i>Verify Code
        </button>
    </form>
    <?php endif; ?>

    <?php if ($step === 2 && !$message): ?>
    <!-- Step 2: Enter New Password -->
    <div class="alert alert-success" style="font-size: 14px;">
        <i class="fas fa-check-circle me-2"></i>
        Reset code verified! Now enter your new password.
    </div>

    <form method="post" autocomplete="off">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
            <div class="password-requirements">
                <i class="fas fa-info-circle me-1"></i>
                Minimum 6 characters required
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required minlength="6">
        </div>

        <button class="btn btn-primary w-100" type="submit">
            <i class="fas fa-key me-2"></i>Reset Password
        </button>
    </form>
    <?php endif; ?>

    <?php if ($step === 1 && !$message): ?>
    <!-- Step 1 Actions -->
    <div class="text-center mt-3">
        <a href="forgot_password.php" class="text-decoration-none" style="font-size: 14px;">
            <i class="fas fa-redo me-1"></i>Didn't receive code? Request new one
        </a>
    </div>
    <?php endif; ?>

    <!-- Back to Login -->
    <div class="text-center mt-3">
        <a href="login.php" class="text-decoration-none" style="font-size: 14px;">
            <i class="fas fa-arrow-left me-1"></i>Back to Login
        </a>
    </div>

    <!-- Footer Credit -->
    <footer class="text-center mt-3" style="font-size:12px;color:#666;">
        Developed by: <strong>BSIT Capstone Group – System Naton</strong> (AY 2025–2026)
    </footer>
</div>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>