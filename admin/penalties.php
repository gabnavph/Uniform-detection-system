<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/activity_logger.php");
require_once("includes/alert_helper.php");

// PHPMailer (for paid receipt)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("../PHPMailer/src/Exception.php");
require_once("../PHPMailer/src/PHPMailer.php");
require_once("../PHPMailer/src/SMTP.php");

// Match Students page config
$COURSES = ["BSIT","BSHM"];
$YEARS   = ["1st Year","2nd Year","3rd Year","4th Year"];
$SECTIONS= ["A","B","C","D"];

// Flash helpers (deprecated - use alert system instead)
function set_flash($type,$msg){ set_alert($msg, $type); }

function send_payment_email($toEmail, $toName, $studentCode, $violation, $orNumber, $paidAt) {
  $mail = new PHPMailer(true);
  // Brevo SMTP (same as earlier)
  $mail->isSMTP();
  $mail->Host       = 'smtp-relay.brevo.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = '7e2379001@smtp-brevo.com';
  $mail->Password   = 'naYfVTzxWtcgHLv4';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  $mail->setFrom('nikkapatriciafrancisco@gmail.com', 'Uniform Monitoring System');
  $mail->addAddress($toEmail, $toName ?? '');

  $mail->isHTML(true);
  $mail->Subject = 'Penalty Payment Confirmation';

  $paidDate = date('F j, Y g:i A', strtotime($paidAt));
  $orLine = $orNumber ? ("Official Receipt #: <strong>".htmlspecialchars($orNumber)."</strong><br>") : "";

  $body  = "Dear ".htmlspecialchars($toName).",<br><br>";
  $body .= "This email confirms your payment for the recorded uniform penalty.<br>";
  $body .= "Student Code: <strong>".htmlspecialchars($studentCode)."</strong><br>";
  $body .= "Violation: <strong>".htmlspecialchars($violation)."</strong><br>";
  $body .= $orLine;
  $body .= "Payment Date: <strong>".htmlspecialchars($paidDate)."</strong><br><br>";
  $body .= "If you have any concerns, you may contact the Office of Student Affairs.<br><br>";
  $body .= "<em>— Uniform Monitoring System<br>University of Antique</em>";

  $alt  = "Dear {$toName},\n\n";
  $alt .= "This email confirms your payment for the recorded uniform penalty.\n";
  $alt .= "Student Code: {$studentCode}\n";
  $alt .= "Violation: {$violation}\n";
  if ($orNumber) $alt .= "Official Receipt #: {$orNumber}\n";
  $alt .= "Payment Date: {$paidDate}\n\n";
  $alt .= "— Uniform Monitoring System\nUniversity of Antique";

  $mail->Body = $body;
  $mail->AltBody = $alt;
  try { $mail->send(); } catch (Exception $e) { /* optionally log $mail->ErrorInfo */ }
}

