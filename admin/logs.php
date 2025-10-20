<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");

// Config (match Students/Penalties pages)
$COURSES  = ["BSIT","BSHM"];
$YEARS    = ["1st Year","2nd Year","3rd Year","4th Year"];
$SECTIONS = ["A","B","C","D"];

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Filters / Search ----------
$search  = trim($_GET['q'] ?? '');
$fcourse = $_GET['course'] ?? '';
$fsection= $_GET['section'] ?? '';
$fyear   = $_GET['year'] ?? '';
$fstatus = $_GET['status'] ?? ''; // complete / incomplete
$from    = trim($_GET['from'] ?? ''); // YYYY-MM-DD
$to      = trim($_GET['to'] ?? '');   // YYYY-MM-DD

$where = "1";
if ($search !== '') {
  $s = $conn->real_escape_string($search);
  $where .= " AND (s.fullname LIKE '%$s%' OR s.student_code LIKE '%$s%' OR ul.detected_items LIKE '%$s%')";
}
if ($fcourse !== '' && in_array($fcourse,$COURSES)) {
  $co = $conn->real_escape_string($fcourse);
  $where .= " AND s.course='$co'";
}
if ($fsection !== '' && in_array($fsection,$SECTIONS)) {
  $sec = $conn->real_escape_string($fsection);
  $where .= " AND s.section='$sec'";
}
if ($fyear !== '' && in_array($fyear,$YEARS)) {
  $yl = $conn->real_escape_string($fyear);
  $where .= " AND s.year_level='$yl'";
}
if ($fstatus !== '' && in_array($fstatus, ['complete','incomplete'])) {
  $st = $conn->real_escape_string($fstatus);
  $where .= " AND ul.status='$st'";
}
if ($from !== '') {
  $f = $conn->real_escape_string($from);
  $where .= " AND DATE(ul.detected_at) >= '$f'";
}
if ($to !== '') {
  $t = $conn->real_escape_string($to);
  $where .= " AND DATE(ul.detected_at) <= '$t'";
}

// ---------- Export CSV (filtered) ----------
if (($_GET['export'] ?? '') === 'csv') {
  $filename = "uniform_logs_export_".date("Ymd_His").".csv";
  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out = fopen("php://output", "w");
  fputcsv($out, ["Student Code","Full Name","Course","Year","Section","Detected Items","Status","Detected At"]);
  $sql = "SELECT ul.*, s.student_code, s.fullname, s.course, s.year_level, s.section
          FROM uniform_logs ul
          JOIN students s ON s.id = ul.student_id
          WHERE $where
          ORDER BY ul.detected_at DESC, ul.id DESC";
  if ($res = $conn->query($sql)) {
    while ($r = $res->fetch_assoc()) {
      $itemsStr = $r['detected_items'];
      $try = json_decode($itemsStr, true);
      if (is_array($try)) $itemsStr = implode(", ", $try);
      fputcsv($out, [
        $r['student_code'],
        $r['fullname'],
        $r['course'],
        $r['year_level'],
        $r['section'],
        $itemsStr,
        strtoupper($r['status']),
        $r['detected_at']
      ]);
    }
  }
  fclose($out);
  exit();
}

// ---------- Pagination + Fetch ----------
$page  = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*)
             FROM uniform_logs ul
             JOIN students s ON s.id = ul.student_id
             WHERE $where";
$total = 0;
if ($rc = $conn->query($sqlCount)) { $row = $rc->fetch_row(); $total = intval($row[0]); }
$pages = max(1, ceil($total / $limit));

$sqlList = "SELECT ul.*, s.student_code, s.fullname, s.course, s.year_level, s.section
            FROM uniform_logs ul
            JOIN students s ON s.id = ul.student_id
            WHERE $where
            ORDER BY ul.detected_at DESC, ul.id DESC
            LIMIT $limit OFFSET $offset";
$list = $conn->query($sqlList);

