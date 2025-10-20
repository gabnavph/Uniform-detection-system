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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Animated background elements */
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
      animation: drift 20s ease-in-out infinite;
    }
    
    @keyframes drift {
      0%, 100% { transform: translateX(0px) translateY(0px); }
      50% { transform: translateX(10px) translateY(-5px); }
    }
    
    .login-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      padding: 20px;
    }
    
    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      padding: 40px 35px;
      box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.1),
        0 4px 16px rgba(0, 0, 0, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
      transform: translateY(0);
      transition: all 0.3s ease;
    }
    
    .login-card:hover {
      transform: translateY(-2px);
      box-shadow: 
        0 12px 40px rgba(0, 0, 0, 0.15),
        0 6px 20px rgba(0, 0, 0, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    }
    
    .logo-section {
      text-align: center;
      margin-bottom: 35px;
    }
    
    .logo-image {
      width: 80px;
      height: 80px;
      border-radius: 20px;
      border: 3px solid #D4AF37;
      object-fit: cover;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      margin-bottom: 15px;
      transition: transform 0.3s ease;
    }
    
    .logo-image:hover {
      transform: scale(1.05);
    }
    
    .system-name {
      font-size: 24px;
      font-weight: 700;
      color: #2d3748;
      margin-bottom: 5px;
      letter-spacing: -0.025em;
    }
    
    .school-name {
      font-size: 14px;
      color: #718096;
      font-weight: 500;
    }
    
    .form-group {
      margin-bottom: 24px;
      position: relative;
    }
    
    .form-label {
      font-weight: 600;
      color: #4a5568;
      font-size: 14px;
      margin-bottom: 8px;
      display: block;
    }
    
    .form-control {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px 16px;
      font-size: 16px;
      transition: all 0.2s ease;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(5px);
    }
    
    .form-control:focus {
      border-color: #7B1113;
      box-shadow: 0 0 0 3px rgba(123, 17, 19, 0.1);
      background: rgba(255, 255, 255, 0.95);
      outline: none;
    }
    
    .input-icon {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: #a0aec0;
      font-size: 18px;
      transition: color 0.2s ease;
    }
    
    .form-control:focus + .input-icon {
      color: #7B1113;
    }
    
    .form-control.with-icon {
      padding-left: 48px;
    }
    
    .password-toggle {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #a0aec0;
      cursor: pointer;
      font-size: 18px;
      transition: color 0.2s ease;
    }
    
    .password-toggle:hover {
      color: #7B1113;
    }
    
    .remember-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }
    
    .form-check {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-check-input {
      width: 18px;
      height: 18px;
      border: 2px solid #e2e8f0;
      border-radius: 4px;
      background: rgba(255, 255, 255, 0.8);
      cursor: pointer;
    }
    
    .form-check-input:checked {
      background-color: #7B1113;
      border-color: #7B1113;
    }
    
    .form-check-label {
      font-size: 14px;
      color: #4a5568;
      font-weight: 500;
      cursor: pointer;
    }
    
    .forgot-link {
      color: #7B1113;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: color 0.2s ease;
    }
    
    .forgot-link:hover {
      color: #5a0c0e;
      text-decoration: underline;
    }
    
    .login-btn {
      width: 100%;
      background: linear-gradient(135deg, #7B1113 0%, #9d1417 100%);
      border: none;
      border-radius: 12px;
      padding: 16px;
      font-size: 16px;
      font-weight: 600;
      color: white;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .login-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }
    
    .login-btn:hover::before {
      left: 100%;
    }
    
    .login-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(123, 17, 19, 0.3);
    }
    
    .login-btn:active {
      transform: translateY(0);
    }
    
    .login-btn:disabled {
      background: #cbd5e0;
      cursor: not-allowed;
      transform: none;
    }
    
    .alert {
      border: none;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      font-size: 14px;
      font-weight: 500;
      animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .alert-danger {
      background: rgba(245, 101, 101, 0.1);
      color: #c53030;
      border: 1px solid rgba(245, 101, 101, 0.2);
    }
    
    .alert-info {
      background: rgba(56, 178, 172, 0.1);
      color: #2c7a7b;
      border: 1px solid rgba(56, 178, 172, 0.2);
    }
    
    .footer-credit {
      text-align: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      font-size: 13px;
      color: #718096;
      font-weight: 500;
    }
    
    .footer-credit strong {
      color: #4a5568;
      font-weight: 600;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 768px) {
      .login-container {
        padding: 15px;
      }
      
      .login-card {
        padding: 30px 25px;
      }
      
      .system-name {
        font-size: 22px;
      }
      
      .logo-image {
        width: 70px;
        height: 70px;
      }
    }
    
    /* Loading state */
    .loading {
      position: relative;
    }
    
    .loading::after {
      content: '';
      position: absolute;
      left: 50%;
      top: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid transparent;
      border-top: 2px solid #fff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>

<body>

  <div class="login-container">
    <div class="login-card">
      <!-- Logo and Header Section -->
      <div class="logo-section">
        <?php if ($school_logo): ?>
          <img src="../<?php echo htmlspecialchars($school_logo); ?>" 
               alt="School Logo" 
               class="logo-image"
               onerror="this.style.display='none'">
        <?php else: ?>
          <!-- Default icon if no logo -->
          <div class="logo-image d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg, #7B1113, #D4AF37);">
            <i class="fas fa-graduation-cap text-white" style="font-size: 32px;"></i>
          </div>
        <?php endif; ?>

        <h1 class="system-name">
          <?php echo htmlspecialchars($system_name); ?>
        </h1>
        <p class="school-name">
          <?php echo htmlspecialchars($school_name); ?>
        </p>
      </div>

      <!-- Error Alert -->
      <?php if ($err): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-triangle me-2"></i>
          <?php echo htmlspecialchars($err); ?>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="post" autocomplete="off" id="loginForm">
        <!-- Username Field -->
        <div class="form-group">
          <label class="form-label" for="username">
            <i class="fas fa-user me-2"></i>Username
          </label>
          <div style="position: relative;">
            <input type="text" 
                   name="username" 
                   id="username"
                   class="form-control with-icon" 
                   value="<?php echo htmlspecialchars($remember_user); ?>" 
                   placeholder="Enter your username"
                   required>
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>

        <!-- Password Field -->
        <div class="form-group">
          <label class="form-label" for="password">
            <i class="fas fa-lock me-2"></i>Password
          </label>
          <div style="position: relative;">
            <input type="password" 
                   name="password" 
                   id="password"
                   class="form-control with-icon" 
                   placeholder="Enter your password"
                   required>
            <i class="fas fa-lock input-icon"></i>
            <button type="button" class="password-toggle" onclick="togglePassword()">
              <i class="fas fa-eye" id="toggleIcon"></i>
            </button>
          </div>
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="remember-section">
          <div class="form-check">
            <input class="form-check-input" 
                   type="checkbox" 
                   value="1" 
                   id="remember" 
                   name="remember"
                   <?php echo $remember_user ? 'checked' : ''; ?>>
            <label class="form-check-label" for="remember">
              Remember me
            </label>
          </div>
          <a href="forgot_password.php" class="forgot-link">
            <i class="fas fa-key me-1"></i>Forgot Password?
          </a>
        </div>

        <!-- Login Button -->
        <button class="login-btn" type="submit" id="loginBtn">
          <i class="fas fa-sign-in-alt me-2"></i>
          <span>Sign In</span>
        </button>
      </form>

      <!-- Footer Credit -->
      <div class="footer-credit">
        <i class="fas fa-code me-1"></i>
        Developed by: <strong>BSIT Capstone Group – System Naton</strong><br>
        <small>Academic Year 2025–2026</small>
      </div>
    </div>
  </div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Password visibility toggle
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}

// Form submission with loading state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('loginBtn');
    const btnText = submitBtn.querySelector('span');
    
    // Add loading state
    submitBtn.classList.add('loading');
    submitBtn.disabled = true;
    btnText.textContent = 'Signing In...';
    
    // Remove loading state after a delay (form will submit)
    setTimeout(() => {
        if (!submitBtn.disabled) {
            submitBtn.classList.remove('loading');
            btnText.textContent = 'Sign In';
        }
    }, 2000);
});