// ---- Actions: update status with OR # / remarks / email ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'update_status') {
  $id = intval($_POST['id'] ?? 0);
  $new_status = $_POST['new_status'] ?? '';
  $remarks = trim($_POST['remarks'] ?? '');
  $or_number = trim($_POST['or_number'] ?? '');
  $send_email = isset($_POST['send_email']) ? (bool)$_POST['send_email'] : false;

  if ($id <= 0 || !in_array($new_status, ['paid','unpaid'])) {
    set_flash('error','Invalid request.');
    header("Location: penalties.php"); exit();
  }

  if ($new_status === 'paid') {
    // set paid_at to NOW, store or_number and remarks
    $sql = "UPDATE penalties SET 
              status='paid',
              or_number=".($or_number!=='' ? "'".$conn->real_escape_string($or_number)."'" : "NULL").",
              remarks=".($remarks!=='' ? "'".$conn->real_escape_string($remarks)."'" : "remarks").",
              paid_at=NOW()
            WHERE id=$id LIMIT 1";
    $ok = $conn->query($sql);

    if ($ok) {
      // Load joined data for email
      $q = $conn->query("SELECT p.*, s.email, s.fullname, s.student_code 
                         FROM penalties p 
                         JOIN students s ON s.id=p.student_id 
                         WHERE p.id=$id LIMIT 1");
      if ($q && $q->num_rows) {
        $row = $q->fetch_assoc();
        
        // Log payment activity
        $details = "Penalty marked as paid: {$row['violation']} (₱{$row['charge']})";
        if ($or_number) $details .= " - OR# $or_number";
        if ($remarks) $details .= " - Remarks: $remarks";
        log_activity($conn, ActivityActions::PENALTY_PAID, ActivityTargets::PENALTY, $id, $row['fullname'], $details);
        
        if ($send_email && !empty($row['email'])) {
          $paidAt = $row['paid_at'] ?: date('Y-m-d H:i:s'); // just in case
          send_payment_email($row['email'], $row['fullname'], $row['student_code'], $row['violation'], $or_number, $paidAt);
        }
      }
      set_flash('success','Penalty marked as PAID.'.($send_email?' Email sent.':''));
    } else {
      set_flash('error','Failed to update: '.$conn->error);
    }
  } else {
    // set unpaid: clear or_number and paid_at
    $sql = "UPDATE penalties SET 
              status='unpaid',
              or_number=NULL,
              paid_at=NULL
            WHERE id=$id LIMIT 1";
    $ok = $conn->query($sql);
    if ($ok) {
      // Get student info for logging
      $q = $conn->query("SELECT s.fullname, p.violation, p.charge FROM penalties p JOIN students s ON s.id=p.student_id WHERE p.id=$id LIMIT 1");
      if ($q && $q->num_rows) {
        $row = $q->fetch_assoc();
        log_activity($conn, ActivityActions::PENALTY_UNPAID, ActivityTargets::PENALTY, $id, $row['fullname'], "Penalty marked as unpaid: {$row['violation']} (₱{$row['charge']})");
      }
      set_flash('success','Penalty marked as UNPAID.');
    } else {
      set_flash('error','Failed to update: '.$conn->error);
    }
  }
  header("Location: penalties.php"); exit();
}

// ---- Filters / Search (with date range) ----
$search  = trim($_GET['q'] ?? '');
$fcourse = $_GET['course'] ?? '';
$fsection= $_GET['section'] ?? '';
$fyear   = $_GET['year'] ?? '';
$fstatus = $_GET['payment_status'] ?? ''; // paid/unpaid
$from    = trim($_GET['from'] ?? ''); // YYYY-MM-DD
$to      = trim($_GET['to'] ?? '');   // YYYY-MM-DD

$where = "1";
if ($search!=='') {
  $s = $conn->real_escape_string($search);
  $where .= " AND (s.fullname LIKE '%$s%' OR s.student_code LIKE '%$s%' OR p.violation LIKE '%$s%' OR s.email LIKE '%$s%')";
}
if ($fcourse!=='' && in_array($fcourse,$COURSES)) {
  $co = $conn->real_escape_string($fcourse);
  $where .= " AND s.course='$co'";
}
if ($fsection!=='' && in_array($fsection,$SECTIONS)) {
  $sec = $conn->real_escape_string($fsection);
  $where .= " AND s.section='$sec'";
}
if ($fyear!=='' && in_array($fyear,$YEARS)) {
  $yl = $conn->real_escape_string($fyear);
  $where .= " AND s.year_level='$yl'";
}
if ($fstatus!=='' && in_array($fstatus,['paid','unpaid'])) {
  $st = $conn->real_escape_string($fstatus);
  $where .= " AND p.status='$st'";
}
if ($from!=='') {
  $f = $conn->real_escape_string($from);
  $where .= " AND DATE(p.date_issued) >= '$f'";
}
if ($to!=='') {
  $t = $conn->real_escape_string($to);
  $where .= " AND DATE(p.date_issued) <= '$t'";
}

// ---- Export CSV (filtered) ----
if (($_GET['export'] ?? '') === 'csv') {
  $filename = "penalties_export_".date("Ymd_His").".csv";
  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out = fopen("php://output", "w");
  fputcsv($out, ["Student Code","Full Name","Course","Year","Section","Violation","Status","OR Number","Paid At","Remarks","Date Issued"]);
  $sql = "SELECT p.*, s.student_code, s.fullname, s.course, s.year_level, s.section
          FROM penalties p
          JOIN students s ON s.id = p.student_id
          WHERE $where
          ORDER BY p.date_issued DESC, p.id DESC";
  if ($res = $conn->query($sql)) {
    while($r=$res->fetch_assoc()){
      fputcsv($out, [
        $r['student_code'],
        $r['fullname'],
        $r['course'],
        $r['year_level'],
        $r['section'],
        $r['violation'],
        strtoupper($r['payment_status']),
        $r['or_number'],
        $r['paid_at'],
        $r['remarks'],
        $r['date_issued']
      ]);
    }
  }
  fclose($out);
  exit();
}

