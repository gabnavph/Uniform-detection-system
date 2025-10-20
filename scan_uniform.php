<?php
// scan_uniform.php — Scanning page with persistent detection + live status
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: admin/login.php"); exit(); }

require_once("db.php");
require_once("admin/includes/settings_helper.php"); // settings
require_once("admin/includes/activity_logger.php"); // activity logging

// PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once("PHPMailer/src/Exception.php");
require_once("PHPMailer/src/PHPMailer.php");
require_once("PHPMailer/src/SMTP.php");

// ---------------------------
// CONFIG & SETTINGS
// ---------------------------
$DETECTION_API  = "http://192.168.1.11:5000/detections";
$VIDEO_FEED_URL = "http://192.168.1.11:5000/video_feed";

// Branding from settings
$system_name  = get_setting($conn,'system_name','Uniform Monitoring System');
$school_name  = get_setting($conn,'school_name','Your School Name');

$school_logo = get_setting($conn, 'school_logo', ''); // optional

// Policy from settings
$require_id    = get_setting($conn,'require_id','1') === '1';
$require_shoes = get_setting($conn,'require_shoes','1') === '1';

// Penalty from settings
$default_penalty = (float)get_setting($conn,'default_penalty','5');

// Detection sensitivity from settings (0.1 to 1.0, default 0.58)
$detection_sensitivity = (float)get_setting($conn,'detection_sensitivity','0.58');

// SMTP from settings
$smtp_host   = get_setting($conn,'smtp_host','smtp-relay.brevo.com');
$smtp_user   = get_setting($conn,'smtp_user','');
$smtp_pass   = get_setting($conn,'smtp_pass','');
$smtp_sender = get_setting($conn,'smtp_sender_name','Uniform Monitoring System');

// YOLO class map
$CLASS_MAP = [
  0 => "ID",
  1 => "female_dress",
  2 => "female_skirt",
  3 => "male_dress",
  4 => "male_pants",
  5 => "shoes",
];

// ---------------------------
// UTILITIES
// ---------------------------
function fetch_detections($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 3,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err || $code !== 200) return [ 'ok' => false, 'error' => $err ?: ("HTTP ".$code) ];
  $json = json_decode($resp, true);
  if (!is_array($json)) return [ 'ok' => false, 'error' => 'Invalid JSON' ];
    return [ 'ok' => true, 'data' => $json ];
}

function set_detection_confidence($base_url, $confidence) {
  $url = str_replace('/detections', "/set_confidence/" . $confidence, $base_url);
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 3,
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ($code === 200);
}

// ---------------------------

function send_formal_email($toEmail, $toName, $subject, $html, $text, $smtp) {
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

    $fromAddress = 'nikkapatriciafrancisco@gmail.com';
    $fromName    = $smtp['sender'] ?: 'Uniform Monitoring System';
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($toEmail, $toName ?? '');

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    $mail->send();
  } catch (Exception $e) {
    // optional: log $mail->ErrorInfo
  }
}

// Persistent memory helpers (per student code)
function init_flags_for_code($code) {
  if (!isset($_SESSION['detected_items'])) $_SESSION['detected_items'] = [];
  if (!isset($_SESSION['detected_items'][$code])) {
    $_SESSION['detected_items'][$code] = ['id'=>false,'top'=>false,'bottom'=>false,'shoes'=>false];
  }
}
function merge_flags_with_idset($code, $idset) {
  init_flags_for_code($code);
  // Only ever set TRUE; never revert to FALSE
  if (isset($idset[0])) $_SESSION['detected_items'][$code]['id'] = true;
  if (isset($idset[1]) || isset($idset[3])) $_SESSION['detected_items'][$code]['top'] = true;
  if (isset($idset[2]) || isset($idset[4])) $_SESSION['detected_items'][$code]['bottom'] = true;
  if (isset($idset[5])) $_SESSION['detected_items'][$code]['shoes'] = true;
}
function get_flags($code) {
  init_flags_for_code($code);
  return $_SESSION['detected_items'][$code];
}
function clear_flags($code) {
  if (isset($_SESSION['detected_items'][$code])) unset($_SESSION['detected_items'][$code]);
}

