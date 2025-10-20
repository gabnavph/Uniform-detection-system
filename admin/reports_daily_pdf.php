<?php
// admin/reports_daily_pdf.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

require_once("../db.php");
require_once("includes/settings_helper.php");

// ---- Dompdf bootstrap (Composer preferred) ----
$dompdfLoaded = false;
try {
  if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $dompdfLoaded = true;
  }
} catch (Throwable $e) {}
if (!$dompdfLoaded) {
  // fallback (if someone installed dompdf differently)
  $alt1 = __DIR__ . "/../dompdf/autoload.inc.php";
  if (file_exists($alt1)) { require_once $alt1; $dompdfLoaded = true; }
}
if (!$dompdfLoaded) {
  die("Dompdf is not installed. Run: <code>composer require dompdf/dompdf</code>");
}

use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- Helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n){ return "₱".number_format((float)$n, 2); }

// ---------- Settings / Branding ----------
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');

// ---------- Filters ----------
$COURSES  = ["BSIT","BSHM"];
$YEARS    = ["1st Year","2nd Year","3rd Year","4th Year"];
$SECTIONS = ["A","B","C","D"];

$sel_date = $_GET['date'] ?? date('Y-m-d');
$fcourse  = $_GET['course'] ?? '';
$fyear    = $_GET['year'] ?? '';
$fsection = $_GET['section'] ?? '';

$where_student = "1";
if ($fcourse !== '' && in_array($fcourse,$COURSES)) {
  $where_student .= " AND s.course='".$conn->real_escape_string($fcourse)."'";
}
if ($fyear !== '' && in_array($fyear,$YEARS)) {
  $where_student .= " AND s.year_level='".$conn->real_escape_string($fyear)."'";
}
if ($fsection !== '' && in_array($fsection,$SECTIONS)) {
  $where_student .= " AND s.section='".$conn->real_escape_string($fsection)."'";
}
$esc_date = $conn->real_escape_string($sel_date);

// ---------- KPIs ----------
$q_scans = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND {$where_student}");
$total_scans = ($q_scans && $q_scans->num_rows) ? (int)$q_scans->fetch_assoc()['n'] : 0;

$q_comp = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='complete' AND {$where_student}");
$total_compliant = ($q_comp && $q_comp->num_rows) ? (int)$q_comp->fetch_assoc()['n'] : 0;

$q_incomp = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='incomplete' AND {$where_student}");
$total_violations = ($q_incomp && $q_incomp->num_rows) ? (int)$q_incomp->fetch_assoc()['n'] : 0;

$compliance_rate = ($total_scans>0) ? round(($total_compliant/$total_scans)*100,1) : 0.0;