// ---- Pagination + Fetch List ----
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 10; $offset = ($page-1)*$limit;

$sqlCount = "SELECT COUNT(*) 
             FROM penalties p
             JOIN students s ON s.id = p.student_id
             WHERE $where";
$total=0; if ($rc=$conn->query($sqlCount)) { $row=$rc->fetch_row(); $total=intval($row[0]); }
$pages=max(1, ceil($total/$limit));

$sqlList = "SELECT p.*, s.student_code, s.fullname, s.course, s.year_level, s.section, s.email
            FROM penalties p
            JOIN students s ON s.id = p.student_id
            WHERE $where
            ORDER BY p.date_issued DESC, p.id DESC
            LIMIT $limit OFFSET $offset";
$list = $conn->query($sqlList);

include('includes/header.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Penalties</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&payment_status=<?php echo urlencode($fstatus); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv">
        <i class="fa fa-download me-1"></i> Export CSV
      </a>
    </div>
  </div>

  <!-- Filters -->
  <div class="card shadow-sm mb-3">
    <div class="card-header">
      <strong><i class="fa fa-filter me-1"></i> Filters</strong>
    </div>
    <div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-md-3">
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search name, code, violation, email...">
        </div>
        <div class="col-md-2">
          <select name="course" class="form-select">
            <option value="">All Courses</option>
            <?php foreach($COURSES as $c): ?>
              <option value="<?php echo $c; ?>" <?php echo $fcourse===$c?'selected':''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="section" class="form-select">
            <option value="">All Sections</option>
            <?php foreach($SECTIONS as $sec): ?>
              <option value="<?php echo $sec; ?>" <?php echo $fsection===$sec?'selected':''; ?>><?php echo $sec; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="year" class="form-select">
            <option value="">All Years</option>
            <?php foreach($YEARS as $y): ?>
              <option value="<?php echo $y; ?>" <?php echo $fyear===$y?'selected':''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="payment_status" class="form-select">
            <option value="">All Status</option>
            <option value="unpaid" <?php echo $fstatus==='unpaid'?'selected':''; ?>>Unpaid</option>
            <option value="paid"   <?php echo $fstatus==='paid'?'selected':''; ?>>Paid</option>
          </select>
        </div>
        <div class="col-md-1">
          <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" class="form-control" title="From Date">
        </div>
        <div class="col-md-1">
          <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" class="form-control" title="To Date">
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100" type="submit"><i class="fa fa-search"></i></button>
        </div>
        <div class="col-md-12">
          <a class="btn btn-outline-secondary btn-sm" href="penalties.php"><i class="fa fa-times me-1"></i> Clear Filters</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Student</th>
          <th>Course/Year/Section</th>
          <th>Violation</th>
          <th>Status</th>
          <th>OR #</th>
          <th>Paid At</th>
          <th>Remarks</th>
          <th style="width:160px;">Date Issued</th>
          <th style="width:190px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list && $list->num_rows): while($r=$list->fetch_assoc()): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($r['fullname']); ?></div>
              <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['student_code']); ?></div>
            </td>
            <td><?php echo htmlspecialchars($r['course']." / ".$r['year_level']." / ".$r['section']); ?></td>
            <td><?php echo htmlspecialchars($r['violation']); ?></td>
            <td>
              <?php if ($r['payment_status']==='paid'): ?>
                <span class="badge bg-success">PAID</span>
              <?php else: ?>
                <span class="badge bg-danger">UNPAID</span>
              <?php endif; ?>
            </td>
            <td><?php echo $r['or_number'] ? htmlspecialchars($r['or_number']) : '<span class="text-muted">—</span>'; ?></td>
            <td>
              <?php echo $r['paid_at'] ? '<span class="text-muted" style="font-size:12px;">'.htmlspecialchars($r['paid_at']).'</span>' : '<span class="text-muted">—</span>'; ?>
            </td>
            <td style="max-width:240px;">
              <div class="text-truncate" title="<?php echo htmlspecialchars($r['remarks']); ?>">
                <?php echo $r['remarks']!==null && $r['remarks']!=='' ? htmlspecialchars($r['remarks']) : '<span class="text-muted">—</span>'; ?>
              </div>
            </td>
            <td><span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['date_issued']); ?></span></td>
            <td>
              <?php if ($r['payment_status']==='unpaid'): ?>
                <button class="btn btn-sm btn-success btn-mark-paid"
                  data-id="<?php echo $r['id']; ?>"
                  data-name="<?php echo htmlspecialchars($r['fullname'],ENT_QUOTES); ?>"
                  data-email="<?php echo htmlspecialchars($r['email'],ENT_QUOTES); ?>"
                  data-violation="<?php echo htmlspecialchars($r['violation'],ENT_QUOTES); ?>"
                  data-studcode="<?php echo htmlspecialchars($r['student_code'],ENT_QUOTES); ?>">
                  <i class="fa fa-check me-1"></i> Mark as Paid
                </button>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary btn-mark-unpaid" data-id="<?php echo $r['id']; ?>">
                  <i class="fa fa-rotate-left me-1"></i> Mark as Unpaid
                </button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="9" class="text-center text-muted">No penalties found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages>1): ?>
    <nav><ul class="pagination">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
          <a class="page-link" href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&payment_status=<?php echo urlencode($fstatus); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&page=<?php echo $p; ?>">
            <?php echo $p; ?>
          </a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>

