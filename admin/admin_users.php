<?php
// admin/admin_users.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/alert_helper.php");

// ---- PHPMailer (Brevo) ----
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("../PHPMailer/src/Exception.php");
require_once("../PHPMailer/src/PHPMailer.php");
require_once("../PHPMailer/src/SMTP.php");

// ---- Helpers ----
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash_set($type,$msg){ $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function flash_get(){ if(!empty($_SESSION['flash'])){ $x=$_SESSION['flash']; unset($_SESSION['flash']); return $x; } return null; }
function random_password($len=10){
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789@#$%';
  $out=''; for($i=0;$i<$len;$i++) $out .= $chars[random_int(0, strlen($chars)-1)];
  return $out;
}
function send_admin_email($toEmail,$toName,$subject,$html,$alt){
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = 'smtp-relay.brevo.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = '7e2379001@smtp-brevo.com';
  $mail->Password   = 'naYfVTzxWtcgHLv4';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;
  $mail->setFrom('nikkapatriciafrancisco@gmail.com','Uniform Monitoring System');
  $mail->addAddress($toEmail, $toName ?? '');
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $html;
  $mail->AltBody = $alt;
  try { $mail->send(); } catch (Exception $e) { /* optionally log $mail->ErrorInfo */ }
}

// ---- Superadmin protection ----
function is_super_protected($row){
  if (!$row) return false;
  if ((int)$row['id'] === 1) return true;
  if (isset($row['role']) && $row['role']==='superadmin') return true;
  return false;
}

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  // ADD ADMIN (auto-password + email)
  if ($action==='add') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'officer';

    if ($fullname==='' || $username==='' || $email==='') {
      set_alert('Full name, username, and email are required.', 'error');
      header("Location: admin_users.php"); exit();
    }

    // Uniqueness
    $u = $conn->real_escape_string($username);
    $e = $conn->real_escape_string($email);
    $dup = $conn->query("SELECT id FROM admins WHERE username='$u' OR email='$e' LIMIT 1");
    if ($dup && $dup->num_rows) { set_alert('Username or Email already exists.', 'error'); header("Location: admin_users.php"); exit(); }

    // Generate password (MD5 to match your current login)
    $plain = random_password(10);
    $hash  = md5($plain);

    $r = in_array($role,['superadmin','officer','viewer']) ? $role : 'officer';
    $fn = $conn->real_escape_string($fullname);

    $sql = "INSERT INTO admins (username,password,fullname,email,role,status)
            VALUES ('$u','$hash','$fn','$e','$r','active')";
    if ($conn->query($sql)) {
      // Email credentials
      $html = "Dear ".h($fullname).",<br><br>"
            . "An administrator account has been created for you on the <strong>Uniform Monitoring System</strong>.<br><br>"
            . "Username: <strong>".h($username)."</strong><br>"
            . "Email: <strong>".h($email)."</strong><br>"
            . "Temporary Password: <strong>".h($plain)."</strong><br><br>"
            . "For security, please sign in and change your password as soon as possible.<br><br>"
            . "<em>— Uniform Monitoring System</em>";
      $alt  = "Dear {$fullname},\n\nYour UMS admin account has been created.\nUsername: {$username}\nEmail: {$email}\nTemporary Password: {$plain}\n\n— Uniform Monitoring System";
      send_admin_email($email, $fullname, 'UMS Admin Account Created', $html, $alt);
      set_alert('Admin account created and email sent successfully.', 'success');
    } else {
      set_alert('Failed to create admin: '.$conn->error, 'error');
    }
    header("Location: admin_users.php"); exit();
  }

  // RESET PASSWORD (auto + email)
  if ($action==='reset') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id<=0) { set_alert('Invalid request.', 'error'); header("Location: admin_users.php"); exit(); }
    $res = $conn->query("SELECT * FROM admins WHERE id=$id LIMIT 1");
    if (!$res || !$res->num_rows) { set_alert('Admin not found.', 'error'); header("Location: admin_users.php"); exit(); }
    $row = $res->fetch_assoc();
    if (is_super_protected($row)) { set_alert('Cannot reset the Super Admin password.', 'error'); header("Location: admin_users.php"); exit(); }

    $plain = random_password(10);
    $hash  = md5($plain);
    if ($conn->query("UPDATE admins SET password='$hash' WHERE id=$id LIMIT 1")) {
      $html = "Dear ".h($row['fullname']).",<br><br>"
            . "Your administrator password has been reset.<br>"
            . "New Temporary Password: <strong>".h($plain)."</strong><br><br>"
            . "Please change your password immediately after logging in.<br><br>"
            . "<em>— Uniform Monitoring System</em>";
      $alt  = "Dear {$row['fullname']},\n\nYour admin password has been reset.\nNew Temporary Password: {$plain}\n\n— Uniform Monitoring System";
      send_admin_email($row['email'], $row['fullname'], 'UMS Admin Password Reset', $html, $alt);
      set_alert('Password reset and emailed to the admin.', 'success');
    } else {
      set_alert('Failed to reset password: '.$conn->error, 'error');
    }
    header("Location: admin_users.php"); exit();
  }

  // TOGGLE STATUS (active/disabled)
  if ($action==='toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $new = $_POST['new'] ?? '';
    if ($id<=0 || !in_array($new,['active','disabled'])) { set_alert('Invalid request.', 'error'); header("Location: admin_users.php"); exit(); }
    $res = $conn->query("SELECT * FROM admins WHERE id=$id LIMIT 1");
    if (!$res || !$res->num_rows) { set_alert('Admin not found.', 'error'); header("Location: admin_users.php"); exit(); }
    $row = $res->fetch_assoc();
    if (is_super_protected($row)) { set_alert('Cannot disable the Super Admin.', 'error'); header("Location: admin_users.php"); exit(); }

    // Get admin name for better alert message
    $admin_name = $row['username'];
    if (!empty($row['fullname'])) {
      $admin_name = $row['fullname'] . " ({$row['username']})";
    }

    if ($conn->query("UPDATE admins SET status='{$conn->real_escape_string($new)}' WHERE id=$id LIMIT 1")) {
      // Enhanced alert messages
      if ($new === 'disabled') {
        set_success_alert('disabled', 'admin user', $admin_name);
      } else {
        set_success_alert('enabled', 'admin user', $admin_name);
      }
    } else {
      set_alert('Failed to update status: '.$conn->error, 'error');
    }
    header("Location: admin_users.php"); exit();
  }

  // EDIT (fullname, username, email, role)
  if ($action==='edit') {
    $id = (int)($_POST['id'] ?? 0);
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'officer';

    if ($id<=0 || $fullname==='' || $username==='' || $email==='') {
      set_alert('All fields are required.', 'error'); header("Location: admin_users.php"); exit();
    }
    $res = $conn->query("SELECT * FROM admins WHERE id=$id LIMIT 1");
    if (!$res || !$res->num_rows) { set_alert('Admin not found.', 'error'); header("Location: admin_users.php"); exit(); }
    $row = $res->fetch_assoc();
    if (is_super_protected($row)) { $role = 'superadmin'; } // lock superadmin role

    $u = $conn->real_escape_string($username);
    $e = $conn->real_escape_string($email);
    $f = $conn->real_escape_string($fullname);
    $r = in_array($role,['superadmin','officer','viewer']) ? $role : 'officer';

    // unique checks excluding current id
    $dup = $conn->query("SELECT id FROM admins WHERE (username='$u' OR email='$e') AND id<>$id LIMIT 1");
    if ($dup && $dup->num_rows) { set_alert('Username or Email already in use.', 'error'); header("Location: admin_users.php"); exit(); }

    if ($conn->query("UPDATE admins SET fullname='$f', username='$u', email='$e', role='$r' WHERE id=$id LIMIT 1")) {
      set_alert('Admin updated.', 'success');
    } else {
      set_alert('Failed to update: '.$conn->error, 'error');
    }
    header("Location: admin_users.php"); exit();
  }
}