// ---------------------------
// PARTIAL endpoint for recent table (AJAX refresh)
// ---------------------------
if (isset($_GET['partial']) && $_GET['partial']==='recent') {
  $recent = $conn->query("SELECT ul.*, s.fullname, s.student_code
                          FROM uniform_logs ul
                          JOIN students s ON s.id = ul.student_id
                          ORDER BY ul.detected_at DESC
                          LIMIT 10");
  header('Content-Type: text/html; charset=utf-8');
  echo '<tbody id="recentBody">';
  if ($recent && $recent->num_rows) {
    while($r = $recent->fetch_assoc()){
      echo '<tr>';
      echo '<td><div class="fw-semibold" style="font-size:13px;">'.htmlspecialchars($r['fullname']).'</div>';
      echo '<div class="text-muted" style="font-size:12px;">'.htmlspecialchars($r['student_code']).'</div></td>';
      echo '<td>'.($r['status']==='complete'
        ? '<span class="badge bg-success">Complete</span>'
        : '<span class="badge bg-danger">Incomplete</span>').'</td>';
      echo '<td><span class="text-muted" style="font-size:12px;">'.htmlspecialchars($r['detected_at']).'</span></td>';
      echo '</tr>';
    }
  } else {
    echo '<tr><td colspan="3" class="text-center text-muted">No recent logs.</td></tr>';
  }
  echo '</tbody>';
  exit();
}

// ---------------------------
// AJAX: validate student BEFORE countdown
// ---------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_validate'])) {
  header('Content-Type: application/json');
  $code = trim($_POST['scan_student_code'] ?? '');
  if ($code==='') { echo json_encode(['ok'=>false,'msg'=>'Please scan a student barcode.']); exit(); }

  $sc = $conn->real_escape_string($code);
  $res = $conn->query("SELECT id,student_code,fullname,email,contact,course,year_level,section,photo FROM students WHERE student_code='$sc' LIMIT 1");
  if (!$res || !$res->num_rows) { echo json_encode(['ok'=>false,'msg'=>'No student found for that barcode.']); exit(); }
  $st = $res->fetch_assoc();

  // initialize persistent flags for this student code
  init_flags_for_code($code);

  // Photo URL (fallback placeholder)
  $photoUrl = '';
  if (!empty($st['photo']) && file_exists(__DIR__ . '/' . $st['photo'])) {
    $photoUrl = $st['photo']; // relative path like uploads/students/xxx.jpg
  } else {
    $photoUrl = 'https://cdn.jsdelivr.net/gh/edent/SuperTinyIcons/images/svg/user.svg';
  }

  echo json_encode([
    'ok'=>true,
    'student'=>[
      'id'          => (int)$st['id'],
      'student_code'=> $st['student_code'],
      'fullname'    => $st['fullname'],
      'email'       => $st['email'],
      'contact'     => $st['contact'],
      'course'      => $st['course'],
      'year_level'  => $st['year_level'],
      'section'     => $st['section'],
      'photo'       => $photoUrl
    ],
    'rules'=>[
      'require_id'    => $require_id ? 1 : 0,
      'require_shoes' => $require_shoes ? 1 : 0
    ]
  ]);
  exit();
}

// ---------------------------
// AJAX: TICK — called every ~1s during the 5s detection window
// merges current YOLO detections into the persistent flags and returns the flags.
// ---------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_tick'])) {
  header('Content-Type: application/json');
  $code = trim($_POST['scan_student_code'] ?? '');
  if ($code==='') { echo json_encode(['ok'=>false,'msg'=>'No code.']); exit(); }

  // Set detection confidence and fetch detections
  set_detection_confidence($DETECTION_API, $detection_sensitivity);
  $det = fetch_detections($DETECTION_API);
  if (!$det['ok']) {
    echo json_encode(['ok'=>false,'msg'=>'Detection server unreachable: '.$det['error']]); exit();
  }
  $payload = $det['data'];

  $ids = isset($payload['detected_ids']) && is_array($payload['detected_ids'])
    ? array_map('intval', $payload['detected_ids']) : [];
  $idset = array_fill_keys($ids, true);

  // Merge into persistent flags
  merge_flags_with_idset($code, $idset);

  // Return flags for UI
  $flags = get_flags($code);
  echo json_encode(['ok'=>true,'flags'=>$flags]);
  exit();
}

