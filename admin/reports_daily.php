<?php
// admin/reports_daily.php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/settings_helper.php"); // for system & school names

// Branding from settings
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function peso($n){ return "₱".number_format((float)$n, 2); }

// Static filters
$COURSES  = ["BSIT","BSHM"];
$YEARS    = ["1st Year","2nd Year","3rd Year","4th Year"];
$SECTIONS = ["A","B","C","D"];

// Inputs
$sel_date = $_GET['date'] ?? date('Y-m-d');
$fcourse  = $_GET['course'] ?? '';
$fyear    = $_GET['year'] ?? '';
$fsection = $_GET['section'] ?? '';

// Build WHERE for student-scoped queries
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

// ---------- Export CSV for today's violations (with current filters) ----------
if (($_GET['export'] ?? '') === 'csv') {
  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"daily_violations_{$sel_date}.csv\"");
  $out = fopen("php://output","w");
  fputcsv($out, ["Student Code","Full Name","Course","Year","Section","Detected Items","Detected At"]);

  $sqlCsv = "
    SELECT s.student_code, s.fullname, s.course, s.year_level, s.section, ul.detected_items, ul.detected_at
    FROM uniform_logs ul
    JOIN students s ON s.id = ul.student_id
    WHERE DATE(ul.detected_at)='".$conn->real_escape_string($sel_date)."'
      AND ul.status='incomplete'
      AND {$where_student}
    ORDER BY ul.detected_at DESC, ul.id DESC";
  if ($res = $conn->query($sqlCsv)) {
    while ($r = $res->fetch_assoc()) {
      $items = $r['detected_items'];
      $try = json_decode($items,true);
      if (is_array($try)) $items = implode(", ", $try);
      fputcsv($out, [
        $r['student_code'], $r['fullname'], $r['course'], $r['year_level'], $r['section'],
        $items, $r['detected_at'] // keep format as-is (per your preference C)
      ]);
    }
  }
  fclose($out);
  exit();
}

// ---------- Summary metrics ----------
$esc_date = $conn->real_escape_string($sel_date);

// Total scans
$q_scans = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND {$where_student}");
$total_scans = ($q_scans && $q_scans->num_rows) ? (int)$q_scans->fetch_assoc()['n'] : 0;

// Compliant
$q_comp = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='complete' AND {$where_student}");
$total_compliant = ($q_comp && $q_comp->num_rows) ? (int)$q_comp->fetch_assoc()['n'] : 0;