// ---- List / Search ----
$search = trim($_GET['q'] ?? '');
$where = "1";
if ($search!=='') {
  $s = $conn->real_escape_string($search);
  $where .= " AND (fullname LIKE '%$s%' OR username LIKE '%$s%' OR email LIKE '%$s%' OR role LIKE '%$s%')";
}
$list = $conn->query("SELECT * FROM admins WHERE $where ORDER BY id ASC");

include('includes/header.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Admin Users</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="fa fa-user-plus me-1"></i> Add Admin
    </button>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-sm-6 col-md-4 col-lg-3">
      <input type="text" name="q" value="<?php echo h($search); ?>" class="form-control" placeholder="Search name, username, email, role">
    </div>
    <div class="col-auto">
      <button class="btn btn-outline-secondary"><i class="fa fa-search"></i></button>
      <a class="btn btn-outline-secondary" href="admin_users.php"><i class="fa fa-times"></i></a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px;">ID</th>
          <th>Full Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th style="width:240px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list && $list->num_rows): while($r=$list->fetch_assoc()): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo h($r['fullname']); ?></td>
            <td><?php echo h($r['username']); ?></td>
            <td><?php echo h($r['email']); ?></td>
            <td>
              <?php if (is_super_protected($r)): ?>
                <span class="badge bg-dark">superadmin</span>
              <?php else: ?>
                <span class="badge bg-secondary"><?php echo h($r['role'] ?: 'officer'); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($r['status'] ?? 'active')==='active'): ?>
                <span class="badge bg-success">active</span>
              <?php else: ?>
                <span class="badge bg-danger">disabled</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-group">
                <!-- Edit -->
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#editModal"
                        data-id="<?php echo (int)$r['id']; ?>"
                        data-fullname="<?php echo h($r['fullname']); ?>"
                        data-username="<?php echo h($r['username']); ?>"
                        data-email="<?php echo h($r['email']); ?>"
                        data-role="<?php echo h($r['role'] ?: 'officer'); ?>"
                        <?php echo is_super_protected($r)?'disabled':''; ?>>
                  <i class="fa fa-pencil"></i>
                </button>
                <!-- Reset Password -->
                <form method="post" class="d-inline" onsubmit="return confirm('Reset password for this admin?');">
                  <input type="hidden" name="action" value="reset">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-sm btn-warning" <?php echo is_super_protected($r)?'disabled':''; ?>>
                    <i class="fa fa-key"></i>
                  </button>
                </form>
                <!-- Toggle Status -->
                <form method="post" class="d-inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <input type="hidden" name="new" value="<?php echo (($r['status'] ?? 'active')==='active') ? 'disabled' : 'active'; ?>">
                  <button type="button" class="btn btn-sm <?php echo (($r['status'] ?? 'active')==='active')?'btn-outline-danger':'btn-outline-success'; ?> btn-toggle-status" 
                          data-id="<?php echo (int)$r['id']; ?>" 
                          data-name="<?php echo htmlspecialchars($r['fullname'] ?: $r['username'], ENT_QUOTES); ?>"
                          data-action="<?php echo (($r['status'] ?? 'active')==='active') ? 'disable' : 'enable'; ?>"
                          data-new-status="<?php echo (($r['status'] ?? 'active')==='active') ? 'disabled' : 'active'; ?>"
                          <?php echo is_super_protected($r)?'disabled':''; ?>>
                    <?php echo (($r['status'] ?? 'active')==='active')?'<i class="fa fa-ban"></i>':'<i class="fa fa-rotate-left"></i>'; ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="7" class="text-center text-muted">No admins found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title">Add Admin (Auto-Generated Password)</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="add">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input name="fullname" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input name="username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select">
            <option value="officer">Officer</option>
            <option value="viewer">Viewer (read-only)</option>
            <option value="superadmin">Super Admin</option>
          </select>
          <div class="form-text">Only an existing Super Admin should create other Super Admins.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Create & Email Login</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post">
      <div class="modal-header">
        <h5 class="modal-title">Edit Admin</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input name="fullname" id="edit_fullname" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input name="username" id="edit_username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input name="email" type="email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="role" id="edit_role" class="form-select">
            <option value="officer">Officer</option>
            <option value="viewer">Viewer</option>
            <option value="superadmin">Super Admin</option>
          </select>
          <div class="form-text">Super Admin cannot be downgraded.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden Archive Form -->