// Input focus animations
document.querySelectorAll('.form-control').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.classList.remove('focused');
    });
});

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 300);
    }, 5000);
});

<?php if ($login_suspended && $suspension_time > 0): ?>
// Handle suspension countdown
let suspensionTime = <?php echo $suspension_time; ?>;
const form = document.getElementById('loginForm');
const submitBtn = document.getElementById('loginBtn');
const btnText = submitBtn.querySelector('span');
const errorDiv = document.querySelector('.alert-danger');

function updateCountdown() {
    if (suspensionTime > 0) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-clock me-2"></i><span>Suspended (${suspensionTime}s)</span>`;
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Account temporarily suspended due to multiple failed login attempts. Try again in ' + suspensionTime + ' seconds.';
        }
        suspensionTime--;
        setTimeout(updateCountdown, 1000);
    } else {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i><span>Sign In</span>';
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="fas fa-info-circle me-2"></i>You can now try logging in again.';
            errorDiv.className = 'alert alert-info';
            setTimeout(() => {
                errorDiv.style.opacity = '0';
                errorDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    errorDiv.style.display = 'none';
                }, 300);
            }, 3000);
        }
    }
}

// Start countdown if suspended
if (suspensionTime > 0) {
    updateCountdown();
}
<?php endif; ?>

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Enter key on username field should focus password
    if (e.key === 'Enter' && document.activeElement.id === 'username') {
        e.preventDefault();
        document.getElementById('password').focus();
    }
    
    // Escape key clears form
    if (e.key === 'Escape') {
        document.getElementById('loginForm').reset();
        document.getElementById('username').focus();
    }
});

// Auto-focus username field on page load
window.addEventListener('load', function() {
    const usernameField = document.getElementById('username');
    if (usernameField && !usernameField.value) {
        usernameField.focus();
    } else if (usernameField && usernameField.value) {
        document.getElementById('password').focus();
    }
});

// Smooth animations for card hover
const loginCard = document.querySelector('.login-card');
loginCard.addEventListener('mouseenter', function() {
    this.style.transform = 'translateY(-2px) scale(1.01)';
});

loginCard.addEventListener('mouseleave', function() {
    this.style.transform = 'translateY(0) scale(1)';
});
</script>
</body>
</html>