// ---------------------------
// AJAX: FINALIZE — called once after 5s detection window finishes
// does one last merge, computes final result, logs, emails, and clears flags.
// ---------------------------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_detect'])) {
  header('Content-Type: application/json');

  $code = trim($_POST['scan_student_code'] ?? '');
  if ($code==='') { echo json_encode(['ok'=>false,'type'=>'warning','msg'=>'Please scan a student barcode.']); exit(); }

  // Find student
  $sc = $conn->real_escape_string($code);
  $res = $conn->query("SELECT * FROM students WHERE student_code='$sc' LIMIT 1");
  if (!$res || !$res->num_rows) { echo json_encode(['ok'=>false,'type'=>'error','msg'=>'Student not found.']); exit(); }
  $student = $res->fetch_assoc();

  // Set detection confidence before scanning
  set_detection_confidence($DETECTION_API, $detection_sensitivity);
  
  // Last update from detection server (merge once more)
  $det = fetch_detections($DETECTION_API);
  if ($det['ok']) {
    $ids = isset($det['data']['detected_ids']) && is_array($det['data']['detected_ids'])
      ? array_map('intval', $det['data']['detected_ids']) : [];
    $idset = array_fill_keys($ids, true);
    merge_flags_with_idset($code, $idset);
  }

  // Read persistent flags (final)
  $flags = get_flags($code);
  $has_id     = $flags['id'];
  $has_top    = $flags['top'];
  $has_bottom = $flags['bottom'];
  $has_shoes  = $flags['shoes'];

  // Apply rules
  $missing = [];
  if ($require_id && !$has_id) $missing[] = "ID";
  if (!$has_top)    $missing[] = "Top (female_dress/male_dress)";
  if (!$has_bottom) $missing[] = "Bottom (female_skirt/male_pants)";
  if ($require_shoes && !$has_shoes) $missing[] = "Shoes";

  $status = count($missing) ? 'incomplete' : 'complete';

  // Log detected names from flags (for audit visibility)
  $detected_names = [];
  if ($has_id) $detected_names[] = "ID";
  if ($has_top) $detected_names[] = "Top";
  if ($has_bottom) $detected_names[] = "Bottom";
  if ($has_shoes) $detected_names[] = "shoes";

  $student_id = (int)$student['id'];
  $detected_json = $conn->real_escape_string(json_encode($detected_names));
  $conn->query("INSERT INTO uniform_logs (student_id, detected_items, status, detected_at)
                VALUES ($student_id, '$detected_json', '$status', NOW())");

  // Log uniform scan activity
  log_activity($conn, ActivityActions::UNIFORM_SCANNED, ActivityTargets::STUDENT, $student_id, $student['fullname'], "Uniform scanned - Status: $status, Items detected: " . implode(', ', $detected_names));

  // If incomplete => penalty + email
  if ($status==='incomplete') {
    $violation = "Incomplete uniform: ".implode(", ", $missing);
    $charge = $default_penalty;
    $result = $conn->query("INSERT INTO penalties (student_id, violation, charge, paid_amount, payment_status, date_issued, remarks)
                  VALUES ($student_id, '".$conn->real_escape_string($violation)."', {$charge}, 0, 'unpaid', NOW(), NULL)");
    
    if ($result) {
      $penalty_id = $conn->insert_id;
      log_activity($conn, ActivityActions::PENALTY_CREATED, ActivityTargets::PENALTY, $penalty_id, $student['fullname'], "Penalty created: $violation (₱$charge)");
      log_activity($conn, ActivityActions::VIOLATION_DETECTED, ActivityTargets::STUDENT, $student_id, $student['fullname'], "Violation detected: " . implode(', ', $missing));
    }

    $html = "Dear ".htmlspecialchars($student['fullname']).",<br><br>"
          . "This is to inform you that during today’s uniform check, your uniform was assessed as <strong>incomplete</strong> as per school policy.<br>"
          . "Missing item(s): <strong>".htmlspecialchars(implode(', ', $missing))."</strong>.<br><br>"
          . "A record has been noted. Kindly comply in future inspections to avoid further actions.<br><br>"
          . "<em>— ".htmlspecialchars($smtp_sender)."<br>".htmlspecialchars($school_name)."</em>";
    $txt  = "Dear {$student['fullname']},\n\nDuring today’s uniform check, your uniform was incomplete.\n"
          . "Missing item(s): ".implode(', ',$missing).".\n\n— {$smtp_sender}\n{$school_name}";
    if (!empty($student['email'])) {
      send_formal_email(
        $student['email'],
        $student['fullname'],
        'Uniform Violation Notice',
        $html,
        $txt,
        ['host'=>$smtp_host,'user'=>$smtp_user,'pass'=>$smtp_pass,'sender'=>$smtp_sender]
      );
    }

    // clear flags for this student
    clear_flags($code);

    echo json_encode([
      'ok'=>true,'type'=>'error',
      'msg'=>"INCOMPLETE — Missing: ".implode(', ',$missing).". Penalty recorded and email sent.",
      'student'=>$student,'status'=>$status,'flags'=>$flags
    ]);
    exit();
  }

  // Complete => courtesy email (optional)
  $html = "Dear ".htmlspecialchars($student['fullname']).",<br><br>"
        . "Our records show that your uniform is complete. Thank you for observing the dress code policy.<br><br>"
        . "<em>— ".htmlspecialchars($smtp_sender)."<br>".htmlspecialchars($school_name)."</em>";
  $txt  = "Dear {$student['fullname']},\n\nYour uniform has been verified as complete. Thank you for observing the policy.\n\n— {$smtp_sender}\n{$school_name}";
  if (!empty($student['email'])) {
    send_formal_email(
      $student['email'],
      $student['fullname'],
      'Uniform Check — Passed',
      $html,
      $txt,
      ['host'=>$smtp_host,'user'=>$smtp_user,'pass'=>$smtp_pass,'sender'=>$smtp_sender]
    );
  }

  // Complete => log compliance verification
  log_activity($conn, ActivityActions::COMPLIANCE_VERIFIED, ActivityTargets::STUDENT, $student_id, $student['fullname'], "Uniform compliance verified - All required items present");

  // clear flags for this student
  clear_flags($code);

  echo json_encode(['ok'=>true,'type'=>'success','msg'=>'COMPLETE — Uniform verified and logged.','student'=>$student,'status'=>$status,'flags'=>$flags]);
  exit();
}

// ---------------------------
// Fetch recent logs for first render
// ---------------------------
$recent = $conn->query("SELECT ul.*, s.fullname, s.student_code
                        FROM uniform_logs ul
                        JOIN students s ON s.id = ul.student_id
                        ORDER BY ul.detected_at DESC
                        LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($system_name); ?> — Scanning Station</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
  body { background:#f8f9fa; }
  .brand-head { text-align:center; padding:18px 10px 6px; }
  .school-logo-container { display: flex; justify-content: center; align-items: center; }
  .school-logo { 
    max-height: 80px; 
    max-width: 200px; 
    height: auto; 
    width: auto; 
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  .brand-title { font-size:22px; font-weight:800; letter-spacing:.5px; }
  .brand-sub { color:#6c757d; margin-top:-2px; }
  .back-link { position:fixed; top:10px; right:12px; z-index:10; }
  .card-header-maroon { background:#7B1113; color:#fff; }
  .stream { width:100%; max-width:980px; border-radius:10px; display:block; margin:auto; }
  .live-status { text-align:left; font-size:14px; margin-top:8px; }
  .live-status .ok { color:#198754; font-weight:600; }
  .live-status .miss { color:#dc3545; font-weight:600; }
</style>
</head>
<body>
  <a href="admin/index.php" class="back-link btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> Back to Admin</a>

  <div class="brand-head">
    <?php if (!empty($school_logo)): ?>
      <div class="school-logo-container mb-3">
        <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" class="school-logo">
      </div>
    <?php endif; ?>
    <div class="brand-title"><?php echo htmlspecialchars(strtoupper($system_name)); ?></div>
    <div class="brand-sub"><?php echo htmlspecialchars($school_name); ?></div>
  </div>

  <div class="container-lg">
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
          <div class="card-header card-header-maroon">Live Camera — Detection Stream</div>
          <div class="card-body">
            <img src="<?php echo htmlspecialchars($VIDEO_FEED_URL); ?>" class="stream" alt="Live stream">
            <div class="text-muted mt-2" style="font-size:12px;">If the stream doesn’t load, ensure the Python server is running on port 5000.</div>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-white"><strong>Scan Student Barcode</strong></div>
          <div class="card-body">
            <form id="scanForm" class="row g-2 align-items-center">
              <div class="col-md-6">
                <input autofocus autocomplete="off" type="text" name="scan_student_code" id="scan_input" class="form-control" placeholder="Focus here and scan barcode..." required>
              </div>
              <div class="col-md-4">
                <button class="btn btn-primary" type="submit"><i class="fa fa-barcode me-1"></i> Scan</button>
              </div>
            </form>

            <div id="resultBox" class="mt-3"></div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white"><strong>Student Details</strong></div>
          <div class="card-body" id="studentDetails">
            <div class="text-muted">Scan a barcode to load student details.</div>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Recent Activity</strong>
            <span class="badge bg-light text-dark">Policy: 
              <?php
                $bits = [];
                if ($require_id) $bits[] = 'Require ID';
                if ($require_shoes) $bits[] = 'Require Shoes';
                echo htmlspecialchars(implode(' • ', $bits) ?: 'Standard');
              ?>
            </span>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm m-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:60%;">Student</th>
                    <th>Status</th>
                    <th style="width:35%;">Time</th>
                  </tr>
                </thead>
                <tbody id="recentBody">
                  <?php if ($recent && $recent->num_rows): while($r=$recent->fetch_assoc()): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold" style="font-size:13px;"><?php echo htmlspecialchars($r['fullname']); ?></div>
                        <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['student_code']); ?></div>
                      </td>
                      <td>
                        <?php if ($r['status']==='complete'): ?>
                          <span class="badge bg-success">Complete</span>
                        <?php else: ?>
                          <span class="badge bg-danger">Incomplete</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($r['detected_at']); ?></span></td>
                    </tr>
                  <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted">No recent logs.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const scanForm  = document.getElementById('scanForm');
const scanInput = document.getElementById('scan_input');

function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }
function renderStudentDetails(st) {
  const el = document.getElementById('studentDetails');
  if (!st) { el.innerHTML = '<div class="text-muted">Scan a barcode to load student details.</div>'; return; }
  const photo = st.photo ? escapeHtml(st.photo) : 'https://cdn.jsdelivr.net/gh/edent/SuperTinyIcons/images/svg/user.svg';
  el.innerHTML = `
    <div class="d-flex align-items-center gap-3">
      <img src="${photo}" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:1px solid #eee;">
      <div>
        <div class="fw-semibold">${escapeHtml(st.fullname||'')}</div>
        <div class="text-muted" style="font-size:13px;">Code: ${escapeHtml(st.student_code||'')}</div>
        <div class="text-muted" style="font-size:13px;">${escapeHtml(st.course||'')} / ${escapeHtml(st.year_level||'')} / ${escapeHtml(st.section||'')}</div>
      </div>
    </div>
  `;
}

// Pretty text helper
function okx(b){ return b ? '<span class="ok">✔ Detected</span>' : '<span class="miss">✘ Missing</span>'; }

// 1) Validate student FIRST (no countdown if not found)
scanForm.addEventListener('submit', function(e){
  e.preventDefault();
  const code = (scanInput.value||'').trim();
  if (!code) return;
  scanInput.disabled = true;

  fetch('scan_uniform.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ ajax_validate:'1', scan_student_code: code })
  })
  .then(r=>r.json())
  .then(data=>{
    if (!data.ok) {
      scanInput.disabled=false; scanInput.focus(); scanInput.select?.();
      Swal.fire({icon:'error', title:'Not Found', text:data.msg||'Student not found.'});
      return;
    }

    renderStudentDetails(data.student);

    let countdown = 5;
    const photo = data.student.photo||'';
    const fullname = data.student.fullname||'';
    const cys = `${data.student.course||''} / ${data.student.year_level||''} / ${data.student.section||''}`;
    Swal.fire({
      title: 'Please position yourself for inspection.',
      html: `
        <div class="d-flex flex-column align-items-center">
          <img src="${escapeHtml(photo)}" style="width:88px;height:88px;object-fit:cover;border-radius:50%;border:1px solid #eee;margin-bottom:8px;">
          <div style="font-weight:600;">${escapeHtml(fullname)}</div>
          <div class="text-muted" style="font-size:13px;margin-bottom:8px;">${escapeHtml(cys)}</div>
          Detection will begin in <b>${countdown}</b> seconds.
        </div>
      `,
      icon: 'info',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        const b = Swal.getHtmlContainer().querySelector('b');
        const t1 = setInterval(()=>{
          countdown--;
          if (b) b.textContent = countdown;
          if (countdown<=0) {
            clearInterval(t1);

            // Now run a 5s "Detecting…" window with live status
            let liveHtml = `
              <div>Live detection in progress…</div>
              <div class="live-status" id="liveStatus">
                <div>ID: <span data-k="id" class="miss">✘ Missing</span></div>
                <div>Top: <span data-k="top" class="miss">✘ Missing</span></div>
                <div>Bottom: <span data-k="bottom" class="miss">✘ Missing</span></div>
                <div>Shoes: <span data-k="shoes" class="miss">✘ Missing</span></div>
              </div>
              <div class="text-muted" style="font-size:12px;margin-top:6px;">Once detected, an item stays detected for this scan.</div>
            `;
            Swal.fire({
              title: 'Detecting…',
              html: liveHtml,
              icon: 'info',
              timer: 5000,
              timerProgressBar: true,
              allowOutsideClick:false,
              allowEscapeKey:false,
              showConfirmButton:false,
              didOpen: () => {
                // Poll server every ~800ms to merge detections & get persistent flags
                const updateUI = (flags) => {
                  const root = Swal.getHtmlContainer();
                  if (!root) return;
                  ['id','top','bottom','shoes'].forEach(k=>{
                    const el = root.querySelector(`span[data-k="${k}"]`);
                    if (!el) return;
                    if (flags && flags[k]) {
                      el.textContent = '✔ Detected';
                      el.classList.remove('miss');
                      el.classList.add('ok');
                    }
                  });
                };
                // Start polling
                const h = setInterval(()=>{
                  fetch('scan_uniform.php', {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ ajax_tick:'1', scan_student_code: code })
                  })
                  .then(r=>r.json())
                  .then(d=>{ if (d && d.ok && d.flags) updateUI(d.flags); })
                  .catch(()=>{ /* ignore transient errors during polling */ });
                }, 800);

                // Stop polling when modal closes
                Swal.getPopup().addEventListener('mouseleave', ()=>{}); // noop
                const stop = () => clearInterval(h);
                Swal.getPopup().addEventListener('hide', stop);
                // also stop when it closes by timer
                setTimeout(stop, 5200);
              }
            }).then(()=>{
              // Finalize result
              runAjaxDetection(code);
            });
          }
        },1000);
      }
    });
  })
  .catch(err=>{
    scanInput.disabled=false; scanInput.focus(); scanInput.select?.();
    Swal.fire({icon:'error', title:'Error', text:'Validation failed.'});
  });
});

// 2) Perform detection FINALIZE (server-side logs+email, clears session flags)
function runAjaxDetection(code){
  fetch('scan_uniform.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ ajax_detect:'1', scan_student_code: code })
  })
  .then(r=>r.json())
  .then(data=>{
    scanInput.disabled=false; scanInput.focus(); scanInput.select?.();

    if (!data.ok) {
      Swal.fire({icon:(data.type==='warning'?'warning':'error'), title:'Notice', text:data.msg||'Error occurred.'});
      document.getElementById('resultBox').innerHTML = `<div class="alert alert-${data.type==='warning'?'warning':'danger'}">${escapeHtml(data.msg||'')}</div>`;
      return;
    }

    if (data.student) renderStudentDetails(data.student);

    if (data.type==='success') {
      Swal.fire({icon:'success', title:'Complete', text:data.msg||'Uniform verified.', timer:2200, showConfirmButton:false});
      document.getElementById('resultBox').innerHTML = `<div class="alert alert-success">${escapeHtml(data.msg||'')}</div>`;
    } else {
      Swal.fire({icon:'error', title:'Incomplete', text:data.msg||'Uniform incomplete.', timer:3000, showConfirmButton:false});
      document.getElementById('resultBox').innerHTML = `<div class="alert alert-danger">${escapeHtml(data.msg||'')}</div>`;
    }

    try { refreshRecent(); } catch(e){}
  })
  .catch(err=>{
    scanInput.disabled=false; scanInput.focus(); scanInput.select?.();
    Swal.fire({icon:'error', title:'Error', text:'Failed to perform detection.'});
  });
}

// Refresh recent logs
function refreshRecent(){
  fetch('scan_uniform.php?partial=recent')
    .then(r=>r.text())
    .then(html=>{
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      const newBody = tmp.querySelector('#recentBody');
      if (newBody) document.getElementById('recentBody').innerHTML = newBody.innerHTML;
    }).catch(()=>{});
}

window.addEventListener('load', ()=>{ scanInput.focus(); scanInput.select?.(); });
</script>
</body>
</html>