<!-- Hidden form for status updates -->
<form id="statusForm" method="post" class="d-none">
  <input type="hidden" name="action" value="update_status">
  <input type="hidden" name="id" id="status_id">
  <input type="hidden" name="new_status" id="status_val">
  <input type="hidden" name="remarks" id="status_remarks">
  <input type="hidden" name="or_number" id="status_or">
  <input type="hidden" name="send_email" id="status_send_email" value="1">
</form>



<script>
// Render alerts
<?php render_alert_script(); ?>

// Mark as Paid — OR # optional, remarks optional, email default ON
document.querySelectorAll('.btn-mark-paid').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id = btn.getAttribute('data-id');
    const studentName = btn.getAttribute('data-name') || 'Student';

    const { value: formValues, isConfirmed } = await Swal.fire({
      title: `Mark as PAID`,
      html:
        `<div class="mb-2 text-start"><small class="text-muted">OR Number (optional)</small></div>`+
        `<input id="swal-or" class="swal2-input" placeholder="Official Receipt # (optional)">`+
        `<div class="mb-2 text-start"><small class="text-muted">Remarks (optional)</small></div>`+
        `<textarea id="swal-remarks" class="swal2-textarea" placeholder="e.g., Paid at cashier, OR# 1523"></textarea>`+
        `<div class="form-check" style="display:flex;align-items:center;justify-content:center;">`+
        `  <input class="form-check-input" type="checkbox" id="swal-email" checked>`+
        `  <label class="form-check-label ms-2" for="swal-email">Send payment receipt email to ${studentName}</label>`+
        `</div>`,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#198754',
      cancelButtonColor: '#6c757d',
      preConfirm: () => {
        return {
          or: (document.getElementById('swal-or')?.value || '').trim(),
          remarks: (document.getElementById('swal-remarks')?.value || '').trim(),
          sendEmail: document.getElementById('swal-email')?.checked ? '1' : ''
        };
      }
    });

    if (isConfirmed) {
      document.getElementById('status_id').value = id;
      document.getElementById('status_val').value = 'paid';
      document.getElementById('status_or').value = formValues.or || '';
      document.getElementById('status_remarks').value = formValues.remarks || '';
      document.getElementById('status_send_email').value = formValues.sendEmail ? '1' : '';
      document.getElementById('statusForm').submit();
    }
  });
});

// Mark as Unpaid — simple confirm
document.querySelectorAll('.btn-mark-unpaid').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const id = btn.getAttribute('data-id');
    Swal.fire({
      title: 'Mark as UNPAID?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Confirm',
      confirmButtonColor: '#7B1113',
      cancelButtonColor: '#6c757d'
    }).then((res)=>{
      if (res.isConfirmed) {
        document.getElementById('status_id').value = id;
        document.getElementById('status_val').value = 'unpaid';
        document.getElementById('status_remarks').value = '';
        document.getElementById('status_or').value = '';
        document.getElementById('status_send_email').value = '';
        document.getElementById('statusForm').submit();
      }
    });
  });
});
</script>

<?php include('includes/footer.php'); ?>
