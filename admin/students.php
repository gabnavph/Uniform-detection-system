<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/activity_logger.php");
require_once("includes/archive_helper.php");
require_once("includes/alert_helper.php");

/** CONFIG **/
$COURSES = ["BSIT", "BSHM"];
$YEARS   = ["1st Year","2nd Year","3rd Year","4th Year"];
$SECTIONS= ["A","B","C","D"];

// Ensure upload dir exists
$uploadDir = __DIR__ . "/../uploads/students";
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

// Flash helpers
function set_flash($type,$msg){ $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function get_flash(){ if(!empty($_SESSION['flash'])){ $x=$_SESSION['flash']; unset($_SESSION['flash']); return $x; } return null; }
function sanitize_filename($name){ $name=preg_replace('/[^a-zA-Z0-9_\.\-]/','_',$name); return substr($name,0,120); }

/** ---------- ACTIONS: ADD / EDIT / DELETE ---------- **/
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  if ($action==='add') {
    $student_code = trim($_POST['student_code'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $fullname     = trim($_POST['fullname'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $contact      = trim($_POST['contact'] ?? '');
    $course       = $_POST['course'] ?? '';
    $year_level   = $_POST['year_level'] ?? '';
    $section      = $_POST['section'] ?? '';

    if ($student_code===''||$first_name===''||$last_name===''||$fullname===''||$email===''||$course===''||$year_level===''||$section==='') {
      set_flash('error','Please complete all required fields.');
      header("Location: students.php"); exit();
    }
    if (!in_array($course,$COURSES) || !in_array($year_level,$YEARS) || !in_array($section,$SECTIONS)) {
      set_flash('error','Invalid Course/Year/Section selection.');
      header("Location: students.php"); exit();
    }

    // Duplicate student_code
    $sc = $conn->real_escape_string($student_code);
    $dup = $conn->query("SELECT id FROM students WHERE student_code='$sc' LIMIT 1");
    if ($dup && $dup->num_rows) { set_flash('error','Student Code already exists.'); header("Location: students.php"); exit(); }

    // Optional photo
    $photoRel = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error']===UPLOAD_ERR_OK) {
      $ext=strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,['jpg','jpeg','png'])) { set_flash('error','Photo must be JPG or PNG.'); header("Location: students.php"); exit(); }
      $newName='stu_'.time().'_'.sanitize_filename($_FILES['photo']['name']);
      $dest=$uploadDir.'/'.$newName;
      if (!move_uploaded_file($_FILES['photo']['tmp_name'],$dest)) { set_flash('error','Failed to upload photo.'); header("Location: students.php"); exit(); }
      $photoRel="uploads/students/".$newName;
    }

    $fn=$conn->real_escape_string($fullname);
    $fname=$conn->real_escape_string($first_name);
    $mname=$conn->real_escape_string($middle_name);
    $lname=$conn->real_escape_string($last_name);
    $em=$conn->real_escape_string($email);
    $ct=$conn->real_escape_string($contact);
    $co=$conn->real_escape_string($course);
    $yl=$conn->real_escape_string($year_level);
    $sec=$conn->real_escape_string($section);
    $ph=$photoRel?("'".$conn->real_escape_string($photoRel)."'"):"NULL";

    $sql="INSERT INTO students (student_code,first_name,middle_name,last_name,fullname,email,contact,course,year_level,section,photo,date_created)
          VALUES('$sc','$fname','$mname','$lname','$fn','$em','$ct','$co','$yl','$sec',$ph,NOW())";
    if ($conn->query($sql)) {
      $student_id = $conn->insert_id;
      log_activity($conn, ActivityActions::STUDENT_CREATED, ActivityTargets::STUDENT, $student_id, $fullname, "Student created: $student_code - $fullname ($course $year_level $section)");
      set_success_alert('created', 'student', "$student_code - $fullname");
    } else {
      set_error_alert('create', 'student', $conn->error);
    }

    header("Location: students.php"); exit();
  }

  if ($action==='edit') {
    $id           = intval($_POST['id'] ?? 0);
    $student_code = trim($_POST['student_code'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $fullname     = trim($_POST['fullname'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $contact      = trim($_POST['contact'] ?? '');
    $course       = $_POST['course'] ?? '';
    $year_level   = $_POST['year_level'] ?? '';
    $section      = $_POST['section'] ?? '';
    $old_photo    = trim($_POST['old_photo'] ?? '');

    if ($id<=0 || $student_code===''||$first_name===''||$last_name===''||$fullname===''||$email===''||$course===''||$year_level===''||$section==='') {
      set_flash('error','Invalid form data.'); header("Location: students.php"); exit();
    }
    if (!in_array($course,$COURSES) || !in_array($year_level,$YEARS) || !in_array($section,$SECTIONS)) {
      set_flash('error','Invalid Course/Year/Section selection.'); header("Location: students.php"); exit();
    }

    // Duplicate code check excluding current
    $sc=$conn->real_escape_string($student_code);
    $dup=$conn->query("SELECT id FROM students WHERE student_code='$sc' AND id<>$id LIMIT 1");
    if ($dup && $dup->num_rows) { set_flash('error','Another student with the same Student Code exists.'); header("Location: students.php"); exit(); }

    // Optional new photo
    $photoSQL="";
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error']===UPLOAD_ERR_OK) {
      $ext=strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext,['jpg','jpeg','png'])) { set_flash('error','Photo must be JPG or PNG.'); header("Location: students.php"); exit(); }
      $newName='stu_'.time().'_'.sanitize_filename($_FILES['photo']['name']);
      $dest=$uploadDir.'/'.$newName;
      if (!move_uploaded_file($_FILES['photo']['tmp_name'],$dest)) { set_flash('error','Failed to upload new photo.'); header("Location: students.php"); exit(); }
      $newRel="uploads/students/".$newName;
      if ($old_photo && file_exists(__DIR__."/../".$old_photo)) { @unlink(__DIR__."/../".$old_photo); }
      $photoSQL=", photo='".$conn->real_escape_string($newRel)."'";
    }

    $fn=$conn->real_escape_string($fullname);
    $fname=$conn->real_escape_string($first_name);
    $mname=$conn->real_escape_string($middle_name);
    $lname=$conn->real_escape_string($last_name);
    $em=$conn->real_escape_string($email);
    $ct=$conn->real_escape_string($contact);
    $co=$conn->real_escape_string($course);
    $yl=$conn->real_escape_string($year_level);
    $sec=$conn->real_escape_string($section);

    $sql="UPDATE students SET
            student_code='$sc',
            first_name='$fname',
            middle_name='$mname',
            last_name='$lname',
            fullname='$fn',
            email='$em',
            contact='$ct',
            course='$co',
            year_level='$yl',
            section='$sec'
            $photoSQL
          WHERE id=$id LIMIT 1";
    if ($conn->query($sql)) {
      log_activity($conn, ActivityActions::STUDENT_UPDATED, ActivityTargets::STUDENT, $id, $fullname, "Student updated: $student_code - $fullname ($course $year_level $section)");
      set_success_alert('updated', 'student', "$student_code - $fullname");
    } else {
      set_error_alert('update', 'student', $conn->error);
    }

    header("Location: students.php"); exit();
  }

  if ($action==='archive') {
    $id=intval($_POST['id'] ?? 0);
    if ($id>0) {
      // Get student info before archiving
      $res=$conn->query("SELECT student_code, fullname FROM students WHERE id=$id AND archived_at IS NULL");
      $student_data = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
      
      if ($student_data && archive_record($conn, 'students', $id)) {
        log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, $id, $student_data['fullname'], "Student archived: {$student_data['student_code']} - {$student_data['fullname']}");
        set_success_alert('archived', 'student', "{$student_data['student_code']} - {$student_data['fullname']}");
      } else {
        set_error_alert('archive', 'student', 'Student not found or already archived');
      }
    } else {
      set_error_alert('archive', 'student', 'Invalid student ID');
    }
    header("Location: students.php"); exit();
  }

  if ($action==='restore') {
    $id=intval($_POST['id'] ?? 0);
    if ($id>0) {
      // Get student info
      $res=$conn->query("SELECT student_code, fullname FROM students WHERE id=$id AND archived_at IS NOT NULL");
      $student_data = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
      
      if ($student_data && restore_record($conn, 'students', $id)) {
        log_activity($conn, ActivityActions::STUDENT_CREATED, ActivityTargets::STUDENT, $id, $student_data['fullname'], "Student restored: {$student_data['student_code']} - {$student_data['fullname']}");
        set_success_alert('restored', 'student', "{$student_data['student_code']} - {$student_data['fullname']}");
      } else {
        set_error_alert('restore', 'student', 'Student not found or not archived');
      }
    } else {
      set_error_alert('restore', 'student', 'Invalid student ID');
    }
    header("Location: students.php"); exit();
  }

  if ($action==='delete_permanent') {
    $id=intval($_POST['id'] ?? 0);
    if ($id>0) {
      // Get student info before permanent deletion
      $res=$conn->query("SELECT student_code, fullname, photo FROM students WHERE id=$id AND archived_at IS NOT NULL");
      $student_data = ($res && $res->num_rows) ? $res->fetch_assoc() : null;
      
      if ($student_data && permanently_delete_record($conn, 'students', $id)) {
        log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, $id, $student_data['fullname'], "Student permanently deleted: {$student_data['student_code']} - {$student_data['fullname']}");
        // Delete photo file
        if ($student_data['photo'] && file_exists(__DIR__."/../".$student_data['photo'])) { 
          @unlink(__DIR__."/../".$student_data['photo']); 
        }
        set_success_alert('deleted', 'student', "{$student_data['student_code']} - {$student_data['fullname']}");
      } else {
        set_error_alert('delete', 'student', 'Student not found or not archived');
      }
    } else {
      set_error_alert('delete', 'student', 'Invalid student ID');
    }
    header("Location: students.php"); exit();
  }
}