// Financial KPIs
$q_pen_sum = $conn->query("
  SELECT IFNULL(SUM(p.charge),0) AS amt
  FROM penalties p
  JOIN students s ON s.id = p.student_id
  WHERE DATE(p.date_issued)='{$esc_date}' AND {$where_student}");
$penalties_issued_amt = ($q_pen_sum && $q_pen_sum->num_rows) ? (float)$q_pen_sum->fetch_assoc()['amt'] : 0.0;

$q_pay_sum = $conn->query("
  SELECT IFNULL(SUM(p.amount),0) AS amt
  FROM payments p
  JOIN students s ON s.id = p.student_id
  WHERE DATE(p.payment_date)='{$esc_date}' AND {$where_student}");
$payments_collected_amt = ($q_pay_sum && $q_pay_sum->num_rows) ? (float)$q_pay_sum->fetch_assoc()['amt'] : 0.0;

$q_out = $conn->query("
  SELECT IFNULL(SUM(p.charge - p.paid_amount),0) AS bal
  FROM penalties p
  JOIN students s ON s.id = p.student_id
  WHERE (p.payment_status='unpaid' OR p.payment_status='partial') AND {$where_student}");
$outstanding_amt = ($q_out && $q_out->num_rows) ? (float)$q_out->fetch_assoc()['bal'] : 0.0;

// Top 5 repeat violators (selected date + filters)
$top_repeat = $conn->query("
  SELECT s.student_code, s.fullname, s.course, s.year_level, s.section, COUNT(*) AS vcount
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='incomplete' AND {$where_student}
  GROUP BY s.student_code, s.fullname, s.course, s.year_level, s.section
  ORDER BY vcount DESC, s.fullname ASC
  LIMIT 5
");

// Violations detail table
$violations = $conn->query("
  SELECT s.student_code, s.fullname, s.course, s.year_level, s.section, ul.detected_items, ul.detected_at
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}'
    AND ul.status='incomplete'
    AND {$where_student}
  ORDER BY ul.detected_at DESC, ul.id DESC
");

// ---------- Prepare logo (embed as base64 if exists) ----------
$logoHtml = '';
$logoFs = __DIR__ . '/assets/images/logo.png';
if (file_exists($logoFs)) {
  $imgData = base64_encode(file_get_contents($logoFs));
  $logoHtml = '<img src="data:image/png;base64,' . $imgData . '" style="height:64px;width:auto;">';
}

// ---------- Filters summary text ----------
$filtersSummary = [];
$filtersSummary[] = ($fcourse   !== '' ? $fcourse   : 'All Courses');
$filtersSummary[] = ($fyear     !== '' ? $fyear     : 'All Years');
$filtersSummary[] = ($fsection  !== '' ? 'Section '.$fsection : 'All Sections');
$filtersText = implode(' / ', $filtersSummary);

// ---------- Build HTML ----------
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Daily Report — <?php echo h($sel_date); ?></title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
    .header { text-align: center; margin-bottom: 10px; }
    .header .sys-name { font-weight: 800; font-size: 16px; letter-spacing: .4px; }
    .header .school { color: #555; margin-top: -2px; }
    .header .title { font-weight: 700; margin-top: 6px; }
    .muted { color:#666; }
    .kpis, .money { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .kpis th, .kpis td, .money th, .money td { border:1px solid #ddd; padding:8px; }
    .kpis th, .money th { background:#f5f5f7; text-align:left; }
    .kpi-num { font-weight:700; font-size:14px; }
    .section-title { font-weight:700; margin: 12px 0 6px; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #ddd; padding: 6px; }
    table.grid th { background: #f8f8fa; text-align:left; }
    .text-right { text-align:right; }
    .small { font-size: 11px; }
    .footer { margin-top: 10px; text-align: right; font-size: 11px; color:#777; }
    .top { margin-top: 10px; }
  </style>
</head>
<body>

  <div class="header">
    <?php if ($logoHtml): ?>
      <div><?php echo $logoHtml; ?></div>
    <?php endif; ?>
    <div class="sys-name"><?php echo h(strtoupper($system_name)); ?></div>
    <div class="school"><?php echo h($school_name); ?></div>
    <div class="title">Daily Uniform Inspection Report</div>
    <div class="muted small">Date: <strong><?php echo h($sel_date); ?></strong> &nbsp; | &nbsp; Filters: <?php echo h($filtersText); ?></div>
  </div>

  <!-- KPI Blocks -->
  <table class="kpis">
    <tr>
      <th>Metric</th><th>Value</th>
      <th>Metric</th><th>Value</th>
    </tr>
    <tr>
      <td>Total Scans</td><td class="kpi-num"><?php echo (int)$total_scans; ?></td>
      <td>Compliant</td><td class="kpi-num"><?php echo (int)$total_compliant; ?></td>
    </tr>
    <tr>
      <td>Violations</td><td class="kpi-num"><?php echo (int)$total_violations; ?></td>
      <td>Compliance Rate</td><td class="kpi-num"><?php echo $compliance_rate . "%"; ?></td>
    </tr>
  </table>

  <!-- Financial KPIs -->
  <table class="money">
    <tr>
      <th>Penalties Issued (<?php echo h($sel_date); ?>)</th>
      <th>Payments Collected (<?php echo h($sel_date); ?>)</th>
      <th>Outstanding (All-time, filtered)</th>
    </tr>
    <tr>
      <td class="kpi-num"><?php echo peso($penalties_issued_amt); ?></td>
      <td class="kpi-num"><?php echo peso($payments_collected_amt); ?></td>
      <td class="kpi-num"><?php echo peso($outstanding_amt); ?></td>
    </tr>
  </table>

  <!-- Top repeat violators -->
  <div class="section-title top">Top 5 Repeat Violators (<?php echo h($sel_date); ?>)</div>
  <table class="grid">
    <thead>
      <tr>
        <th style="width:45%;">Student</th>
        <th>Course / Year / Section</th>
        <th class="text-right" style="width:90px;">Violations</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($top_repeat && $top_repeat->num_rows): while($t=$top_repeat->fetch_assoc()): ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?php echo h($t['fullname']); ?></div>
            <div class="small muted"><?php echo h($t['student_code']); ?></div>
          </td>
          <td><?php echo h($t['course'].' / '.$t['year_level'].' / '.$t['section']); ?></td>
          <td class="text-right"><?php echo (int)$t['vcount']; ?></td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="3" class="small muted" style="text-align:center;">No repeat violators for this date / filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Detailed violations table -->
  <div class="section-title top">Violations Detail (<?php echo h($sel_date); ?>)</div>
  <table class="grid">
    <thead>
      <tr>
        <th style="width:34%;">Student</th>
        <th>Course / Year / Section</th>
        <th>Detected Items</th>
        <th style="width:140px;">Detected At</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($violations && $violations->num_rows): while($r=$violations->fetch_assoc()): ?>
        <?php
          $items = $r['detected_items'];
          $try = json_decode($items,true);
          if (is_array($try)) $items = implode(", ", $try);
        ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?php echo h($r['fullname']); ?></div>
            <div class="small muted"><?php echo h($r['student_code']); ?></div>
          </td>
          <td><?php echo h($r['course']." / ".$r['year_level']." / ".$r['section']); ?></td>
          <td><?php echo h($items); ?></td>
          <td class="small muted"><?php echo h($r['detected_at']); ?></td>
        </tr>
      <?php endwhile; else: ?>
        <tr><td colspan="4" class="small muted" style="text-align:center;">No violations for this date / filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="footer">Generated by <?php echo h($system_name); ?> — <?php echo h($school_name); ?></div>
</body>
</html>
<?php
$html = ob_get_clean();

// ---------- Render PDF ----------
$options = new Options();
$options->set('isRemoteEnabled', true); // allow embedded images (we used base64 anyway)
$options->set('defaultFont', 'DejaVu Sans'); // better unicode support
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Smart filename: SchoolName_Uniform_Report_YYYY-MM-DD.pdf
$slug = preg_replace('/[^A-Za-z0-9]+/', '_', (string)$school_name);
if ($slug === '' || $slug === null) { $slug = 'School'; }
$filename = $slug . "_Uniform_Report_" . $sel_date . ".pdf";

// Stream to browser (inline)
$dompdf->stream($filename, ["Attachment" => false]); // set to true to force download
exit;
