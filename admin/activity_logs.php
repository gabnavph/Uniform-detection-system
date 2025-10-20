<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/activity_logger.php");

// Only superadmin and officers can view activity logs
$admin_role = $_SESSION['role'] ?? 'viewer';
if (!in_array($admin_role, ['super', 'officer'])) {
    header("Location: index.php"); 
    exit();
}

// Log this page access
log_activity($conn, ActivityActions::REPORT_GENERATED, ActivityTargets::SYSTEM, null, 'Activity Logs Page', 'Viewed activity logs page');

// Flash helpers
function set_flash($type,$msg){ $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function get_flash(){ if(!empty($_SESSION['flash'])){ $x=$_SESSION['flash']; unset($_SESSION['flash']); return $x; } return null; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Get unique values for filters
$actions_result = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$available_actions = [];
if ($actions_result) {
    while ($row = $actions_result->fetch_assoc()) {
        $available_actions[] = $row['action'];
    }
}

$targets_result = $conn->query("SELECT DISTINCT target_type FROM activity_logs WHERE target_type IS NOT NULL ORDER BY target_type");
$available_targets = [];
if ($targets_result) {
    while ($row = $targets_result->fetch_assoc()) {
        $available_targets[] = $row['target_type'];
    }
}

$admins_result = $conn->query("SELECT DISTINCT admin_username FROM activity_logs WHERE admin_username IS NOT NULL ORDER BY admin_username");
$available_admins = [];
if ($admins_result) {
    while ($row = $admins_result->fetch_assoc()) {
        $available_admins[] = $row['admin_username'];
    }
}

// Filters
$search = trim($_GET['q'] ?? '');
$filter_action = $_GET['action'] ?? '';
$filter_target = $_GET['target_type'] ?? '';
$filter_admin = $_GET['admin'] ?? '';
$from_date = trim($_GET['from'] ?? '');
$to_date = trim($_GET['to'] ?? '');

// Build WHERE clause
$where = "1";
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where .= " AND (action LIKE '%$s%' OR target_name LIKE '%$s%' OR details LIKE '%$s%' OR admin_username LIKE '%$s%' OR ip_address LIKE '%$s%')";
}
if ($filter_action !== '' && in_array($filter_action, $available_actions)) {
    $action = $conn->real_escape_string($filter_action);
    $where .= " AND action = '$action'";
}
if ($filter_target !== '' && in_array($filter_target, $available_targets)) {
    $target = $conn->real_escape_string($filter_target);
    $where .= " AND target_type = '$target'";
}
if ($filter_admin !== '' && in_array($filter_admin, $available_admins)) {
    $admin = $conn->real_escape_string($filter_admin);
    $where .= " AND admin_username = '$admin'";
}
if ($from_date !== '') {
    $from = $conn->real_escape_string($from_date);
    $where .= " AND DATE(created_at) >= '$from'";
}
if ($to_date !== '') {
    $to = $conn->real_escape_string($to_date);
    $where .= " AND DATE(created_at) <= '$to'";
}

// Export CSV
if (($_GET['export'] ?? '') === 'csv') {
    $filename = "activity_logs_export_" . date("Ymd_His") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $out = fopen("php://output", "w");
    fputcsv($out, ["Date/Time", "Admin User", "Action", "Target Type", "Details", "IP Address"]);
    
    $export_sql = "SELECT created_at, admin_username, action, target_type, details, ip_address 
                   FROM activity_logs 
                   WHERE $where 
                   ORDER BY created_at DESC";
    
    if ($res = $conn->query($export_sql)) {
        while ($r = $res->fetch_assoc()) {
            fputcsv($out, [
                $r['created_at'],
                $r['admin_username'],
                $r['action'],
                $r['target_type'],
                $r['details'],
                $r['ip_address']
            ]);
        }
    }
    fclose($out);
    exit();
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25; // Show more records per page for logs
$offset = ($page - 1) * $limit;

// Get total count
$count_sql = "SELECT COUNT(*) FROM activity_logs WHERE $where";
$total = 0;
if ($count_result = $conn->query($count_sql)) {
    $row = $count_result->fetch_row();
    $total = intval($row[0]);
}
$pages = max(1, ceil($total / $limit));

// Get logs
$sql = "SELECT * FROM activity_logs 
        WHERE $where 
        ORDER BY created_at DESC 
        LIMIT $limit OFFSET $offset";
$logs = $conn->query($sql);

include('includes/header.php');
$flash = get_flash();
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.activity-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: white;
    margin-right: 8px;
}
.icon-login { background: #28a745; }
.icon-logout { background: #6c757d; }
.icon-create { background: #007bff; }
.icon-update { background: #ffc107; color: #000; }
.icon-delete { background: #dc3545; }
.icon-scan { background: #17a2b8; }
.icon-payment { background: #28a745; }
.icon-system { background: #6f42c1; }
.icon-default { background: #6c757d; }

.details-cell {
    max-width: 300px;
    word-wrap: break-word;
}
</style>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="m-0">Activity Logs</h3>
            <div class="text-muted">System activity and audit trail</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" 
               href="?q=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&target_type=<?php echo urlencode($filter_target); ?>&admin=<?php echo urlencode($filter_admin); ?>&from=<?php echo urlencode($from_date); ?>&to=<?php echo urlencode($to_date); ?>&export=csv">
                <i class="fa fa-download me-1"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Total Activities</div>
                            <div class="h4"><?php echo number_format($total); ?></div>
                        </div>
                        <div class="align-self-center">
                            <i class="fa fa-list fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Today's Activities</div>
                            <div class="h4">
                                <?php
                                $today_count = 0;
                                $today_result = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()");
                                if ($today_result) {
                                    $row = $today_result->fetch_row();
                                    $today_count = intval($row[0]);
                                }
                                echo number_format($today_count);
                                ?>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="fa fa-calendar-day fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">Active Admins</div>
                            <div class="h4">
                                <?php
                                $active_admins = 0;
                                $admin_result = $conn->query("SELECT COUNT(DISTINCT admin_username) FROM activity_logs WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                if ($admin_result) {
                                    $row = $admin_result->fetch_row();
                                    $active_admins = intval($row[0]);
                                }
                                echo number_format($active_admins);
                                ?>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="fa fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="small">This Week</div>
                            <div class="h4">
                                <?php
                                $week_count = 0;
                                $week_result = $conn->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                if ($week_result) {
                                    $row = $week_result->fetch_row();
                                    $week_count = intval($row[0]);
                                }
                                echo number_format($week_count);
                                ?>
                            </div>
                        </div>
                        <div class="align-self-center">
                            <i class="fa fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
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
                    <input type="text" name="q" value="<?php echo h($search); ?>" class="form-control" placeholder="Search activities, details, IP...">
                </div>
                <div class="col-md-2">
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php foreach ($available_actions as $action): ?>
                            <option value="<?php echo h($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                <?php echo h($action); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="target_type" class="form-select">
                        <option value="">All Targets</option>
                        <?php foreach ($available_targets as $target): ?>
                            <option value="<?php echo h($target); ?>" <?php echo $filter_target === $target ? 'selected' : ''; ?>>
                                <?php echo h(ucfirst($target)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="admin" class="form-select">
                        <option value="">All Admins</option>
                        <?php foreach ($available_admins as $admin): ?>
                            <option value="<?php echo h($admin); ?>" <?php echo $filter_admin === $admin ? 'selected' : ''; ?>>
                                <?php echo h($admin); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="date" name="from" value="<?php echo h($from_date); ?>" class="form-control" title="From Date">
                </div>
                <div class="col-md-1">
                    <input type="date" name="to" value="<?php echo h($to_date); ?>" class="form-control" title="To Date">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i></button>
                    <a class="btn btn-outline-secondary" href="activity_logs.php" title="Clear filters"><i class="fa fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Logs Table -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="fa fa-list me-1"></i> Activity Logs</strong>
            <small class="text-muted">Showing <?php echo number_format($total); ?> total activities</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm m-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">Date/Time</th>
                            <th style="width: 100px;">Admin</th>
                            <th style="width: 200px;">Action</th>
                            <th style="width: 100px;">Target</th>
                            <th>Details</th>
                            <th style="width: 100px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs && $logs->num_rows): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <?php
                                // Determine icon based on action
                                $icon_class = 'icon-default';
                                $icon = 'fa-circle';
                                
                                if (strpos($log['action'], 'Login') !== false) {
                                    $icon_class = 'icon-login';
                                    $icon = 'fa-sign-in-alt';
                                } elseif (strpos($log['action'], 'Logout') !== false) {
                                    $icon_class = 'icon-logout';
                                    $icon = 'fa-sign-out-alt';
                                } elseif (strpos($log['action'], 'Created') !== false) {
                                    $icon_class = 'icon-create';
                                    $icon = 'fa-plus';
                                } elseif (strpos($log['action'], 'Updated') !== false) {
                                    $icon_class = 'icon-update';
                                    $icon = 'fa-edit';
                                } elseif (strpos($log['action'], 'Deleted') !== false) {
                                    $icon_class = 'icon-delete';
                                    $icon = 'fa-trash';
                                } elseif (strpos($log['action'], 'Scan') !== false || strpos($log['action'], 'Detection') !== false) {
                                    $icon_class = 'icon-scan';
                                    $icon = 'fa-camera';
                                } elseif (strpos($log['action'], 'Payment') !== false || strpos($log['action'], 'Paid') !== false) {
                                    $icon_class = 'icon-payment';
                                    $icon = 'fa-money-bill';
                                } elseif (strpos($log['action'], 'System') !== false || strpos($log['action'], 'Report') !== false) {
                                    $icon_class = 'icon-system';
                                    $icon = 'fa-cog';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                                            <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold" style="font-size: 13px;">
                                            <?php echo h($log['admin_username'] ?: 'System'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="activity-icon <?php echo $icon_class; ?>">
                                                <i class="fa <?php echo $icon; ?>"></i>
                                            </span>
                                            <span style="font-size: 13px;"><?php echo h($log['action']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['target_type']): ?>
                                            <span class="badge bg-secondary" style="font-size: 11px;">
                                                <?php echo h(ucfirst($log['target_type'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="details-cell">
                                        <div class="text-truncate" title="<?php echo h($log['details']); ?>">
                                            <?php echo h($log['details'] ?: '—'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo h($log['ip_address']); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa fa-search fa-2x mb-2 opacity-50"></i><br>
                                    No activity logs found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?q=<?php echo urlencode($search); ?>&action=<?php echo urlencode($filter_action); ?>&target_type=<?php echo urlencode($filter_target); ?>&admin=<?php echo urlencode($filter_admin); ?>&from=<?php echo urlencode($from_date); ?>&to=<?php echo urlencode($to_date); ?>&page=<?php echo $p; ?>">
                            <?php echo $p; ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script>
// SweetAlert flash messages
<?php if ($flash): ?>
Swal.fire({
    icon: '<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>',
    title: '<?php echo $flash['type'] === 'success' ? 'Success' : 'Error'; ?>',
    text: '<?php echo addslashes($flash['msg']); ?>',
    timer: 1800,
    showConfirmButton: false
});
<?php endif; ?>
</script>

<?php include('includes/footer.php'); ?>