/** ---------- LIST + FILTERS + CSV EXPORT ---------- **/
$search  = trim($_GET['q'] ?? '');
$fcourse = $_GET['course'] ?? '';
$fsection= $_GET['section'] ?? '';
$fyear   = $_GET['year'] ?? '';
$export  = $_GET['export'] ?? ''; // csv or empty

$where = "archived_at IS NULL";
if ($search!=='') {
  $s=$conn->real_escape_string($search);
  $where.=" AND (student_code LIKE '%$s%' OR fullname LIKE '%$s%' OR email LIKE '%$s%' OR contact LIKE '%$s%')";
}
if ($fcourse!=='' && in_array($fcourse,$COURSES)) {
  $co=$conn->real_escape_string($fcourse); $where.=" AND course='$co'";
}
if ($fsection!=='' && in_array($fsection,$SECTIONS)) {
  $sec=$conn->real_escape_string($fsection); $where.=" AND section='$sec'";
}
if ($fyear!=='' && in_array($fyear,$YEARS)) {
  $yl=$conn->real_escape_string($fyear); $where.=" AND year_level='$yl'";
}

$baseSql = "SELECT id,student_code,first_name,middle_name,last_name,fullname,email,contact,course,year_level,section,photo,date_created FROM students WHERE $where";

/* CSV EXPORT (filtered) */
if ($export==='csv') {
  $filename = "students_export_".date("Ymd_His").".csv";
  header("Content-Type: text/csv; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"$filename\"");
  $out = fopen("php://output", "w");
  fputcsv($out, ["Student Code","First Name","Middle Name","Last Name","Full Name","Email","Contact","Course","Year Level","Section","Photo Path","Date Created"]);
  if ($res = $conn->query($baseSql." ORDER BY date_created DESC, id DESC")) {
    while ($r=$res->fetch_assoc()) {
      fputcsv($out, [
        $r['student_code'],
        $r['first_name'],
        $r['middle_name'],
        $r['last_name'],
        $r['fullname'],
        $r['email'],
        $r['contact'],
        $r['course'],
        $r['year_level'],
        $r['section'],
        $r['photo'],
        $r['date_created']
      ]);
    }
  }
  fclose($out);
  exit();
}