// Violations
$q_incomp = $conn->query("
  SELECT COUNT(*) AS n
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='incomplete' AND {$where_student}");
$total_violations = ($q_incomp && $q_incomp->num_rows) ? (int)$q_incomp->fetch_assoc()['n'] : 0;

// Penalties issued (today)
$q_pen_sum = $conn->query("
  SELECT IFNULL(SUM(p.charge),0) AS amt
  FROM penalties p
  JOIN students s ON s.id = p.student_id
  WHERE DATE(p.date_issued)='{$esc_date}' AND {$where_student}");
$penalties_issued_amt = ($q_pen_sum && $q_pen_sum->num_rows) ? (float)$q_pen_sum->fetch_assoc()['amt'] : 0.0;

// Payments collected (today)
$q_pay_sum = $conn->query("
  SELECT IFNULL(SUM(p.amount),0) AS amt
  FROM payments p
  JOIN students s ON s.id = p.student_id
  WHERE DATE(p.payment_date)='{$esc_date}' AND {$where_student}");
$payments_collected_amt = ($q_pay_sum && $q_pay_sum->num_rows) ? (float)$q_pay_sum->fetch_assoc()['amt'] : 0.0;

// Outstanding (all-time) for filtered students — not limited to current date
$q_out = $conn->query("
  SELECT IFNULL(SUM(p.charge - p.paid_amount),0) AS bal
  FROM penalties p
  JOIN students s ON s.id = p.student_id
  WHERE (p.payment_status='unpaid' OR p.payment_status='partial') AND {$where_student}");
$outstanding_amt = ($q_out && $q_out->num_rows) ? (float)$q_out->fetch_assoc()['bal'] : 0.0;

// Violation components bar data (from today's penalties -> violation text)
$vc = ['ID'=>0,'Top'=>0,'Bottom'=>0,'Shoes'=>0];
$q_comp_bar = $conn->query("
  SELECT violation
  FROM penalties p
  JOIN students s ON s.id = p.student_id
  WHERE DATE(p.date_issued)='{$esc_date}' AND {$where_student}");
if ($q_comp_bar) {
  while ($r = $q_comp_bar->fetch_assoc()) {
    $v = strtoupper($r['violation'] ?? '');
    if (strpos($v,'ID') !== false) $vc['ID']++;
    if (strpos($v,'TOP') !== false || strpos($v,'DRESS') !== false || strpos($v,'MALE_DRESS')!==false || strpos($v,'FEMALE_DRESS')!==false) $vc['Top']++;
    if (strpos($v,'BOTTOM') !== false || strpos($v,'PANTS') !== false || strpos($v,'SKIRT') !== false) $vc['Bottom']++;
    if (strpos($v,'SHOES') !== false) $vc['Shoes']++;
  }
}

// Violations (table) for selected date
$violations = $conn->query("
  SELECT s.student_code, s.fullname, s.course, s.year_level, s.section, ul.detected_items, ul.detected_at
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}'
    AND ul.status='incomplete'
    AND {$where_student}
  ORDER BY ul.detected_at DESC, ul.id DESC");

// Top 5 repeat violators (selected date + filters)
$top_repeat = $conn->query("
  SELECT s.id, s.student_code, s.fullname, s.course, s.year_level, s.section, COUNT(*) AS vcount
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE DATE(ul.detected_at)='{$esc_date}' AND ul.status='incomplete' AND {$where_student}
  GROUP BY s.id, s.student_code, s.fullname, s.course, s.year_level, s.section
  ORDER BY vcount DESC, s.fullname ASC
  LIMIT 5
");

include('includes/header.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid">

  <!-- Official Report Header -->
<div class="card shadow-sm mb-3">
  <div class="card-body py-3">
    <div class="text-center">

      <!-- School Logo -->
      <div style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; overflow: hidden;">
        <?php if (!empty($school_logo)): ?>
          <img src="../<?php echo htmlspecialchars($school_logo); ?>" 
               alt="School Logo"
               style="max-width: 100%; max-height: 100%; object-fit: contain;">
        <?php endif; ?>
      </div>

      <!-- System Name -->
      <div style="font-weight:800; font-size:18px; letter-spacing:.5px;">
        <?php echo h(strtoupper($system_name)); ?>
      </div>

      <!-- School Name -->
      <div class="text-muted" style="margin-top:-2px;">
        <?php echo h($school_name); ?>
      </div>

      <!-- Report Title -->
      <div style="font-weight:600; margin-top:6px;">
        Daily Uniform Inspection Report
      </div>

      <!-- Date -->
      <div class="text-muted" style="font-size:12px;">
        Date: <strong><?php echo h($sel_date); ?></strong>
      </div>

    </div>
  </div>
</div>


  <!-- Filters -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      <strong><i class="fa fa-filter me-1"></i> Filter Report</strong>
    </div>
    <div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-sm-6 col-md-3">
          <label class="form-label">Date</label>
          <input type="date" name="date" value="<?php echo h($sel_date); ?>" class="form-control">
        </div>
        <div class="col-sm-6 col-md-3">
          <label class="form-label">Course</label>
          <select name="course" class="form-select">
            <option value="">All Courses</option>
            <?php foreach($COURSES as $c): ?>
              <option value="<?php echo $c; ?>" <?php echo $fcourse===$c?'selected':''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-3">
          <label class="form-label">Year</label>
          <select name="year" class="form-select">
            <option value="">All Years</option>
            <?php foreach($YEARS as $y): ?>
              <option value="<?php echo $y; ?>" <?php echo $fyear===$y?'selected':''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-6 col-md-3">
          <label class="form-label">Section</label>
          <select name="section" class="form-select">
            <option value="">All Sections</option>
            <?php foreach($SECTIONS as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $fsection===$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-primary"><i class="fa fa-filter me-1"></i> Apply Filters</button>
          <a class="btn btn-outline-secondary" href="reports_daily.php"><i class="fa fa-times me-1"></i> Clear Filters</a>
          <a class="btn btn-success"
             href="reports_daily.php?date=<?php echo urlencode($sel_date); ?>&course=<?php echo urlencode($fcourse); ?>&year=<?php echo urlencode($fyear); ?>&section=<?php echo urlencode($fsection); ?>&export=csv">
             <i class="fa fa-download me-1"></i> Export CSV
          </a>
          <a class="btn btn-danger"
             href="reports_daily_pdf.php?date=<?php echo urlencode($sel_date); ?>&course=<?php echo urlencode($fcourse); ?>&year=<?php echo urlencode($fyear); ?>&section=<?php echo urlencode($fsection); ?>">
            <i class="fa fa-file-pdf me-1"></i> Export PDF
          </a>
        </div>
      </form>
    </div>
  </div>
<li>________________________________________________________________________________________________</li><br>
  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Total Scans</div>
          <div class="h4 m-0"><?php echo (int)$total_scans; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Compliant</div>
          <div class="h4 m-0 text-success"><?php echo (int)$total_compliant; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Violations</div>
          <div class="h4 m-0 text-danger"><?php echo (int)$total_violations; ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Compliance Rate</div>
          <div class="h4 m-0">
            <?php
              $rate = ($total_scans>0) ? round(($total_compliant/$total_scans)*100,1) : 0;
              echo $rate . "%";
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Money KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Penalties Issued (<?php echo h($sel_date); ?>)</div>
          <div class="h4 m-0"><?php echo peso($penalties_issued_amt); ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Payments Collected (<?php echo h($sel_date); ?>)</div>
          <div class="h4 m-0"><?php echo peso($payments_collected_amt); ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted">Outstanding (All-time, filtered)</div>
          <div class="h4 m-0"><?php echo peso($outstanding_amt); ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Compliant vs Violations</strong></div>
        <div class="card-body">
          <canvas id="pieCompliance"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Violation Components (from penalties)</strong></div>
        <div class="card-body">
          <canvas id="barComponents"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Repeat Violators -->
  <div class="card mb-4">
    <div class="card-header bg-white"><strong>Top 5 Repeat Violators (<?php echo h($sel_date); ?>)</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm m-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:45%;">Student</th>
              <th>Course / Year / Section</th>
              <th class="text-end" style="width:120px;">Violations</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($top_repeat && $top_repeat->num_rows): while($t=$top_repeat->fetch_assoc()): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo h($t['fullname']); ?></div>
                  <div class="text-muted" style="font-size:12px;"><?php echo h($t['student_code']); ?></div>
                </td>
                <td><?php echo h($t['course'].' / '.$t['year_level'].' / '.$t['section']); ?></td>
                <td class="text-end"><span class="badge bg-danger"><?php echo (int)$t['vcount']; ?></span></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="3" class="text-center text-muted">No repeat violators for this date / filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Violations table -->
  <div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <strong>Violations on <?php echo h($sel_date); ?></strong>
      <small class="text-muted">Filtered by Course/Year/Section</small>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover m-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <th>Course / Year / Section</th>
              <th>Detected Items</th>
              <th style="width:180px;">Detected At</th>
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
                  <div class="fw-semibold"><?php echo h($r['fullname']); ?></div>
                  <div class="text-muted" style="font-size:12px;"><?php echo h($r['student_code']); ?></div>
                </td>
                <td><?php echo h($r['course']." / ".$r['year_level']." / ".$r['section']); ?></td>
                <td><?php echo h($items); ?></td>
                <td><span class="text-muted" style="font-size:12px;"><?php echo h($r['detected_at']); ?></span></td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4" class="text-center text-muted">No violations for this date / filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer text-end">
      <small class="text-muted">Generated by <?php echo h($system_name); ?> — <?php echo h($school_name); ?></small>
    </div>
  </div>

</div>

<script>
(() => {
  const totalCompliant = <?php echo (int)$total_compliant; ?>;
  const totalViol = <?php echo (int)$total_violations; ?>;

  // Pie chart
  const pieCtx = document.getElementById('pieCompliance');
  new Chart(pieCtx, {
    type: 'pie',
    data: {
      labels: ['Compliant','Violations'],
      datasets: [{
        data: [totalCompliant, totalViol]
      }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // Bar chart (components)
  const comp = <?php echo json_encode(array_values($vc)); ?>;
  const barCtx = document.getElementById('barComponents');
  new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: ['ID','Top','Bottom','Shoes'],
      datasets: [{ data: comp }]
    },
    options: {
      plugins: { legend: { display:false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
  });
})();
</script>

<?php include('includes/footer.php'); ?>
