<?php
session_start();
require_once("../db.php");
require_once("includes/settings_helper.php");
require_once("includes/activity_logger.php");

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("../PHPMailer/src/Exception.php");
require_once("../PHPMailer/src/PHPMailer.php");
require_once("../PHPMailer/src/SMTP.php");

// Load dynamic settings
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');
$school_logo = get_setting($conn, 'school_logo', '');

// SMTP settings
$smtp_host = get_setting($conn, 'smtp_host', 'smtp-relay.brevo.com');
$smtp_user = get_setting($conn, 'smtp_user', '');
$smtp_pass = get_setting($conn, 'smtp_pass', '');
$smtp_sender = get_setting($conn, 'smtp_sender_name', 'Uniform Monitoring System');

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$error = "";

function send_reset_email($toEmail, $toName, $resetCode, $smtp) {
    if (!$toEmail) return false;
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtp['host'] ?: 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['user'] ?: '';
        $mail->Password = $smtp['pass'] ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $fromAddress = 'nikkapatriciafrancisco@gmail.com';
        $fromName = $smtp['sender'] ?: 'Uniform Monitoring System';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($toEmail, $toName ?? '');

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Code';
        
        $html = "Dear " . htmlspecialchars($toName) . ",<br><br>"
              . "You have requested to reset your password for the " . htmlspecialchars($smtp['sender']) . ".<br><br>"
              . "Your password reset code is: <strong style='font-size: 18px; color: #7B1113;'>" . $resetCode . "</strong><br><br>"
              . "This code will remain valid until you use it to reset your password. If you did not request this password reset, please ignore this email.<br><br>"
              . "<em>— " . htmlspecialchars($smtp['sender']) . "<br>" . htmlspecialchars($GLOBALS['school_name']) . "</em>";
              
        $text = "Dear " . $toName . ",\n\n"
              . "You have requested to reset your password.\n\n"
              . "Your password reset code is: " . $resetCode . "\n\n"
              . "This code will remain valid until you use it to reset your password. If you did not request this password reset, please ignore this email.\n\n"
              . "— " . $smtp['sender'] . "\n" . $GLOBALS['school_name'];

        $mail->Body = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if ($username === '') {
        $error = "Please enter your username.";
    } else {
        $u = $conn->real_escape_string($username);
        $sql = "SELECT id, username, fullname, email FROM admins WHERE username='$u' AND status='active' LIMIT 1";
        $res = $conn->query($sql);
        
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            
            if (empty($row['email'])) {
                $error = "No email address found for this account. Please contact the administrator.";
            } else {
                // Generate 6-digit reset code
                $reset_code = sprintf('%06d', mt_rand(100000, 999999));
                
                // Store reset code in database (no expiration - only expires when used)
                $stmt = $conn->prepare("UPDATE admins SET reset_token = ?, reset_token_expires = NULL WHERE id = ?");
                $stmt->bind_param("si", $reset_code, $row['id']);
                
                if ($stmt->execute()) {
                    // Send email with reset code
                    $emailSent = send_reset_email(
                        $row['email'],
                        $row['fullname'] ?: $row['username'],
                        $reset_code,
                        ['host' => $smtp_host, 'user' => $smtp_user, 'pass' => $smtp_pass, 'sender' => $smtp_sender]
                    );
                    
                    if ($emailSent) {
                        $message = "A 6-digit reset code has been sent to your email address. The code will remain valid until you use it to reset your password.";
                        
                        // Log the password reset request
                        log_activity($conn, ActivityActions::PASSWORD_RESET_REQUEST, ActivityTargets::ADMIN, $row['id'], $row['fullname'], "Password reset code sent to email for user: {$row['username']}");
                    } else {
                        $error = "Failed to send reset code. Please try again or contact the administrator.";
                    }
                } else {
                    $error = "Failed to generate reset code. Please try again.";
                }
            }
        } else {
            // Don't reveal if username exists or not for security
            $message = "If the username exists and has an email address, a reset code has been sent.";
            
            // Log failed attempt
            log_activity($conn, ActivityActions::PASSWORD_RESET_REQUEST, null, null, null, "Password reset requested for non-existent username: $username");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($system_name); ?> — Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        .auth-card {
            max-width: 450px;
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
            Forgot Password
        </h4>

        <!-- Dynamic School Name -->
        <div style="font-size:12px;color:#888;">
            <?php echo htmlspecialchars($school_name); ?>
        </div>
    </div>

    <!-- Success Message -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="fas fa-envelope me-2"></i>
            <?php echo $message; ?>
            <br><br>
            <a href="reset_password.php" class="btn btn-primary btn-sm">
                <i class="fas fa-key me-2"></i>Enter Reset Code
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

    <?php if (!$message): ?>
    <!-- Instructions -->
    <div class="alert alert-info" style="font-size: 14px;">
        <i class="fas fa-info-circle me-2"></i>
        Enter your username below and we'll send a 6-digit reset code to your email address.
    </div>

    <!-- Forgot Password Form -->
    <form method="post" autocomplete="off">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
        </div>

        <button class="btn btn-primary w-100" type="submit">
            <i class="fas fa-paper-plane me-2"></i>Send Reset Code
        </button>
    </form>
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