/* Normal list with pagination */
$page = max(1, intval($_GET['page'] ?? 1));
$limit= 10; $offset = ($page-1)*$limit;

$total=0; if ($r=$conn->query("SELECT COUNT(*) FROM students WHERE $where")) { $row=$r->fetch_row(); $total=intval($row[0]); }
$pages=max(1, ceil($total/$limit));
$list=$conn->query($baseSql." ORDER BY date_created DESC, id DESC LIMIT $limit OFFSET $offset");

include('includes/header.php');
$flash=get_flash();
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">Students</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" 
         href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&export=csv">
        <i class="fa fa-download me-1"></i> Export CSV
      </a>
      <a class="btn btn-outline-warning" href="settings.php#tabRecycleBin">
        <i class="fas fa-recycle me-1"></i> Recycle Bin
        <?php 
        $archived_count = count_archived_records($conn, 'students');
        if ($archived_count > 0): ?>
          <span class="badge bg-warning text-dark ms-1"><?php echo $archived_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="import_students.php" class="btn btn-outline-primary">
        <i class="fas fa-upload me-1"></i> Import Students
      </a>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa fa-plus me-1"></i> Add Student
      </button>
    </div>
  </div>

  <!-- Filters -->
  <div class="card shadow-sm mb-3">
    <div class="card-header">
      <strong><i class="fa fa-filter me-1"></i> Filters</strong>
    </div>
    <div class="card-body">
      <form class="row g-2" method="get">
        <div class="col-md-4">
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search name, code, email, contact...">
        </div>
        <div class="col-md-2">
          <select name="course" id="filter_course" class="form-select">
            <option value="">All Courses</option>
            <?php foreach($COURSES as $c): ?>
              <option value="<?php echo $c; ?>" <?php echo $fcourse===$c?'selected':''; ?>><?php echo $c; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="section" id="filter_section" class="form-select">
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
          <button class="btn btn-primary w-100" type="submit"><i class="fa fa-search me-1"></i> Search</button>
        </div>
        <div class="col-md-12">
          <a class="btn btn-outline-secondary btn-sm" href="students.php"><i class="fa fa-times me-1"></i> Clear Filters</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px;">Photo</th>
          <th>Student Code</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Contact</th>
          <th>Course</th>
          <th>Year</th>
          <th>Section</th>
          <th style="width:150px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list && $list->num_rows): while($row=$list->fetch_assoc()): ?>
          <tr>
            <td>
              <?php if (!empty($row['photo'])): ?>
                <img src="../<?php echo htmlspecialchars($row['photo']); ?>" style="width:44px;height:44px;object-fit:cover;border-radius:50%;">
              <?php else: ?>
                <span class="text-muted" style="font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['student_code']); ?></td>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['contact']); ?></td>
            <td><?php echo htmlspecialchars($row['course']); ?></td>
            <td><?php echo htmlspecialchars($row['year_level']); ?></td>
            <td><?php echo htmlspecialchars($row['section']); ?></td>
            <td>
              <button class="btn btn-sm btn-outline-primary me-1 btn-edit"
                data-id="<?php echo $row['id']; ?>"
                data-student_code="<?php echo htmlspecialchars($row['student_code'],ENT_QUOTES); ?>"
                data-first_name="<?php echo htmlspecialchars($row['first_name'],ENT_QUOTES); ?>"
                data-middle_name="<?php echo htmlspecialchars($row['middle_name'],ENT_QUOTES); ?>"
                data-last_name="<?php echo htmlspecialchars($row['last_name'],ENT_QUOTES); ?>"
                data-fullname="<?php echo htmlspecialchars($row['fullname'],ENT_QUOTES); ?>"
                data-email="<?php echo htmlspecialchars($row['email'],ENT_QUOTES); ?>"
                data-contact="<?php echo htmlspecialchars($row['contact'],ENT_QUOTES); ?>"
                data-course="<?php echo htmlspecialchars($row['course'],ENT_QUOTES); ?>"
                data-year_level="<?php echo htmlspecialchars($row['year_level'],ENT_QUOTES); ?>"
                data-section="<?php echo htmlspecialchars($row['section'],ENT_QUOTES); ?>"
                data-photo="<?php echo htmlspecialchars($row['photo'],ENT_QUOTES); ?>"
                data-bs-toggle="modal" data-bs-target="#editModal">
                <i class="fa fa-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-warning btn-archive" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['student_code'] . ' - ' . $row['fullname'], ENT_QUOTES); ?>">
                <i class="fas fa-archive"></i>
              </button>
            </td>
          </tr>
        <?php endwhile; else: ?>
          <tr><td colspan="9" class="text-center text-muted">No students found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages>1): ?>
    <nav><ul class="pagination">
      <?php for($p=1;$p<=$pages;$p++): ?>
        <li class="page-item <?php echo $p==$page?'active':''; ?>">
          <a class="page-link" href="?q=<?php echo urlencode($search); ?>&course=<?php echo urlencode($fcourse); ?>&section=<?php echo urlencode($fsection); ?>&year=<?php echo urlencode($fyear); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title">Add Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Student Code (Barcode)</label>
          <input type="text" name="student_code" class="form-control" required>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" id="add_first_name" class="form-control" required>
          </div>
          <div class="col-4">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middle_name" id="add_middle_name" class="form-control" placeholder="Optional">
          </div>
          <div class="col-4">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" id="add_last_name" class="form-control" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Full Name (Auto-generated)</label>
          <input type="text" name="fullname" id="add_fullname" class="form-control" readonly style="background-color: #f8f9fa;">
          <small class="form-text text-muted">This field is automatically filled based on the name fields above.</small>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact" class="form-control">
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Course</label>
            <select name="course" class="form-select" required>
              <option value="">Select course</option>
              <?php foreach($COURSES as $c): ?>
                <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Year Level</label>
            <select name="year_level" class="form-select" required>
              <?php foreach($YEARS as $y): ?>
                <option><?php echo $y; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Section</label>
          <select name="section" class="form-select" required>
            <option value="">Select section</option>
            <?php foreach($SECTIONS as $sec): ?>
              <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2">
          <label class="form-label">Profile Photo (optional)</label>
          <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <form class="modal-content" method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" name="old_photo" id="edit_old_photo">
      <div class="modal-header">
        <h5 class="modal-title">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Student Code (Barcode)</label>
          <input type="text" name="student_code" id="edit_student_code" class="form-control" required>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-4">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
          </div>
          <div class="col-4">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middle_name" id="edit_middle_name" class="form-control" placeholder="Optional">
          </div>
          <div class="col-4">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Full Name (Auto-generated)</label>
          <input type="text" name="fullname" id="edit_fullname" class="form-control" readonly style="background-color: #f8f9fa;">
          <small class="form-text text-muted">This field is automatically filled based on the name fields above.</small>
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="edit_email" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact" id="edit_contact" class="form-control">
        </div>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Course</label>
            <select name="course" id="edit_course" class="form-select" required>
              <?php foreach($COURSES as $c): ?>
                <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Year Level</label>
            <select name="year_level" id="edit_year_level" class="form-select" required>
              <?php foreach($YEARS as $y): ?>
                <option><?php echo $y; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="mt-2">
          <label class="form-label">Section</label>
          <select name="section" id="edit_section" class="form-select" required>
            <?php foreach($SECTIONS as $sec): ?>
              <option value="<?php echo $sec; ?>"><?php echo $sec; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt-2">
          <label class="form-label">Replace Photo (optional)</label>
          <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png">
          <div class="form-text">Leave empty to keep current photo.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Update Student</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden archive form -->
