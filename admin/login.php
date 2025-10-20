<?php
session_start();
require_once("../db.php");
require_once("includes/settings_helper.php");
require_once("includes/activity_logger.php");

// Load dynamic settings
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');
$school_logo = get_setting($conn, 'school_logo', ''); // optional logo

// If already logged in, redirect
if (isset($_SESSION['admin_id'])) {
  header("Location: index.php");
  exit();
}

$err = "";
$remember_user = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : "";
$login_suspended = false;
$suspension_time = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($username === '' || $password === '') {
        $err = "Please enter username and password.";
        // Log failed attempt
        log_activity($conn, ActivityActions::LOGIN_FAILED, null, null, null, "Empty username or password for: $username");
    } else {
        $u = $conn->real_escape_string($username);
        
        // Check if user exists and get their login status
        $check_sql = "SELECT id, username, fullname, role, password, failed_login_attempts, login_suspended_until FROM admins WHERE username='$u' AND status='active' LIMIT 1";
        $check_res = $conn->query($check_sql);
        
        if ($check_res && $check_res->num_rows === 1) {
            $user = $check_res->fetch_assoc();
            
            // Check if login is currently suspended
            if ($user['login_suspended_until'] && strtotime($user['login_suspended_until']) > time()) {
                $suspension_time = strtotime($user['login_suspended_until']) - time();
                $login_suspended = true;
                $err = "Account temporarily suspended due to multiple failed login attempts. Try again in " . $suspension_time . " seconds.";
                log_activity($conn, ActivityActions::LOGIN_FAILED, ActivityTargets::ADMIN, $user['id'], $user['fullname'], "Login attempt during suspension for user: {$user['username']}");
            } else {
                // Clear suspension if time has passed
                if ($user['login_suspended_until']) {
                    $conn->query("UPDATE admins SET login_suspended_until = NULL, failed_login_attempts = 0 WHERE id = " . $user['id']);
                }
                
                // Check password
                $p = md5($password);
                if ($user['password'] === $p) {
                    // Successful login - reset failed attempts
                    $conn->query("UPDATE admins SET failed_login_attempts = 0, last_failed_login = NULL, login_suspended_until = NULL WHERE id = " . $user['id']);
                    
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['admin_name'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];

                    // Log successful login
                    log_activity($conn, ActivityActions::LOGIN, ActivityTargets::ADMIN, $user['id'], $user['fullname'], "Successful login for user: {$user['username']}");

                    if ($remember) {
                        setcookie('remember_username', $username, time()+60*60*24*30, '/');
                    } else {
                        setcookie('remember_username', '', time()-3600, '/');
                    }
                    header("Location: index.php");
                    exit();
                } else {
                    // Failed password - increment failed attempts
                    $failed_attempts = (int)$user['failed_login_attempts'] + 1;
                    
                    if ($failed_attempts >= 3) {
                        // Suspend for 5 seconds after 3 failed attempts
                        $suspend_until = date('Y-m-d H:i:s', time() + 5);
                        $conn->query("UPDATE admins SET failed_login_attempts = $failed_attempts, last_failed_login = NOW(), login_suspended_until = '$suspend_until' WHERE id = " . $user['id']);
                        $err = "Account suspended for 5 seconds due to multiple failed login attempts.";
                        log_activity($conn, ActivityActions::LOGIN_FAILED, ActivityTargets::ADMIN, $user['id'], $user['fullname'], "Account suspended after {$failed_attempts} failed attempts for user: {$user['username']}");
                    } else {
                        // Just increment failed attempts
                        $conn->query("UPDATE admins SET failed_login_attempts = $failed_attempts, last_failed_login = NOW() WHERE id = " . $user['id']);
                        $remaining = 3 - $failed_attempts;
                        $err = "Invalid username or password. $remaining attempts remaining before temporary suspension.";
                        log_activity($conn, ActivityActions::LOGIN_FAILED, ActivityTargets::ADMIN, $user['id'], $user['fullname'], "Failed login attempt {$failed_attempts}/3 for user: {$user['username']}");
                    }
                }
            }
        } else {
            // User not found
            $err = "Invalid username or password.";
            // Log failed attempt (no user ID available)
            log_activity($conn, ActivityActions::LOGIN_FAILED, null, null, null, "Invalid credentials for non-existent username: $username");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($system_name); ?> — Admin Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Styles -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
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
        <?php echo htmlspecialchars($system_name); ?>
      </h4>

      <!-- Dynamic School Name -->
      <div style="font-size:12px;color:#888;">
        <?php echo htmlspecialchars($school_name); ?>
      </div>
    </div>

    <!-- Error Alert -->
    <?php if ($err): ?>
      <div class="alert alert-danger py-2"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" 
               value="<?php echo htmlspecialchars($remember_user); ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <!-- Remember Me -->
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember"
               <?php echo $remember_user ? 'checked' : ''; ?>>
        <label class="form-check-label" for="remember">Remember me</label>
      </div>

      <button class="btn btn-primary w-100" type="submit">Sign In</button>
      
      <!-- Forgot Password Link -->
      <div class="text-center mt-3">
        <a href="forgot_password.php" class="text-decoration-none" style="font-size: 14px;">
          Forgot your password?
        </a>
      </div>
    </form>

    <!-- Footer Credit -->
    <footer class="text-center mt-3" style="font-size:12px;color:#666;">
      Developed by: <strong>BSIT Capstone Group – System Naton</strong> (AY 2025–2026)
    </footer>
  </div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($login_suspended && $suspension_time > 0): ?>
// Handle suspension countdown
let suspensionTime = <?php echo $suspension_time; ?>;
const form = document.querySelector('form');
const submitBtn = form.querySelector('button[type="submit"]');
const errorDiv = document.querySelector('.alert-danger');

function updateCountdown() {
    if (suspensionTime > 0) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-clock me-2"></i>Suspended (${suspensionTime}s)`;
        errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Account temporarily suspended due to multiple failed login attempts. Try again in ' + suspensionTime + ' seconds.';
        suspensionTime--;
        setTimeout(updateCountdown, 1000);
    } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Sign In';
        errorDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>You can now try logging in again.';
        errorDiv.className = 'alert alert-info py-2';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 3000);
    }
}

// Start countdown if suspended
if (suspensionTime > 0) {
    updateCountdown();
}
<?php endif; ?>
</script>
</body>
</html>
