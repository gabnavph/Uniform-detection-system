<?php
// admin/payments.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/settings_helper.php"); // <-- for system settings

// ---- Settings (branding + defaults + smtp) ----
$system_name   = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name   = get_setting($conn, 'school_name', 'Your School Name');
$default_fee   = (float) get_setting($conn, 'default_penalty', '5'); // used as fallback when penalty.charge is NULL

$smtp_host     = get_setting($conn, 'smtp_host', 'smtp-relay.brevo.com');
$smtp_user     = get_setting($conn, 'smtp_user', '');
$smtp_pass     = get_setting($conn, 'smtp_pass', '');
$smtp_sender   = get_setting($conn, 'smtp_sender_name', 'Uniform Monitoring System');

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("../PHPMailer/src/Exception.php");
require_once("../PHPMailer/src/PHPMailer.php");
require_once("../PHPMailer/src/SMTP.php");

// ---- Utils ----
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n){ return "₱".number_format((float)$n, 2); }
function flash_set($type,$msg){ $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function flash_get(){ if(!empty($_SESSION['flash'])){ $x=$_SESSION['flash']; unset($_SESSION['flash']); return $x; } return null; }

function send_receipt_email($toEmail, $toName, $amount, $remainingAfter, $distributionParts, $school_name, $smtp) {
  if (!$toEmail) return;
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = $smtp['host'] ?: 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp['user'] ?: '';
    $mail->Password   = $smtp['pass'] ?: '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $fromAddress = $smtp['user'] ?: 'no-reply@localhost';
    $fromName    = $smtp['sender'] ?: 'Uniform Monitoring System';
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($toEmail, $toName ?? '');

    $distText = (count($distributionParts) ? implode(', ', $distributionParts) : '—');

    $mail->isHTML(true);
    $mail->Subject = 'Uniform Penalty Payment Receipt';
    $mail->Body = "
      Dear ".h($toName).",<br><br>
      This is to confirm we received your uniform penalty payment of <strong>".peso($amount)."</strong>.<br>
      <strong>Applied To:</strong> {$distText}<br>
      <strong>Remaining Balance:</strong> ".peso($remainingAfter)."<br><br>
      Thank you for your prompt compliance.<br><br>
      <em>— ".h($fromName)."<br>".h($school_name)."</em>
    ";
    $mail->AltBody = "Dear {$toName},\n\nWe received your payment of ".peso($amount).".\nApplied to: {$distText}\nRemaining balance: ".peso($remainingAfter)."\n\n— {$fromName}\n{$school_name}";
    $mail->send();
  } catch (Exception $e) {
    // optionally log: $mail->ErrorInfo
  }
}

$flash = null;

/* ---------- Actions ---------- */

// Update per-penalty charges (inline edit)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='update_charges') {
  $student_id = (int)($_POST['student_id'] ?? 0);
  if ($student_id > 0 && !empty($_POST['charge']) && is_array($_POST['charge'])) {
    foreach ($_POST['charge'] as $pid => $val) {
      $pid = (int)$pid; $val = (float)$val;
      if ($pid <= 0) continue;
      if ($val < 0) $val = 0;

      // Update charge
      $conn->query("UPDATE penalties SET charge={$val} WHERE id={$pid} AND student_id={$student_id}");

      // Fix status if overpaid/underpaid after charge change
      $conn->query("
        UPDATE penalties 
        SET payment_status = CASE 
          WHEN IFNULL(paid_amount,0) >= IFNULL(charge, {$default_fee}) THEN 'paid'
          WHEN IFNULL(paid_amount,0) > 0 AND IFNULL(paid_amount,0) < IFNULL(charge, {$default_fee}) THEN 'partial'
          ELSE 'unpaid'
        END
        WHERE id={$pid} AND student_id={$student_id}
      ");
    }
    flash_set('success','Charges updated.');
  } else {
    flash_set('error','No charges to update.');
  }
  header("Location: payments.php?student_id=".$student_id); exit();
}