<form id="archiveForm" method="post" class="d-none">
  <input type="hidden" name="action" value="archive">
  <input type="hidden" name="id" id="archive_id">
</form>

<!-- Hidden restore form -->
<form id="restoreForm" method="post" class="d-none">
  <input type="hidden" name="action" value="restore">
  <input type="hidden" name="id" id="restore_id">
</form>

<!-- Hidden permanent delete form -->
<form id="permanentDeleteForm" method="post" class="d-none">
  <input type="hidden" name="action" value="delete_permanent">
  <input type="hidden" name="id" id="permanent_delete_id">
</form>

<?php render_alert_script(); ?>

<script>
// Auto-generate full name function
function generateFullName(first, middle, last) {
  let parts = [first];
  if (middle && middle.trim() !== '') {
    // Use only the first letter of middle name as initial
    parts.push(middle.trim().charAt(0).toUpperCase() + '.');
  }
  if (last) {
    parts.push(last);
  }
  return parts.join(' ').trim();
}

// Add modal name field listeners
function setupNameListeners(prefix) {
  const firstField = document.getElementById(prefix + '_first_name');
  const middleField = document.getElementById(prefix + '_middle_name');
  const lastField = document.getElementById(prefix + '_last_name');
  const fullField = document.getElementById(prefix + '_fullname');
  
  function updateFullName() {
    const fullName = generateFullName(
      firstField.value.trim(),
      middleField.value.trim(), 
      lastField.value.trim()
    );
    fullField.value = fullName;
  }
  
  firstField.addEventListener('input', updateFullName);
  middleField.addEventListener('input', updateFullName);
  lastField.addEventListener('input', updateFullName);
}