<form id="archiveForm" method="post" style="display: none;">
  <input type="hidden" name="action" value="archive">
  <input type="hidden" name="id" id="archiveId">
</form>

<!-- Hidden Toggle Status Form -->
<form id="toggleStatusForm" method="post" style="display: none;">
  <input type="hidden" name="action" value="toggle">
  <input type="hidden" name="id" id="toggleId">
  <input type="hidden" name="new" id="toggleNewStatus">
</form>

<script>
document.getElementById('editModal')?.addEventListener('show.bs.modal', function (evt) {
  const btn = evt.relatedTarget;
  if (!btn) return;
  this.querySelector('#edit_id').value        = btn.getAttribute('data-id') || '';
  this.querySelector('#edit_fullname').value  = btn.getAttribute('data-fullname') || '';
  this.querySelector('#edit_username').value  = btn.getAttribute('data-username') || '';
  this.querySelector('#edit_email').value     = btn.getAttribute('data-email') || '';
  this.querySelector('#edit_role').value      = btn.getAttribute('data-role') || 'officer';
});

// Archive functionality
document.querySelectorAll('.archiveBtn').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.getAttribute('data-id');
    const name = this.getAttribute('data-name');
    
    Swal.fire({
      title: 'Archive Admin?',
      text: `Are you sure you want to archive "${name}"? They can be restored from the recycle bin.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#f0ad4e',
      confirmButtonText: 'Yes, Archive',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById('archiveId').value = id;
        document.getElementById('archiveForm').submit();
      }
    });
  });
});

// Toggle Status functionality
document.querySelectorAll('.btn-toggle-status').forEach(btn => {
  btn.addEventListener('click', function() {
    const id = this.getAttribute('data-id');
    const name = this.getAttribute('data-name');
    const action = this.getAttribute('data-action'); // 'disable' or 'enable'
    const newStatus = this.getAttribute('data-new-status'); // 'disabled' or 'active'
    
    const isDisabling = action === 'disable';
    const title = isDisabling ? 'Disable Admin User?' : 'Enable Admin User?';
    const text = isDisabling 
      ? `Are you sure you want to disable "${name}"? They will not be able to log in until re-enabled.`
      : `Are you sure you want to enable "${name}"? They will be able to log in again.`;
    const icon = isDisabling ? 'warning' : 'question';
    const confirmButtonColor = isDisabling ? '#dc3545' : '#28a745';
    const confirmButtonText = isDisabling ? 'Yes, Disable' : 'Yes, Enable';
    
    Swal.fire({
      title: title,
      text: text,
      icon: icon,
      showCancelButton: true,
      confirmButtonColor: confirmButtonColor,
      confirmButtonText: confirmButtonText,
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById('toggleId').value = id;
        document.getElementById('toggleNewStatus').value = newStatus;
        document.getElementById('toggleStatusForm').submit();
      }
    });
  });
});
</script>

<?php render_alert_script(); ?>

<?php include('includes/footer.php'); ?>