// Apply payment (partial allowed, oldest first)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='apply_payment') {
  $student_id = (int)($_POST['student_id'] ?? 0);
  $amount_in  = (float)($_POST['amount'] ?? 0);
  $remarks    = trim($_POST['remarks'] ?? '');
  $admin_id   = (int)$_SESSION['admin_id'];

  if ($student_id <= 0 || $amount_in <= 0) {
    flash_set('error','Enter a valid payment amount.'); 
    header("Location: payments.php?student_id=".$student_id); exit();
  }

  // Fetch student for receipt
  $st = $conn->query("SELECT fullname, email FROM students WHERE id={$student_id} LIMIT 1");
  $student_fullname = ''; $student_email = '';
  if ($st && $st->num_rows) { $srow = $st->fetch_assoc(); $student_fullname = $srow['fullname']; $student_email = $srow['email']; }

  // Fetch unpaid/partial penalties in chronological order (oldest first)
  $pen = $conn->query("
    SELECT id, IFNULL(charge, {$default_fee}) AS charge, IFNULL(paid_amount,0) AS paid_amount
    FROM penalties
    WHERE student_id={$student_id} AND (payment_status='unpaid' OR payment_status='partial')
    ORDER BY date_issued ASC, id ASC
  ");

  $remaining = $amount_in;
  $applications = []; // [{penalty_id, applied}]
  $total_applied = 0.00;

  if ($pen && $pen->num_rows) {
    while ($row = $pen->fetch_assoc()) {
      $pid = (int)$row['id'];
      $charge = (float)$row['charge'];
      $paid   = (float)$row['paid_amount'];
      $balance = max($charge - $paid, 0);

      if ($balance <= 0) continue;
      if ($remaining <= 0) break;

      $apply = min($balance, $remaining);
      $remaining -= $apply;
      $total_applied += $apply;

      // Update the penalty
      $new_paid = $paid + $apply;
      $status = ($new_paid >= $charge) ? 'paid' : (($new_paid > 0) ? 'partial' : 'unpaid');
      $conn->query("UPDATE penalties SET paid_amount={$new_paid}, payment_status='{$status}' WHERE id={$pid} LIMIT 1");

      $applications[] = ['penalty_id'=>$pid, 'applied'=>round($apply,2)];
    }
  }

  if ($total_applied <= 0) {
    flash_set('error','Nothing to apply. Student has no outstanding balance.');
    header("Location: payments.php?student_id=".$student_id); exit();
  }

  // Record a payment receipt
  $apps_json = $conn->real_escape_string(json_encode($applications));
  $amt = round($total_applied,2);
  $rem = $conn->real_escape_string($remarks);
  $conn->query("INSERT INTO payments (student_id, amount, penalties_settled, remarks, received_by) VALUES ({$student_id}, {$amt}, '{$apps_json}', '{$rem}', {$admin_id})");

  // Compute remaining balance AFTER this payment
  $sum = $conn->query("
    SELECT SUM(IFNULL(charge, {$default_fee}) - IFNULL(paid_amount,0)) AS remaining_total
    FROM penalties
    WHERE student_id={$student_id} AND (payment_status='unpaid' OR payment_status='partial')
  ");
  $remaining_total = 0.00;
  if ($sum && $row = $sum->fetch_assoc()) $remaining_total = max(0, (float)$row['remaining_total']);

  // Email receipt to student
  $distributionParts = array_map(function($x){ return '#'.$x['penalty_id'].'='.peso($x['applied']); }, $applications);
  send_receipt_email(
    $student_email,
    $student_fullname,
    $amt,
    $remaining_total,
    $distributionParts,
    $school_name,
    ['host'=>$smtp_host,'user'=>$smtp_user,'pass'=>$smtp_pass,'sender'=>$smtp_sender]
  );

  $change_note = '';
  if ($amount_in > $total_applied) {
    $change = $amount_in - $total_applied;
    $change_note = " Change to return: ".peso($change).".";
  }

  flash_set('success', 'Payment recorded: '.peso($total_applied).'.'.$change_note);
  header("Location: payments.php?student_id=".$student_id); exit();
}

/* ---------- Search & Student Loading ---------- */

$search = trim($_GET['q'] ?? '');
$student_id = (int)($_GET['student_id'] ?? 0);
$student = null;

if ($student_id > 0) {
  $res = $conn->query("SELECT * FROM students WHERE id={$student_id} LIMIT 1");
  if ($res && $res->num_rows) $student = $res->fetch_assoc();
}

$students_list = null;
if ($search !== '' && !$student) {
  $s = $conn->real_escape_string($search);
  $students_list = $conn->query("
    SELECT id, fullname, student_code, course, year_level, section
    FROM students
    WHERE fullname LIKE '%$s%' OR student_code LIKE '%$s%'
    ORDER BY fullname ASC
    LIMIT 50
  ");
}

// Load penalties summary for selected student
$unpaid = []; $totals = ['charge'=>0,'paid'=>0,'balance'=>0];
$history = null;

if ($student) {
  $pen = $conn->query("
    SELECT id, violation, date_issued, remarks, 
           IFNULL(charge, {$default_fee}) AS charge,
           IFNULL(paid_amount,0) AS paid_amount,
           payment_status
    FROM penalties
    WHERE student_id={$student_id}
    ORDER BY date_issued DESC, id DESC
  ");

  if ($pen) {
    while ($r = $pen->fetch_assoc()) {
      $bal = max(((float)$r['charge'] - (float)$r['paid_amount']),0);
      $r['balance'] = $bal;
      if ($r['payment_status']!=='paid') {
        $unpaid[] = $r;
        $totals['charge']  += (float)$r['charge'];
        $totals['paid']    += (float)$r['paid_amount'];
        $totals['balance'] += $bal;
      }
    }
  }

  // Payment history (with admin who received)
  $history = $conn->query("
    SELECT p.*, a.fullname AS admin_name
    FROM payments p
    LEFT JOIN admins a ON a.id = p.received_by
    WHERE p.student_id={$student_id}
    ORDER BY p.payment_date DESC, p.id DESC
    LIMIT 50
  ");
}

include('includes/header.php');
$flash = flash_get();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Payments & Settlement</h3>
  </div>

  <?php if ($flash): ?>
    <script>
      Swal.fire({
        icon: '<?php echo $flash['type']==='success'?'success':'error'; ?>',
        title: '<?php echo $flash['type']==='success'?'Success':'Error'; ?>',
        html: '<?php echo addslashes($flash["msg"]); ?>',
        timer: 2400, showConfirmButton: false
      });
    </script>
  <?php endif; ?>

  <!-- Search -->
  <div class="card shadow-sm mb-3">
    <div class="card-header">
      <strong><i class="fa fa-search me-1"></i> Student Search</strong>
    </div>
    <div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-md-8">
          <input type="text" class="form-control" name="q" value="<?php echo h($search); ?>" placeholder="Search student by name or code...">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100"><i class="fa fa-search"></i></button>
        </div>
        <div class="col-md-2">
          <a class="btn btn-outline-secondary w-100" href="payments.php"><i class="fa fa-times me-1"></i> Clear</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Search results -->
  <?php if ($students_list && $students_list->num_rows && !$student): ?>
    <div class="card mb-3">
      <div class="card-header bg-white"><strong>Search Results</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover m-0">
            <thead class="table-light">
              <tr>
                <th>Student</th><th>Course / Year / Section</th><th style="width:140px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php while($s=$students_list->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?php echo h($s['fullname']); ?></div>
                    <div class="text-muted" style="font-size:12px;"><?php echo h($s['student_code']); ?></div>
                  </td>
                  <td><?php echo h($s['course'].' / '.$s['year_level'].' / '.$s['section']); ?></td>
                  <td>
                    <a class="btn btn-sm btn-primary" href="payments.php?student_id=<?php echo (int)$s['id']; ?>">View Penalties</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Selected student panel -->
  <?php if ($student): ?>
    <div class="card mb-3">
      <div class="card-header bg-white"><strong>Student</strong></div>
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-bold"><?php echo h($student['fullname']); ?></div>
            <div class="text-muted"><?php echo h($student['student_code']); ?></div>
            <div class="text-muted"><?php echo h($student['course'].' / '.$student['year_level'].' / '.$student['section']); ?></div>
          </div>
          <div class="text-end">
            <div>Total Outstanding: <span class="badge bg-danger"><?php echo peso($totals['balance']); ?></span></div>
            <div class="text-muted" style="font-size:12px;">(Unpaid + Partial only)</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Unpaid/Partial Penalties -->
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card mb-3">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Open Penalties (Unpaid / Partial)</strong>
            <span class="text-muted" style="font-size:12px;">Default charge: <?php echo peso($default_fee); ?> (editable)</span>
          </div>
          <div class="card-body p-0">
            <form method="post">
              <input type="hidden" name="action" value="update_charges">
              <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">
              <div class="table-responsive">
                <table class="table table-hover m-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Violation</th>
                      <th class="text-end">Charge</th>
                      <th class="text-end">Paid</th>
                      <th class="text-end">Balance</th>
                      <th class="text-end" style="width:140px;">Edit Charge</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($unpaid)): foreach($unpaid as $p): ?>
                      <tr>
                        <td><span class="text-muted" style="font-size:12px;"><?php echo h($p['date_issued']); ?></span></td>
                        <td>
                          <div class="fw-semibold"><?php echo h($p['violation']); ?></div>
                          <div class="text-muted" style="font-size:12px;"><?php echo h($p['remarks'] ?? ''); ?></div>
                        </td>
                        <td class="text-end"><?php echo peso($p['charge']); ?></td>
                        <td class="text-end"><?php echo peso($p['paid_amount']); ?></td>
                        <td class="text-end"><?php echo peso($p['balance']); ?></td>
                        <td class="text-end">
                          <input type="number" step="0.01" min="0" name="charge[<?php echo (int)$p['id']; ?>]" class="form-control form-control-sm" value="<?php echo h($p['charge']); ?>">
                        </td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="6" class="text-center text-muted">No open penalties.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="p-3 d-flex justify-content-end">
                <button class="btn btn-outline-secondary btn-sm" <?php echo count($unpaid)?'':'disabled'; ?>>
                  <i class="fa fa-save me-1"></i> Save Charges
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Payment Form -->
      <div class="col-lg-5">
        <div class="card">
          <div class="card-header bg-white"><strong>Accept Payment</strong></div>
          <div class="card-body">
            <div class="mb-2 small text-muted">
              Auto-applies to <strong>oldest</strong> penalties first. Partial amount allowed.
            </div>
            <form method="post">
              <input type="hidden" name="action" value="apply_payment">
              <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">

              <div class="mb-3">
                <label class="form-label">Outstanding Balance</label>
                <input type="text" class="form-control" value="<?php echo peso($totals['balance']); ?>" readonly>
              </div>

              <div class="mb-3">
                <label class="form-label">Amount Received (₱)</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="e.g., 20.00" required <?php echo $totals['balance']>0?'':'disabled'; ?>>
              </div>

              <div class="mb-3">
                <label class="form-label">Remarks (optional)</label>
                <input type="text" name="remarks" class="form-control" placeholder="OR #, cashier, etc.">
              </div>

              <button class="btn btn-primary w-100" <?php echo $totals['balance']>0?'':'disabled'; ?>>
                <i class="fa fa-receipt me-1"></i> Record Payment
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment History -->
    <div class="card mt-3">
      <div class="card-header bg-white"><strong>Payment History</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm m-0">
            <thead class="table-light">
              <tr>
                <th>When</th>
                <th>Amount</th>
                <th>Received By</th>
                <th>Remarks</th>
                <th>Distribution</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($history && $history->num_rows): while($p=$history->fetch_assoc()): ?>
                <tr>
                  <td><span class="text-muted" style="font-size:12px;"><?php echo h($p['payment_date']); ?></span></td>
                  <td class="fw-semibold"><?php echo peso($p['amount']); ?></td>
                  <td><?php echo h($p['admin_name'] ?: ('Admin #'.$p['received_by'])); ?></td>
                  <td><?php echo h($p['remarks'] ?: ''); ?></td>
                  <td>
                    <?php
                      $apps = json_decode($p['penalties_settled'], true);
                      if (is_array($apps) && count($apps)) {
                        $parts = array_map(function($x){ return '#'.$x['penalty_id'].'='.peso($x['applied']); }, $apps);
                        echo '<span class="text-muted" style="font-size:12px;">'.h(implode(', ', $parts)).'</span>';
                      } else {
                        echo '<span class="text-muted">—</span>';
                      }
                    ?>
                  </td>
                </tr>
              <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-center text-muted">No payments yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php include('includes/footer.php'); ?>