// Setup listeners for both modals
document.addEventListener('DOMContentLoaded', function() {
  setupNameListeners('add');
  setupNameListeners('edit');
});

// Fill edit modal from row data
document.querySelectorAll('.btn-edit').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.getElementById('edit_id').value = btn.dataset.id;
    document.getElementById('edit_student_code').value = btn.dataset.student_code;
    document.getElementById('edit_first_name').value = btn.dataset.first_name || '';
    document.getElementById('edit_middle_name').value = btn.dataset.middle_name || '';
    document.getElementById('edit_last_name').value = btn.dataset.last_name || '';
    document.getElementById('edit_fullname').value = btn.dataset.fullname;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_contact').value = btn.dataset.contact || '';
    document.getElementById('edit_course').value = btn.dataset.course;
    document.getElementById('edit_year_level').value = btn.dataset.year_level;
    document.getElementById('edit_section').value = btn.dataset.section;
    document.getElementById('edit_old_photo').value = btn.dataset.photo || '';
  });
});

// Archive confirm
document.querySelectorAll('.btn-archive').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    Swal.fire({
      title: 'Archive this student?',
      text: `${name} will be moved to the recycle bin and can be restored later.`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ffc107',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, archive'
    }).then(res=>{
      if (res.isConfirmed) {
        document.getElementById('archive_id').value = id;
        document.getElementById('archiveForm').submit();
      }
    });
  });
});
</script>

<?php include('includes/footer.php'); ?>
