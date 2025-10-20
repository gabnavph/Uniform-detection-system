<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");

// ===== Summary Cards =====
function single_val($conn, $sql) {
  $res = $conn->query($sql);
  if ($res && ($row = $res->fetch_row())) return (int)$row[0];
  return 0;
}

// Totals
$total_students   = single_val($conn, "SELECT COUNT(*) FROM students");
$total_violations = single_val($conn, "SELECT COUNT(*) FROM penalties");
$paid_penalties   = single_val($conn, "SELECT COUNT(*) FROM penalties WHERE payment_status='paid'");
$unpaid_penalties = single_val($conn, "SELECT COUNT(*) FROM penalties WHERE payment_status='unpaid'");

// Compliance (overall, based on uniform_logs)
$total_logs     = single_val($conn, "SELECT COUNT(*) FROM uniform_logs");
$complete_logs  = single_val($conn, "SELECT COUNT(*) FROM uniform_logs WHERE status='complete'");
$compliance_pct = $total_logs > 0 ? round(($complete_logs / $total_logs) * 100) : 100;

// ===== Monthly Violations (current year) =====
$month_counts = array_fill(1, 12, 0);
$sql_month = "
  SELECT MONTH(detected_at) AS m, SUM(CASE WHEN status='incomplete' THEN 1 ELSE 0 END) AS cnt
  FROM uniform_logs
  WHERE YEAR(detected_at) = YEAR(CURDATE())
  GROUP BY MONTH(detected_at)
  ORDER BY MONTH(detected_at)";
if ($res = $conn->query($sql_month)) {
  while ($r = $res->fetch_assoc()) {
    $m = (int)$r['m'];
    $month_counts[$m] = (int)$r['cnt'];
  }
}

// ===== Violations by Course (current year) =====
$course_viols = ['BSIT'=>0, 'BSHM'=>0];
$sql_course = "
  SELECT s.course, COUNT(*) AS cnt
  FROM uniform_logs ul
  JOIN students s ON s.id = ul.student_id
  WHERE ul.status='incomplete' AND YEAR(ul.detected_at) = YEAR(CURDATE())
  GROUP BY s.course";
if ($res = $conn->query($sql_course)) {
  while ($r = $res->fetch_assoc()) {
    $c = strtoupper(trim($r['course']));
    if (isset($course_viols[$c])) $course_viols[$c] = (int)$r['cnt'];
  }
}

// Prepare JS data
$month_labels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
$month_data   = array_values($month_counts);
$course_labels = array_keys($course_viols);
$course_data   = array_values($course_viols);

include('includes/header.php');
?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
  .card-metric { border: 0; border-radius: 14px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
  .metric-title { font-size: 13px; color: #6c757d; letter-spacing:.3px; text-transform: uppercase; }
  .metric-value { font-size: 28px; font-weight: 700; }
  .bg-maroon { background: #7B1113; color:#fff; }
</style>

<div class="container-fluid">
  <!-- Greeting -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="m-0">Dashboard</h3>
      <div class="text-muted">Uniform Monitoring System — Overview & Insights</div>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card card-metric">
        <div class="card-body">
          <div class="metric-title">Total Students</div>
          <div class="metric-value"><?php echo number_format($total_students); ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-metric">
        <div class="card-body">
          <div class="metric-title">Total Violations</div>
          <div class="metric-value"><?php echo number_format($total_violations); ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-metric">
        <div class="card-body">
          <div class="metric-title">Paid / Unpaid</div>
          <div class="metric-value">
            <span class="text-success"><?php echo number_format($paid_penalties); ?></span>
            /
            <span class="text-danger"><?php echo number_format($unpaid_penalties); ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card card-metric bg-maroon">
        <div class="card-body">
          <div class="metric-title text-white-50">Compliance Rate</div>
          <div class="metric-value"><?php echo $compliance_pct; ?>%</div>
          <div class="small text-white-50">Based on all uniform checks</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <strong>Monthly Violations (<?php echo date('Y'); ?>)</strong>
        </div>
        <div class="card-body">
          <canvas id="violationsMonthly" height="130"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <strong>Violations by Course (<?php echo date('Y'); ?>)</strong>
        </div>
        <div class="card-body">
          <canvas id="violationsByCourse" height="130"></canvas>
          <div class="small text-muted mt-2">Shows incomplete uniform counts per course.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Links -->
  <div class="row g-3 mt-3">
    <div class="col-md-4">
      <a href="/scan_uniform.php" class="btn btn-primary w-100"><i class="fa fa-barcode me-1"></i> Scan & Detect</a>
    </div>
    <div class="col-md-4">
      <a href="students.php" class="btn btn-outline-secondary w-100"><i class="fa fa-users me-1"></i> Manage Students</a>
    </div>
    <div class="col-md-4">
      <a href="penalties.php" class="btn btn-outline-secondary w-100"><i class="fa fa-file-text-o me-1"></i> View Penalties</a>
    </div>
  </div>
</div>

<script>
// Data from PHP
const monthLabels = <?php echo json_encode($month_labels); ?>;
const monthData   = <?php echo json_encode($month_data, JSON_NUMERIC_CHECK); ?>;
const courseLabels= <?php echo json_encode($course_labels); ?>;
const courseData  = <?php echo json_encode($course_data, JSON_NUMERIC_CHECK); ?>;

// Monthly Violations (line chart)
(() => {
  const ctx = document.getElementById('violationsMonthly').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: monthLabels,
      datasets: [{
        label: 'Incomplete Uniforms',
        data: monthData,
        tension: 0.3,
        fill: true
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: true }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
})();

// Violations by Course (bar chart)
(() => {
  const ctx = document.getElementById('violationsByCourse').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: courseLabels,
      datasets: [{
        label: 'Violations',
        data: courseData
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
})();
</script>

<?php include('includes/footer.php'); ?>