include('includes/header.php');
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Uniform Activity Logs</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&status=<?php echo urlencode($fstatus); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&export=csv">
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
          <input type="text" name="q" value="<?php echo h($search); ?>" class="form-control" placeholder="Search name, code, items...">
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
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="complete"   <?php echo $fstatus==='complete'?'selected':''; ?>>Complete</option>
            <option value="incomplete" <?php echo $fstatus==='incomplete'?'selected':''; ?>>Incomplete</option>
          </select>
        </div>
        <div class="col-md-1">
          <input type="date" name="from" value="<?php echo h($from); ?>" class="form-control" title="From Date">
        </div>
        <div class="col-md-1">
          <input type="date" name="to" value="<?php echo h($to); ?>" class="form-control" title="To Date">
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100" type="submit"><i class="fa fa-search"></i></button>
        </div>
        <div class="col-md-12">
          <a class="btn btn-outline-secondary btn-sm" href="logs.php"><i class="fa fa-times me-1"></i> Clear Filters</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Student</th>
          <th>Course / Year / Section</th>
          <th>Detected Items</th>
          <th>Status</th>
          <th style="width:160px;">Detected At</th>
          <th style="width:110px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list && $list->num_rows): ?>
          <?php while($r = $list->fetch_assoc()): ?>
            <?php
              $itemsRaw = $r['detected_items'];
              $itemsPretty = $itemsRaw;
              $try = json_decode($itemsRaw, true);
              if (is_array($try)) $itemsPretty = implode(", ", $try);
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?php echo h($r['fullname']); ?></div>
                <div class="text-muted" style="font-size:12px;"><?php echo h($r['student_code']); ?></div>
              </td>
              <td><?php echo h($r['course']." / ".$r['year_level']." / ".$r['section']); ?></td>
              <td><?php echo $itemsPretty !== '' ? h($itemsPretty) : '<span class="text-muted">—</span>'; ?></td>
              <td>
                <?php if ($r['status']==='complete'): ?>
                  <span class="badge bg-success">Complete</span>
                <?php else: ?>
                  <span class="badge bg-danger">Incomplete</span>
                <?php endif; ?>
              </td>
              <td><span class="text-muted" style="font-size:12px;"><?php echo h($r['detected_at']); ?></span></td>
              <td>
                <button class="btn btn-sm btn-outline-primary btn-view"
                  data-student="<?php echo h($r['fullname']); ?>"
                  data-code="<?php echo h($r['student_code']); ?>"
                  data-cys="<?php echo h($r['course']." / ".$r['year_level']." / ".$r['section']); ?>"
                  data-status="<?php echo h($r['status']); ?>"
                  data-when="<?php echo h($r['detected_at']); ?>"
                  data-items-pretty="<?php echo h($itemsPretty); ?>"
                  data-items-raw="<?php echo h($itemsRaw); ?>">
                  <i class="fa fa-eye"></i> View
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">No logs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav><ul class="pagination">
      <?php for($p=1; $p<=$pages; $p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
          <a class="page-link"
             href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&status=<?php echo urlencode($fstatus); ?>&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>&page=<?php echo $p; ?>">
            <?php echo $p; ?>
          </a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Log Details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>Student:</strong> <span id="md_student"></span> (<span id="md_code"></span>)</div>
        <div class="mb-2"><strong>Course / Year / Section:</strong> <span id="md_cys"></span></div>
        <div class="mb-2"><strong>Status:</strong> <span id="md_status_badge"></span></div>
        <div class="mb-2"><strong>Detected Items:</strong> <span id="md_items_pretty"></span></div>
        <div class="mb-2"><strong>Detected At:</strong> <span id="md_when"></span></div>
        <hr>
        <details>
          <summary><strong>Raw JSON</strong> (click to expand)</summary>
          <pre id="md_items_raw" class="mt-2" style="background:#f8f9fa;border:1px solid #eee;padding:10px;border-radius:6px;white-space:pre-wrap;word-break:break-word;"></pre>
        </details>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>



<script>
(function(){
  const modalEl = document.getElementById('logDetailsModal');
  const md_student = document.getElementById('md_student');
  const md_code    = document.getElementById('md_code');
  const md_cys     = document.getElementById('md_cys');
  const md_status  = document.getElementById('md_status_badge');
  const md_items_p = document.getElementById('md_items_pretty');
  const md_items_r = document.getElementById('md_items_raw');
  const md_when    = document.getElementById('md_when');

  document.querySelectorAll('.btn-view').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const st  = btn.getAttribute('data-student') || '';
      const sc  = btn.getAttribute('data-code') || '';
      const cys = btn.getAttribute('data-cys') || '';
      const status = btn.getAttribute('data-status') || '';
      const when = btn.getAttribute('data-when') || '';
      const itemsPretty = btn.getAttribute('data-items-pretty') || '';
      const itemsRaw    = btn.getAttribute('data-items-raw') || '';

      md_student.textContent = st;
      md_code.textContent    = sc;
      md_cys.textContent     = cys;
      md_when.textContent    = when;

      if (status === 'complete') {
        md_status.innerHTML = '<span class="badge bg-success">Complete</span>';
      } else {
        md_status.innerHTML = '<span class="badge bg-danger">Incomplete</span>';
      }

      // Pretty items
      md_items_p.textContent = itemsPretty || '—';

      // Raw JSON (format nicely if valid JSON)
      try {
        const parsed = JSON.parse(itemsRaw);
        md_items_r.textContent = JSON.stringify(parsed, null, 2);
      } catch(e) {
        md_items_r.textContent = itemsRaw || '—';
      }

      const bsModal = new bootstrap.Modal(modalEl);
      bsModal.show();
    });
  });
})();
</script>

<?php include('includes/footer.php'); ?>
