<?php
// =====================================
// /admin/settings.php  (Super Admin Only)
// =====================================
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

require_once("../db.php");
require_once("includes/settings_helper.php");
require_once("includes/alert_helper.php");

// Strict access: Super Admin only
if (!is_super_admin($conn)) {
  http_response_code(403);
  echo "<!doctype html><meta charset='utf-8'><style>body{font-family:Segoe UI,Arial;margin:40px}</style>
        <h3>Access Denied</h3><p>Only the Super Admin may access System Settings.</p>";
  exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$notice = null; 
$error  = null;

// -----------------------------
// Handle POST (save per tab)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tab = $_POST['tab'] ?? '';

  // GENERAL
  if ($tab === 'general') {
    set_setting($conn, 'system_name', $_POST['system_name'] ?? '');
    set_setting($conn, 'school_name', $_POST['school_name'] ?? '');

    // Optional Logo Upload
    if (!empty($_FILES['school_logo']['name'])) {
      $f = $_FILES['school_logo'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/png','image/jpeg','image/jpg','image/webp'];
        $mime = @mime_content_type($f['tmp_name']);
        if (in_array($mime, $allowed) && $f['size'] <= 3*1024*1024) {
          $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
          $targetDir = __DIR__ . "/assets/images/";
          if (!is_dir($targetDir)) @mkdir($targetDir,0775,true);
          $newName = "logo_".time().".".$ext;
          $target  = $targetDir.$newName;

          if (move_uploaded_file($f['tmp_name'], $target)) {
            $relPath = "admin/assets/images/".$newName; // path used by web
            set_setting($conn, 'school_logo', $relPath);
            $notice = "General settings saved (logo updated).";
          } else {
            $error = "Failed to move uploaded file.";
          }
        } else {
          $error = "Invalid logo type/size. Allowed: PNG/JPG/WEBP up to 3MB.";
        }
      } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "Upload error code: ".$f['error'];
      }
    }
    if (!$notice && !$error) $notice = "General settings saved.";
  }

  // POLICY
  if ($tab === 'policy') {
    set_setting($conn, 'require_id',    isset($_POST['require_id'])    ? '1' : '0');
    set_setting($conn, 'require_shoes', isset($_POST['require_shoes']) ? '1' : '0');
    
    // Detection sensitivity (0.1 to 1.0)
    $sensitivity = (float)($_POST['detection_sensitivity'] ?? 0.58);
    if ($sensitivity < 0.1) $sensitivity = 0.1;
    if ($sensitivity > 1.0) $sensitivity = 1.0;
    set_setting($conn, 'detection_sensitivity', $sensitivity);
    
    $notice = "Uniform policy saved.";
  }

  // PENALTY
  if ($tab === 'penalty') {
    $fee = (float)($_POST['default_penalty'] ?? 5);
    if ($fee < 0) $fee = 0;
    set_setting($conn, 'default_penalty', $fee);
    $notice = "Penalty settings saved.";
  }

  // EMAIL / SMTP
  if ($tab === 'email') {
    set_setting($conn, 'smtp_host',         $_POST['smtp_host'] ?? '');
    set_setting($conn, 'smtp_user',         $_POST['smtp_user'] ?? '');
    set_setting($conn, 'smtp_pass',         $_POST['smtp_pass'] ?? '');
    set_setting($conn, 'smtp_sender_name',  $_POST['smtp_sender_name'] ?? '');
    $notice = "Email/SMTP settings saved.";
  }

  // REPORTS
  if ($tab === 'reports') {
    set_setting($conn, 'date_format',   $_POST['date_format'] ?? 'Y-m-d');
    set_setting($conn, 'report_footer', $_POST['report_footer'] ?? '');
    $notice = "Report settings saved.";
  }

  // GRADUATION
  if ($tab === 'graduation') {
    $graduation_batch = trim($_POST['graduation_batch'] ?? '');
    $graduation_method = $_POST['graduation_method'] ?? 'csv';
    
    if ($graduation_method === 'csv') {
      // CSV Upload Method
      if (!isset($_FILES['graduation_csv']) || $_FILES['graduation_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid CSV file.";
      } elseif (pathinfo($_FILES['graduation_csv']['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = "Please upload a valid CSV file (.csv extension required).";
      }
    } elseif ($graduation_method === 'select') {
      // Manual Selection Method
      $selected_students = $_POST['selected_students'] ?? [];
      if (empty($selected_students)) {
        $error = "Please select at least one student to graduate.";
      }
    } else {
      $error = "Invalid graduation method selected.";
    }
    
    if (!$error && $graduation_method === 'csv') {
      // Process CSV file
      $file = $_FILES['graduation_csv'];
      $handle = fopen($file['tmp_name'], 'r');
      
      if (!$handle) {
        $error = "Could not read the uploaded CSV file.";
      } else {
        $students_data = [];
        $header = fgetcsv($handle); // Read header row
        
        // Validate header format
        if (!$header || count($header) < 2) {
          $error = "Invalid CSV format. Please use the template with 'Id number,Name' columns.";
          fclose($handle);
        } else {
          $row_number = 1;
          
          // Read all student data from CSV
          while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            // Skip empty rows
            if (count(array_filter($data)) === 0) continue;
            
            $id_number = isset($data[0]) ? trim($data[0]) : '';
            $name = isset($data[1]) ? trim($data[1]) : '';
            
            // Validate required fields
            if (empty($id_number)) {
              $error = "Row $row_number: ID number is required.";
              break;
            }
            if (empty($name)) {
              $error = "Row $row_number: Name is required.";
              break;
            }
            
            $students_data[] = [
              'id_number' => $id_number,
              'name' => $name,
              'row' => $row_number
            ];
          }
          fclose($handle);
          
          if (!$error && empty($students_data)) {
            $error = "No valid student records found in the CSV file.";
          }
        }
        
        if (!$error) {
          $success_count = 0;
          $error_count = 0;
          $not_found = [];
          $mismatched = [];
          
          require_once("includes/archive_helper.php");
          require_once("includes/activity_logger.php");
          
          foreach ($students_data as $student_data) {
            $id_number = $student_data['id_number'];
            $expected_name = $student_data['name'];
            $row_number = $student_data['row'];
            
            // Find student by ID number (exact match)
            $id_escaped = $conn->real_escape_string($id_number);
            $sql = "SELECT id, student_code, fullname, course, year_level, section 
                    FROM students 
                    WHERE student_code = '$id_escaped' 
                    AND archived_at IS NULL
                    LIMIT 1";
            
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
              $student = $result->fetch_assoc();
              
              // Verify name matches (case-insensitive partial match)
              $actual_name = $student['fullname'];
              $name_match = stripos($actual_name, $expected_name) !== false || 
                           stripos($expected_name, $actual_name) !== false;
              
              if ($name_match) {
                // Archive the student
                if (archive_record($conn, 'students', $student['id'])) {
                  $graduation_note = $graduation_batch ? "Graduated - Batch $graduation_batch" : "Graduated";
                  log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, 
                              $student['id'], $student['fullname'], 
                              "Student graduated via CSV import: {$student['student_code']} - {$student['fullname']} ($graduation_note)");
                  $success_count++;
                } else {
                  $error_count++;
                }
              } else {
                // ID found but name doesn't match
                $mismatched[] = "Row $row_number: ID '$id_number' found but name mismatch (Expected: '$expected_name', Found: '$actual_name')";
                $error_count++;
              }
            } else {
              // ID not found
              $not_found[] = "Row $row_number: ID '$id_number' not found";
              $error_count++;
            }
          }
          
          // Generate summary message
          $summary_parts = [];
          if ($success_count > 0) {
            $summary_parts[] = "$success_count students graduated and archived";
          }
          
          $error_details = array_merge($not_found, $mismatched);
          if (!empty($error_details)) {
            $display_errors = array_slice($error_details, 0, 3);
            if (count($error_details) > 3) {
              $display_errors[] = "... and " . (count($error_details) - 3) . " more errors";
            }
            $summary_parts[] = "Errors: " . implode('; ', $display_errors);
          }
          
          if ($success_count > 0 && $error_count === 0) {
            set_success_alert('graduated', 'students', "$success_count students");
            $notice = implode('. ', $summary_parts) . ".";
          } elseif ($success_count > 0) {
            $notice = implode('. ', $summary_parts) . ".";
          } else {
            $error = count($error_details) > 0 ? implode('; ', $display_errors) . "." : "No students could be graduated.";
          }
        }
      }
    } elseif (!$error && $graduation_method === 'select') {
      // Process manual selection
      $selected_students = $_POST['selected_students'] ?? [];
      $success_count = 0;
      $error_count = 0;
      $failed_students = [];
      
      require_once("includes/archive_helper.php");
      require_once("includes/activity_logger.php");
      
      foreach ($selected_students as $student_id) {
        $student_id = intval($student_id);
        
        // Get student details
        $student_sql = "SELECT id, student_code, fullname, course, year_level, section 
                       FROM students 
                       WHERE id = $student_id AND archived_at IS NULL";
        $student_result = $conn->query($student_sql);
        
        if ($student_result && $student_result->num_rows > 0) {
          $student = $student_result->fetch_assoc();
          
          // Archive the student
          if (archive_record($conn, 'students', $student['id'])) {
            $graduation_note = $graduation_batch ? "Graduated - Batch $graduation_batch" : "Graduated";
            log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, 
                        $student['id'], $student['fullname'], 
                        "Student graduated via manual selection: {$student['student_code']} - {$student['fullname']} ($graduation_note)");
            $success_count++;
          } else {
            $failed_students[] = $student['student_code'] . ' - ' . $student['fullname'];
            $error_count++;
          }
        } else {
          $error_count++;
        }
      }
      
      // Generate summary for manual selection
      if ($success_count > 0 && $error_count === 0) {
        set_success_alert('graduated', 'students', "$success_count students");
        $notice = "$success_count students graduated and archived successfully.";
      } elseif ($success_count > 0) {
        $notice = "$success_count students graduated successfully. $error_count failed to graduate.";
      } else {
        $error = "No students could be graduated. Please try again.";
      }
    }
  }

  // STUDENT ADVANCEMENT
  if ($tab === 'advancement') {
    $advancement_batch = trim($_POST['advancement_batch'] ?? '');
    
    require_once("includes/archive_helper.php");
    require_once("includes/activity_logger.php");
    
    if (isset($_POST['advance_all'])) {
      // Advance all students to next year level, graduate 4th years
      $success_count = 0;
      $graduated_count = 0;
      $error_count = 0;
      
      // Get all active students
      $students_sql = "SELECT id, student_code, fullname, course, year_level, section 
                       FROM students 
                       WHERE archived_at IS NULL 
                       ORDER BY year_level DESC"; // Process 4th years first for graduation
      
      $students_result = $conn->query($students_sql);
      
      if ($students_result) {
        while ($student = $students_result->fetch_assoc()) {
          if ($student['year_level'] == 4) {
            // Graduate 4th year students
            if (archive_record($conn, 'students', $student['id'])) {
              $advancement_note = $advancement_batch ? "Auto-Graduated - $advancement_batch" : "Auto-Graduated";
              log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, 
                          $student['id'], $student['fullname'], 
                          "Student auto-graduated during advancement: {$student['student_code']} - {$student['fullname']} ($advancement_note)");
              $graduated_count++;
            } else {
              $error_count++;
            }
          } else {
            // Advance to next year level
            $new_year_level = $student['year_level'] + 1;
            $update_sql = "UPDATE students SET year_level = $new_year_level WHERE id = {$student['id']}";
            
            if ($conn->query($update_sql)) {
              $advancement_note = $advancement_batch ? "Advanced to Year $new_year_level - $advancement_batch" : "Advanced to Year $new_year_level";
              log_activity($conn, ActivityActions::STUDENT_UPDATED, ActivityTargets::STUDENT, 
                          $student['id'], $student['fullname'], 
                          "Student advanced from Year {$student['year_level']} to Year $new_year_level ($advancement_note)");
              $success_count++;
            } else {
              $error_count++;
            }
          }
        }
      }
      
      $total_processed = $success_count + $graduated_count;
      if ($total_processed > 0 && $error_count === 0) {
        $message = "Successfully processed $total_processed students: $success_count advanced, $graduated_count graduated.";
        set_success_alert('advanced', 'students', "$total_processed students");
        $notice = $message;
      } elseif ($total_processed > 0) {
        $notice = "Processed $total_processed students successfully ($success_count advanced, $graduated_count graduated). $error_count errors occurred.";
      } else {
        $error = "Failed to advance students. Please try again.";
      }
    } 
    elseif (isset($_POST['process_failed_students'])) {
      // Process CSV of students who failed to advance
      if (!isset($_FILES['failed_students_csv']) || $_FILES['failed_students_csv']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid CSV file.";
      } elseif (pathinfo($_FILES['failed_students_csv']['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = "Please upload a valid CSV file (.csv extension required).";
      } else {
        $file = $_FILES['failed_students_csv'];
        $handle = fopen($file['tmp_name'], 'r');
        
        if (!$handle) {
          $error = "Could not read the uploaded CSV file.";
        } else {
          $failed_students_data = [];
          $header = fgetcsv($handle); // Read header row
          
          if (!$header || count($header) < 2) {
            $error = "Invalid CSV format. Please use 'Id number,Name' columns.";
            fclose($handle);
          } else {
            $row_number = 1;
            
            while (($data = fgetcsv($handle)) !== FALSE) {
              $row_number++;
              if (count(array_filter($data)) === 0) continue;
              
              $id_number = isset($data[0]) ? trim($data[0]) : '';
              $name = isset($data[1]) ? trim($data[1]) : '';
              
              if (empty($id_number) || empty($name)) continue;
              
              $failed_students_data[] = [
                'id_number' => $id_number,
                'name' => $name,
                'row' => $row_number
              ];
            }
            fclose($handle);
            
            if (empty($failed_students_data)) {
              $error = "No valid student records found in the CSV file.";
            } else {
              $found_count = 0;
              $not_found = [];
              
              foreach ($failed_students_data as $failed_data) {
                $id_escaped = $conn->real_escape_string($failed_data['id_number']);
                $check_sql = "SELECT id, student_code, fullname FROM students 
                             WHERE student_code = '$id_escaped' AND archived_at IS NULL";
                $check_result = $conn->query($check_sql);
                
                if ($check_result && $check_result->num_rows > 0) {
                  $student = $check_result->fetch_assoc();
                  log_activity($conn, ActivityActions::STUDENT_UPDATED, ActivityTargets::STUDENT, 
                              $student['id'], $student['fullname'], 
                              "Student marked as failed to advance: {$student['student_code']} - Will remain in current year level");
                  $found_count++;
                } else {
                  $not_found[] = "ID '{$failed_data['id_number']}' not found";
                }
              }
              
              if ($found_count > 0) {
                $notice = "$found_count students marked as failed to advance and will remain in their current year level.";
                if (!empty($not_found)) {
                  $notice .= " " . count($not_found) . " IDs not found.";
                }
              } else {
                $error = "No valid students found from the CSV file.";
              }
            }
          }
        }
      }
    }
    elseif (isset($_POST['advance_except_selected'])) {
      // Advance all students except those selected to keep in current year
      $keep_students = $_POST['keep_students'] ?? [];
      $keep_student_ids = array_map('intval', $keep_students);
      
      $success_count = 0;
      $graduated_count = 0;
      $kept_count = count($keep_student_ids);
      $error_count = 0;
      
      // Build WHERE clause to exclude selected students
      $keep_ids_str = !empty($keep_student_ids) ? implode(',', $keep_student_ids) : '0';
      
      $students_sql = "SELECT id, student_code, fullname, course, year_level, section 
                       FROM students 
                       WHERE archived_at IS NULL AND id NOT IN ($keep_ids_str)
                       ORDER BY year_level DESC";
      
      $students_result = $conn->query($students_sql);
      
      if ($students_result) {
        while ($student = $students_result->fetch_assoc()) {
          if ($student['year_level'] == 4) {
            // Graduate 4th year students
            if (archive_record($conn, 'students', $student['id'])) {
              $advancement_note = $advancement_batch ? "Auto-Graduated (Selective) - $advancement_batch" : "Auto-Graduated (Selective)";
              log_activity($conn, ActivityActions::STUDENT_DELETED, ActivityTargets::STUDENT, 
                          $student['id'], $student['fullname'], 
                          "Student graduated during selective advancement: {$student['student_code']} - {$student['fullname']} ($advancement_note)");
              $graduated_count++;
            } else {
              $error_count++;
            }
          } else {
            // Advance to next year level
            $new_year_level = $student['year_level'] + 1;
            $update_sql = "UPDATE students SET year_level = $new_year_level WHERE id = {$student['id']}";
            
            if ($conn->query($update_sql)) {
              $advancement_note = $advancement_batch ? "Advanced to Year $new_year_level (Selective) - $advancement_batch" : "Advanced to Year $new_year_level (Selective)";
              log_activity($conn, ActivityActions::STUDENT_UPDATED, ActivityTargets::STUDENT, 
                          $student['id'], $student['fullname'], 
                          "Student selectively advanced from Year {$student['year_level']} to Year $new_year_level ($advancement_note)");
              $success_count++;
            } else {
              $error_count++;
            }
          }
        }
      }
      
      // Log kept students
      if (!empty($keep_student_ids)) {
        $kept_students_sql = "SELECT id, student_code, fullname FROM students WHERE id IN ($keep_ids_str)";
        $kept_result = $conn->query($kept_students_sql);
        
        if ($kept_result) {
          while ($kept_student = $kept_result->fetch_assoc()) {
            log_activity($conn, ActivityActions::STUDENT_UPDATED, ActivityTargets::STUDENT, 
                        $kept_student['id'], $kept_student['fullname'], 
                        "Student kept in current year level during selective advancement: {$kept_student['student_code']}");
          }
        }
      }
      
      $total_processed = $success_count + $graduated_count;
      if ($total_processed > 0 && $error_count === 0) {
        $message = "Successfully processed students: $success_count advanced, $graduated_count graduated, $kept_count kept in current year.";
        set_success_alert('advanced', 'students', "$total_processed students");
        $notice = $message;
      } elseif ($total_processed > 0) {
        $notice = "Processed $total_processed students ($success_count advanced, $graduated_count graduated, $kept_count kept). $error_count errors occurred.";
      } else {
        $error = "No students were processed. Please try again.";
      }
    }
  }
}

// -----------------------------
// Pull current values
// -----------------------------
$system_name     = get_setting($conn,'system_name','Uniform Monitoring System');
$school_name     = get_setting($conn,'school_name','Your School Name');
$school_logo     = get_setting($conn,'school_logo',''); // optional

$require_id          = get_setting($conn,'require_id','1') === '1';
$require_shoes       = get_setting($conn,'require_shoes','1') === '1';
$detection_sensitivity = (float)get_setting($conn,'detection_sensitivity','0.58');

$default_penalty = get_setting($conn,'default_penalty','5');

$smtp_host       = get_setting($conn,'smtp_host','smtp-relay.brevo.com');
$smtp_user       = get_setting($conn,'smtp_user','');
$smtp_pass       = get_setting($conn,'smtp_pass','');
$smtp_sender     = get_setting($conn,'smtp_sender_name','Uniform Monitoring System');

$date_format     = get_setting($conn,'date_format','Y-m-d');
$report_footer   = get_setting($conn,'report_footer','Generated by Uniform Monitoring System');

// Handle CSV template download for graduation
if (isset($_GET['download_graduation_template'])) {
    $filename = "graduation_template_" . date("Ymd") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $output = fopen("php://output", "w");
    
    // CSV header
    fputcsv($output, ['Id number', 'Name']);
    
    // Sample data - get a few active students as examples
    $sample_sql = "SELECT student_code, fullname FROM students WHERE archived_at IS NULL ORDER BY student_code LIMIT 5";
    $sample_result = $conn->query($sample_sql);
    
    if ($sample_result && $sample_result->num_rows > 0) {
        $count = 0;
        while (($row = $sample_result->fetch_assoc()) && $count < 3) {
            fputcsv($output, [$row['student_code'], $row['fullname']]);
            $count++;
        }
        // Add one example entry
        fputcsv($output, ['2024-XXXX', 'Example Student Name']);
    } else {
        // Fallback sample data
        fputcsv($output, ['2024-0001', 'John Doe']);
        fputcsv($output, ['2024-0002', 'Jane Smith']); 
        fputcsv($output, ['2024-0003', 'Maria Garcia']);
        fputcsv($output, ['2024-0004', 'Robert Johnson']);
    }
    
    fclose($output);
    exit();
}

include('includes/header.php'); // expects Bootstrap & $conn context available
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="m-0">System Settings</h3>
    <span class="text-muted">Super Admin Only</span>
  </div>

  <?php if ($notice): ?>
    <div class="alert alert-success"><?php echo h($notice); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
  <?php endif; ?>

  <?php echo render_alert_script(); ?>

  <!-- Tabs -->
  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGeneral" type="button">General</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPolicy" type="button">Uniform Policy</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPenalty" type="button">Penalty</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEmail" type="button">Email / SMTP</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabReports" type="button">Reports & Format</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGraduation" type="button">
        <i class="fas fa-graduation-cap me-1"></i>Graduation
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAdvancement" type="button">
        <i class="fas fa-level-up-alt me-1"></i>Advancement
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRecycleBin" type="button">
        <i class="fas fa-recycle me-1"></i>Recycle Bin
      </button>
    </li>
  </ul>

  <div class="tab-content border border-top-0 p-3 bg-white">

    <!-- General -->
    <div class="tab-pane fade show active" id="tabGeneral">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="tab" value="general">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">System Name</label>
            <input type="text" name="system_name" class="form-control" value="<?php echo h($system_name); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">School Name</label>
            <input type="text" name="school_name" class="form-control" value="<?php echo h($school_name); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Logo (PNG/JPG/WEBP, max 3MB)</label>
            <input type="file" name="school_logo" class="form-control">
            <?php if ($school_logo): ?>
              <div class="mt-2 small text-muted">Current Logo:</div>
              <img src="../<?php echo h($school_logo); ?>" alt="Logo" style="max-height:60px">
            <?php else: ?>
              <div class="mt-2 small text-muted">No logo uploaded. Text branding will be used.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="mt-3 text-end">
          <button class="btn btn-primary"><i class="fa fa-save me-1"></i> Save General</button>
        </div>
      </form>
    </div>

    <!-- Uniform Policy -->
    <div class="tab-pane fade" id="tabPolicy">
      <form method="post">
        <input type="hidden" name="tab" value="policy">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="require_id" name="require_id" <?php echo $require_id?'checked':''; ?>>
          <label class="form-check-label" for="require_id">Require ID for compliance</label>
        </div>
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" id="require_shoes" name="require_shoes" <?php echo $require_shoes?'checked':''; ?>>
          <label class="form-check-label" for="require_shoes">Require Shoes for compliance</label>
        </div>
        
        <div class="mt-4">
          <label class="form-label fw-bold">
            <i class="fas fa-eye me-2"></i>Detection Sensitivity
          </label>
          <div class="row align-items-center">
            <div class="col-md-8">
              <input type="range" class="form-range" id="detection_sensitivity" name="detection_sensitivity" 
                     min="0.1" max="1.0" step="0.01" value="<?php echo $detection_sensitivity; ?>"
                     oninput="updateSensitivityValue(this.value)">
              <div class="d-flex justify-content-between text-muted small mt-1">
                <span>Low (0.1)</span>
                <span>Medium (0.5)</span>
                <span>High (1.0)</span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="input-group">
                <input type="text" class="form-control text-center fw-bold" id="sensitivity_value" 
                       value="<?php echo $detection_sensitivity; ?>" readonly>
                <span class="input-group-text">
                  <i class="fas fa-crosshairs"></i>
                </span>
              </div>
            </div>
          </div>
          <div class="form-text">
            <i class="fas fa-info-circle me-1"></i>
            Higher values make detection more strict (fewer false positives), lower values make detection more sensitive (may catch more items but with more false positives).
          </div>
        </div>
        
        <div class="mt-3 text-end">
          <button class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Policy</button>
        </div>
      </form>
    </div>

    <!-- Penalty -->
    <div class="tab-pane fade" id="tabPenalty">
      <form method="post">
        <input type="hidden" name="tab" value="penalty">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Default Penalty Fee (₱)</label>
            <input type="number" step="0.01" min="0" name="default_penalty" class="form-control" value="<?php echo h($default_penalty); ?>" required>
          </div>
        </div>
        <div class="mt-3 text-end">
          <button class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Penalty</button>
        </div>
      </form>
    </div>

    <!-- Email / SMTP -->
    <div class="tab-pane fade" id="tabEmail">
      <form method="post">
        <input type="hidden" name="tab" value="email">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">SMTP Host</label>
            <input type="text" name="smtp_host" class="form-control" value="<?php echo h($smtp_host); ?>" placeholder="smtp-relay.brevo.com">
          </div>
          <div class="col-md-4">
            <label class="form-label">SMTP User</label>
            <input type="text" name="smtp_user" class="form-control" value="<?php echo h($smtp_user); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">SMTP Key / Password</label>
            <input type="text" name="smtp_pass" class="form-control" value="<?php echo h($smtp_pass); ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Sender Name</label>
            <input type="text" name="smtp_sender_name" class="form-control" value="<?php echo h($smtp_sender); ?>">
          </div>
        </div>
        <div class="mt-3 text-end">
          <button class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Email</button>
        </div>
      </form>
    </div>

    <!-- Reports & Format -->
    <div class="tab-pane fade" id="tabReports">
      <form method="post">
        <input type="hidden" name="tab" value="reports">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Date Format (PHP)</label>
            <input type="text" name="date_format" class="form-control" value="<?php echo h($date_format); ?>" placeholder="Y-m-d">
            <div class="form-text">Examples: Y-m-d, m/d/Y, d M Y</div>
          </div>
          <div class="col-md-8">
            <label class="form-label">Report Footer</label>
            <input type="text" name="report_footer" class="form-control" value="<?php echo h($report_footer); ?>" placeholder="Generated by Uniform Monitoring System">
          </div>
        </div>
        <div class="mt-3 text-end">
          <button class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Report Settings</button>
        </div>
      </form>
    </div>

    <!-- Graduation -->
    <div class="tab-pane fade" id="tabGraduation">
      <div class="row">
        <div class="col-lg-8">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="tab" value="graduation">
            
            <div class="card">
              <div class="card-header">
                <h5 class="m-0">
                  <i class="fas fa-graduation-cap me-2 text-success"></i>Graduate Students
                </h5>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label fw-bold">Graduation Batch (Optional)</label>
                  <input type="text" name="graduation_batch" class="form-control" 
                         placeholder="e.g., Class of 2025, Spring 2025, etc.">
                  <div class="form-text">
                    This will be included in the activity log for reference.
                  </div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label fw-bold">Graduation Method</label>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="graduation_method" id="method_csv" value="csv" checked>
                    <label class="form-check-label" for="method_csv">
                      <strong>Upload CSV File</strong>
                    </label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="graduation_method" id="method_select" value="select">
                    <label class="form-check-label" for="method_select">
                      <strong>Select Students Manually</strong>
                    </label>
                  </div>
                </div>

                <!-- CSV Upload Method -->
                <div id="csv_method_section" class="mb-3">
                  <label class="form-label fw-bold">
                    Upload CSV File with Students to Graduate
                    <span class="text-danger">*</span>
                  </label>
                  <input type="file" name="graduation_csv" class="form-control" accept=".csv">
                  <div class="form-text">
                    <i class="fas fa-info-circle me-1"></i>
                    Upload a CSV file with format: Id number,Name
                  </div>
                </div>

                <!-- Manual Selection Method -->
                <div id="select_method_section" class="mb-3" style="display: none;">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label fw-bold">Select Students to Graduate</label>
                    <div>
                      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterStudents()">
                        <i class="fas fa-filter me-1"></i>Filter
                      </button>
                    </div>
                  </div>
                  
                  <!-- Filter Options -->
                  <div id="filter_section" class="row g-2 mb-3" style="display: none;">
                    <div class="col-md-3">
                      <select class="form-select form-select-sm" id="filter_course">
                        <option value="">All Courses</option>
                        <?php 
                        $courses_filter = $conn->query("SELECT DISTINCT course FROM students WHERE archived_at IS NULL ORDER BY course");
                        if ($courses_filter) {
                          while ($course = $courses_filter->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($course['course']) . '">' . 
                                 htmlspecialchars($course['course']) . '</option>';
                          }
                        }
                        ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <select class="form-select form-select-sm" id="filter_year">
                        <option value="">All Year Levels</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <select class="form-select form-select-sm" id="filter_section">
                        <option value="">All Sections</option>
                        <?php 
                        $sections_filter = $conn->query("SELECT DISTINCT section FROM students WHERE archived_at IS NULL ORDER BY section");
                        if ($sections_filter) {
                          while ($section = $sections_filter->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($section['section']) . '">' . 
                                 htmlspecialchars($section['section']) . '</option>';
                          }
                        }
                        ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <input type="text" class="form-control form-control-sm" id="filter_search" placeholder="Search name/ID">
                    </div>
                  </div>
                  
                  <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" id="select_all_students">
                      <label class="form-check-label fw-bold" for="select_all_students">
                        Select All Students
                      </label>
                    </div>
                    <hr>
                    
                    <!-- Students List -->
                    <div id="students_list">
                      <?php
                      $students_sql = "SELECT id, student_code, fullname, course, year_level, section 
                                      FROM students 
                                      WHERE archived_at IS NULL 
                                      ORDER BY course, year_level, section, student_code";
                      $students_result = $conn->query($students_sql);
                      
                      if ($students_result && $students_result->num_rows > 0):
                        while ($student = $students_result->fetch_assoc()):
                      ?>
                        <div class="form-check student-item" 
                             data-course="<?php echo htmlspecialchars($student['course']); ?>"
                             data-year="<?php echo htmlspecialchars($student['year_level']); ?>"
                             data-section="<?php echo htmlspecialchars($student['section']); ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($student['student_code'] . ' ' . $student['fullname'])); ?>">
                          <input class="form-check-input student-checkbox" type="checkbox" 
                                 name="selected_students[]" value="<?php echo $student['id']; ?>"
                                 id="student_<?php echo $student['id']; ?>">
                          <label class="form-check-label" for="student_<?php echo $student['id']; ?>">
                            <strong><?php echo htmlspecialchars($student['student_code']); ?></strong> - 
                            <?php echo htmlspecialchars($student['fullname']); ?>
                            <small class="text-muted">
                              (<?php echo htmlspecialchars($student['course'] . ' ' . $student['year_level'] . $student['section']); ?>)
                            </small>
                          </label>
                        </div>
                      <?php 
                        endwhile;
                      else:
                      ?>
                        <div class="text-muted">No active students found.</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">CSV Template</span>
                    <a href="?download_graduation_template=1" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-download me-1"></i>Download Template
                    </a>
                  </div>
                  <div class="mt-2 p-2 bg-light border rounded">
                    <small class="text-muted">Required CSV Format:</small><br>
                    <code style="font-size: 11px;">
                      Id number,Name<br>
                      2024-0001,John Doe<br>
                      2024-0002,Jane Smith<br>
                      2024-0003,Maria Garcia<br>
                      2024-0004,Robert Johnson
                    </code>
                  </div>
                </div>
                
                <div class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  <strong>Important:</strong> Graduating students will be <strong>archived</strong> (moved to Recycle Bin). 
                  They can be restored later if needed, but will no longer appear in active student lists or scanning.
                </div>
                
                <div class="text-end">
                  <button type="submit" class="btn btn-success btn-lg" 
                          onclick="return confirmGraduation()">
                    <i class="fas fa-graduation-cap me-2"></i>Graduate Selected Students
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
        
        <div class="col-lg-4">
          <div class="card bg-light">
            <div class="card-header">
              <h6 class="m-0">
                <i class="fas fa-question-circle me-2"></i>How to Use
              </h6>
            </div>
            <div class="card-body">
              <div class="small">
                <h6 class="text-primary">Step 1: Enter Batch Info</h6>
                <p class="mb-3">Optionally enter a graduation batch name for record keeping (e.g., "Class of 2025").</p>
                
                <h6 class="text-primary">Step 2: Prepare CSV File</h6>
                <p class="mb-3">Create a CSV file with two columns: ID number and Name. Download the template for the correct format:</p>
                <div class="bg-white p-2 border rounded mb-3">
                  <code style="font-size: 11px;">
                    Id number,Name<br>
                    2024-0001,John Doe<br>
                    2024-0002,Jane Smith<br>
                    2024-0003,Maria Garcia
                  </code>
                </div>
                
                <h6 class="text-primary">Step 3: Review Results</h6>
                <p class="mb-3">The system will show you which students were found and graduated successfully.</p>
                
                <div class="alert alert-info alert-sm">
                  <i class="fas fa-lightbulb me-1"></i>
                  <small><strong>Tip:</strong> You can restore graduated students from the Recycle Bin tab if needed.</small>
                </div>
              </div>
            </div>
          </div>
          
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="m-0">
                <i class="fas fa-chart-bar me-2"></i>Quick Stats
              </h6>
            </div>
            <div class="card-body">
              <?php
              // Get current student counts by year
              $stats_sql = "SELECT year_level, COUNT(*) as count 
                           FROM students 
                           WHERE archived_at IS NULL 
                           GROUP BY year_level 
                           ORDER BY year_level";
              $stats_result = $conn->query($stats_sql);
              $stats = [];
              while ($row = $stats_result->fetch_assoc()) {
                $stats[$row['year_level']] = $row['count'];
              }
              ?>
              <div class="small">
                <div class="d-flex justify-content-between mb-2">
                  <span>1st Year Students:</span>
                  <span class="fw-bold"><?php echo $stats['1st Year'] ?? 0; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span>2nd Year Students:</span>
                  <span class="fw-bold"><?php echo $stats['2nd Year'] ?? 0; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span>3rd Year Students:</span>
                  <span class="fw-bold"><?php echo $stats['3rd Year'] ?? 0; ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                  <span>4th Year Students:</span>
                  <span class="fw-bold text-warning"><?php echo $stats['4th Year'] ?? 0; ?></span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="fw-bold">Total Active:</span>
                  <span class="fw-bold text-primary"><?php echo array_sum($stats); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Student Advancement -->
    <div class="tab-pane fade" id="tabAdvancement">
      <div class="row">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
              <i class="fas fa-level-up-alt me-2"></i>Student Advancement
            </h5>
            <div class="text-muted small">
              Advance all students to next year level or handle failed students
            </div>
          </div>

          <form method="post" enctype="multipart/form-data" onsubmit="return confirmAdvancement();">
            <input type="hidden" name="tab" value="advancement">

            <div class="row">
              <div class="col-lg-6">
                <div class="card">
                  <div class="card-header">
                    <h6 class="card-title mb-0">
                      <i class="fas fa-arrow-up me-1"></i>Advance All Students
                    </h6>
                  </div>
                  <div class="card-body">
                    <p class="text-muted small mb-3">
                      Automatically advance all students to the next year level. Students in 4th year will be graduated.
                    </p>

                    <div class="mb-3">
                      <label class="form-label">Academic Year</label>
                      <input type="text" class="form-control" name="advancement_batch" 
                             placeholder="e.g., 2024-2025" maxlength="20">
                      <div class="form-text">Optional: Specify the academic year for this advancement</div>
                    </div>

                    <div class="d-grid">
                      <button type="submit" name="advance_all" class="btn btn-success">
                        <i class="fas fa-arrow-up me-2"></i>Advance All Students
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-lg-6">
                <div class="card">
                  <div class="card-header">
                    <h6 class="card-title mb-0">
                      <i class="fas fa-file-upload me-1"></i>Import Failed Students
                    </h6>
                  </div>
                  <div class="card-body">
                    <p class="text-muted small mb-3">
                      Upload a CSV file of students who failed to advance (stay in current year level).
                    </p>

                    <div class="mb-3">
                      <label class="form-label">CSV File Format</label>
                      <div class="alert alert-info py-2 px-3">
                        <small>
                          <strong>Required columns:</strong> Id number, Name<br>
                          <strong>Example:</strong> 2021-0001, Juan Dela Cruz
                        </small>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Upload CSV File</label>
                      <input type="file" class="form-control" name="failed_students_csv" 
                             accept=".csv" required>
                      <div class="form-text">Students in this file will remain in their current year level</div>
                    </div>

                    <div class="d-grid">
                      <button type="submit" name="process_failed_students" class="btn btn-warning">
                        <i class="fas fa-file-upload me-2"></i>Process Failed Students
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Advance with Exceptions -->
            <div class="row mt-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                      <h6 class="card-title mb-0">
                        <i class="fas fa-users me-1"></i>Advance All Except Selected
                      </h6>
                      <button type="button" class="btn btn-sm btn-outline-primary" 
                              id="toggle_student_selection_btn">
                        <i class="fas fa-search me-1"></i>Select Students to Keep
                      </button>
                    </div>
                  </div>
                  <div class="card-body" id="advancement_student_selection" style="display: none;">
                    <div class="row mb-3">
                      <div class="col-md-3">
                        <select class="form-select" id="advancement_filter_course">
                          <option value="">All Courses</option>
                          <?php
                          $courses_adv = $conn->query("SELECT DISTINCT course FROM students WHERE archived_at IS NULL ORDER BY course");
                          if ($courses_adv) {
                            while ($course = $courses_adv->fetch_assoc()) {
                              echo '<option value="' . htmlspecialchars($course['course']) . '">' . 
                                   htmlspecialchars($course['course']) . '</option>';
                            }
                          }
                          ?>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <select class="form-select" id="advancement_filter_year">
                          <option value="">All Years</option>
                          <option value="1">1st Year</option>
                          <option value="2">2nd Year</option>
                          <option value="3">3rd Year</option>
                          <option value="4">4th Year</option>
                        </select>
                      </div>
                      <div class="col-md-2">
                        <select class="form-select" id="advancement_filter_section">
                          <option value="">All Sections</option>
                          <?php
                          $sections_adv = $conn->query("SELECT DISTINCT section FROM students WHERE archived_at IS NULL ORDER BY section");
                          if ($sections_adv) {
                            while ($section = $sections_adv->fetch_assoc()) {
                              echo '<option value="' . htmlspecialchars($section['section']) . '">' . 
                                   htmlspecialchars($section['section']) . '</option>';
                            }
                          }
                          ?>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <input type="text" class="form-control" id="advancement_filter_search" 
                               placeholder="Search name or ID...">
                      </div>
                      <div class="col-md-2">
                        <div class="form-check">
                          <input class="form-check-input" type="checkbox" id="advancement_select_all">
                          <label class="form-check-label" for="advancement_select_all">
                            Select All Visible
                          </label>
                        </div>
                      </div>
                    </div>

                    <div class="student-list-container" style="max-height: 400px; overflow-y: auto;">
                      <?php
                      $all_students = $conn->query("
                        SELECT id, student_code, fullname, course, year_level, section 
                        FROM students 
                        WHERE archived_at IS NULL 
                        ORDER BY course, year_level, section, fullname
                      ");
                      
                      if ($all_students) {
                        while ($student = $all_students->fetch_assoc()):
                      ?>
                        <div class="advancement-student-item border-bottom py-2" 
                             data-course="<?php echo htmlspecialchars($student['course']); ?>"
                             data-year="<?php echo $student['year_level']; ?>"
                             data-section="<?php echo htmlspecialchars($student['section']); ?>"
                             data-search="<?php echo strtolower(htmlspecialchars($student['fullname'] . ' ' . $student['student_code'])); ?>">
                          <div class="form-check">
                            <input class="form-check-input advancement-student-checkbox" type="checkbox" 
                                   name="keep_students[]" value="<?php echo $student['id']; ?>"
                                   id="keep_student_<?php echo $student['id']; ?>">
                            <label class="form-check-label" for="keep_student_<?php echo $student['id']; ?>">
                              <strong><?php echo htmlspecialchars($student['student_code']); ?></strong> - 
                              <?php echo htmlspecialchars($student['fullname']); ?>
                              <small class="text-muted ms-2">
                                (<?php echo htmlspecialchars($student['course'] . ' ' . $student['year_level'] . $student['section']); ?>)
                              </small>
                            </label>
                          </div>
                        </div>
                      <?php 
                        endwhile;
                      }
                      ?>
                    </div>

                    <div class="text-muted small mt-2">
                      <i class="fas fa-info-circle me-1"></i>
                      Selected students will remain in their current year level. All others will advance.
                    </div>

                    <div class="d-grid mt-3">
                      <button type="submit" name="advance_except_selected" class="btn btn-primary">
                        <i class="fas fa-arrow-up me-2"></i>Advance All Except Selected
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Recycle Bin -->
    <div class="tab-pane fade" id="tabRecycleBin">
      <div class="row">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
              <i class="fas fa-recycle me-2 text-warning"></i>Archived Records
            </h5>
            <div class="text-muted small">
              <i class="fas fa-info-circle me-1"></i>
              Archived records can be restored or permanently deleted
            </div>
          </div>
          
          <?php
          require_once("includes/archive_helper.php");
          require_once("includes/alert_helper.php");
          
          // Handle restore/delete actions
          if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recycle_action'])) {
            $action = $_POST['recycle_action'];
            $table = $_POST['table'] ?? '';
            $id = intval($_POST['id'] ?? 0);
            
            if ($action === 'restore' && $table === 'students' && $id > 0) {
              $res = $conn->query("SELECT student_code, fullname FROM students WHERE id=$id AND archived_at IS NOT NULL");
              $student = $res ? $res->fetch_assoc() : null;
              
              if ($student && restore_record($conn, 'students', $id)) {
                set_success_alert('restored', 'student', $student['student_code'] . ' - ' . $student['fullname']);
              } else {
                set_error_alert('restore', 'student');
              }
            } elseif ($action === 'delete' && $table === 'students' && $id > 0) {
              $res = $conn->query("SELECT student_code, fullname, photo FROM students WHERE id=$id AND archived_at IS NOT NULL");
              $student = $res ? $res->fetch_assoc() : null;
              
              if ($student && permanently_delete_record($conn, 'students', $id)) {
                // Delete photo file
                if ($student['photo'] && file_exists(__DIR__."/../".$student['photo'])) { 
                  @unlink(__DIR__."/../".$student['photo']); 
                }
                set_success_alert('deleted', 'student', $student['student_code'] . ' - ' . $student['fullname']);
              } else {
                set_error_alert('delete', 'student');
              }
            }
            
            // Redirect to prevent form resubmission
            header("Location: settings.php#tabRecycleBin");
            exit();
          }
          
          // Get archived students
          $archived_students = get_archived_records($conn, 'students', 50, 0);
          $archived_count = count_archived_records($conn, 'students');
          ?>
          
          <div class="card">
            <div class="card-header bg-light">
              <strong>
                <i class="fas fa-graduation-cap me-2"></i>Archived Students 
                <span class="badge bg-warning text-dark ms-2"><?php echo $archived_count; ?></span>
              </strong>
            </div>
            <div class="card-body p-0">
              <?php if (empty($archived_students)): ?>
                <div class="text-center text-muted py-5">
                  <i class="fas fa-recycle fa-3x mb-3 opacity-50"></i>
                  <div>No archived students</div>
                  <small>Archived students will appear here</small>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Student Code</th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Archived Date</th>
                        <th width="120">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($archived_students as $student): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                          <td><?php echo htmlspecialchars($student['fullname']); ?></td>
                          <td>
                            <small class="text-muted">
                              <?php echo htmlspecialchars($student['course'] . ' ' . $student['year_level'] . $student['section']); ?>
                            </small>
                          </td>
                          <td>
                            <small class="text-muted">
                              <?php echo date('M j, Y g:i A', strtotime($student['archived_at'])); ?>
                            </small>
                          </td>
                          <td>
                            <button type="button" class="btn btn-sm btn-outline-success me-1" 
                                    onclick="restoreRecord('students', <?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['student_code'] . ' - ' . $student['fullname'], ENT_QUOTES); ?>')">
                              <i class="fas fa-undo"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="permanentDelete('students', <?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['student_code'] . ' - ' . $student['fullname'], ENT_QUOTES); ?>')">
                              <i class="fas fa-trash"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Hidden forms for recycle bin actions -->
      <form id="recycleForm" method="post" class="d-none">
        <input type="hidden" name="recycle_action" id="recycle_action">
        <input type="hidden" name="table" id="recycle_table">
        <input type="hidden" name="id" id="recycle_id">
      </form>
    </div>

  </div><!-- /.tab-content -->
</div><!-- /.container-fluid -->

<style>
.form-range::-webkit-slider-thumb {
  background: #0d6efd;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.form-range::-moz-range-thumb {
  background: #0d6efd;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.form-range:focus::-webkit-slider-thumb {
  box-shadow: 0 0 0 1px #fff, 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<script>
function updateSensitivityValue(value) {
  document.getElementById('sensitivity_value').value = parseFloat(value).toFixed(2);
}

// Toggle graduation method sections
document.addEventListener('DOMContentLoaded', function() {
  const csvRadio = document.getElementById('method_csv');
  const selectRadio = document.getElementById('method_select');
  const csvSection = document.getElementById('csv_method_section');
  const selectSection = document.getElementById('select_method_section');
  
  function toggleGraduationMethod() {
    if (csvRadio.checked) {
      csvSection.style.display = 'block';
      selectSection.style.display = 'none';
      document.querySelector('input[name="graduation_csv"]').required = true;
    } else {
      csvSection.style.display = 'none';
      selectSection.style.display = 'block';
      document.querySelector('input[name="graduation_csv"]').required = false;
    }
  }
  
  csvRadio.addEventListener('change', toggleGraduationMethod);
  selectRadio.addEventListener('change', toggleGraduationMethod);
  
  // Select all functionality
  const selectAllCheckbox = document.getElementById('select_all_students');
  const studentCheckboxes = document.querySelectorAll('.student-checkbox');
  
  selectAllCheckbox.addEventListener('change', function() {
    const visibleCheckboxes = document.querySelectorAll('.student-item:not([style*="display: none"]) .student-checkbox');
    visibleCheckboxes.forEach(checkbox => {
      checkbox.checked = this.checked;
    });
  });
  
  // Update select all when individual checkboxes change
  studentCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      const visibleCheckboxes = document.querySelectorAll('.student-item:not([style*="display: none"]) .student-checkbox');
      const checkedVisible = document.querySelectorAll('.student-item:not([style*="display: none"]) .student-checkbox:checked');
      selectAllCheckbox.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedVisible.length;
    });
  });

  // Add event listener for advancement student selection toggle
  const toggleAdvancementBtn = document.getElementById('toggle_student_selection_btn');
  if (toggleAdvancementBtn) {
    toggleAdvancementBtn.addEventListener('click', toggleStudentSelection);
  }
});

// Filter students
function filterStudents() {
  const filterSection = document.getElementById('filter_section');
  filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
  
  if (filterSection.style.display === 'block') {
    // Add event listeners for filters
    const courseFilter = document.getElementById('filter_course');
    const yearFilter = document.getElementById('filter_year');
    const sectionFilter = document.getElementById('filter_section');
    const searchFilter = document.getElementById('filter_search');
    
    [courseFilter, yearFilter, sectionFilter, searchFilter].forEach(filter => {
      filter.addEventListener('input', applyFilters);
    });
  }
}

function applyFilters() {
  const course = document.getElementById('filter_course').value;
  const year = document.getElementById('filter_year').value;
  const section = document.getElementById('filter_section').value;
  const search = document.getElementById('filter_search').value.toLowerCase();
  
  const studentItems = document.querySelectorAll('.student-item');
  let visibleCount = 0;
  
  studentItems.forEach(item => {
    const itemCourse = item.dataset.course;
    const itemYear = item.dataset.year;
    const itemSection = item.dataset.section;
    const itemSearch = item.dataset.search;
    
    const matchCourse = !course || itemCourse === course;
    const matchYear = !year || itemYear === year;
    const matchSection = !section || itemSection === section;
    const matchSearch = !search || itemSearch.includes(search);
    
    if (matchCourse && matchYear && matchSection && matchSearch) {
      item.style.display = 'block';
      visibleCount++;
    } else {
      item.style.display = 'none';
      // Uncheck hidden items
      const checkbox = item.querySelector('.student-checkbox');
      checkbox.checked = false;
    }
  });
  
  // Update select all checkbox
  const selectAllCheckbox = document.getElementById('select_all_students');
  selectAllCheckbox.checked = false;
}

// Graduation confirmation
function confirmGraduation() {
  const method = document.querySelector('input[name="graduation_method"]:checked').value;
  const batch = document.querySelector('input[name="graduation_batch"]').value.trim();
  const batchText = batch ? ` (${batch})` : '';
  
  if (method === 'csv') {
    const fileInput = document.querySelector('input[name="graduation_csv"]');
    const file = fileInput.files[0];
    
    if (!file) {
      Swal.fire({
        icon: 'error',
        title: 'No File Selected',
        text: 'Please upload a CSV file containing students to graduate.'
      });
      return false;
    }
    
    if (!file.name.toLowerCase().endsWith('.csv')) {
      Swal.fire({
        icon: 'error',
        title: 'Invalid File Type',
        text: 'Please upload a valid CSV file.'
      });
      return false;
    }
    
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
      Swal.fire({
        icon: 'error',
        title: 'File Too Large',
        text: 'File size must be less than 5MB.'
      });
      return false;
    }
    
    const fileName = file.name;
    
    Swal.fire({
      title: 'Graduate Students?',
      html: `Are you sure you want to graduate students from <strong>"${fileName}"</strong>${batchText}?<br><br>
             <small class="text-muted">All students in the CSV file will be archived and moved to the Recycle Bin.</small>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-graduation-cap me-1"></i> Yes, Graduate Them',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        showLoadingAndSubmit('Processing CSV File...');
      }
    });
  } else {
    // Manual selection
    const selectedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
      Swal.fire({
        icon: 'error',
        title: 'No Students Selected',
        text: 'Please select at least one student to graduate.'
      });
      return false;
    }
    
    Swal.fire({
      title: 'Graduate Students?',
      html: `Are you sure you want to graduate <strong>${selectedCheckboxes.length} selected student${selectedCheckboxes.length > 1 ? 's' : ''}</strong>${batchText}?<br><br>
             <small class="text-muted">Selected students will be archived and moved to the Recycle Bin.</small>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-graduation-cap me-1"></i> Yes, Graduate Them',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        showLoadingAndSubmit('Graduating Students...');
      }
    });
  }
  
  return false; // Prevent default form submission
}

function showLoadingAndSubmit(message) {
  Swal.fire({
    title: message,
    text: 'Please wait while we process the graduation.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Submit the form
  document.querySelector('form input[name="tab"][value="graduation"]').closest('form').submit();
}

// Student Advancement Functions
function toggleStudentSelection() {
  const section = document.getElementById('advancement_student_selection');
  section.style.display = section.style.display === 'none' ? 'block' : 'none';
  
  if (section.style.display === 'block') {
    // Add event listeners for advancement filters
    const courseFilter = document.getElementById('advancement_filter_course');
    const yearFilter = document.getElementById('advancement_filter_year');
    const sectionFilter = document.getElementById('advancement_filter_section');
    const searchFilter = document.getElementById('advancement_filter_search');
    
    [courseFilter, yearFilter, sectionFilter, searchFilter].forEach(filter => {
      filter.addEventListener('input', applyAdvancementFilters);
    });
    
    // Select all functionality for advancement
    const selectAllCheckbox = document.getElementById('advancement_select_all');
    const studentCheckboxes = document.querySelectorAll('.advancement-student-checkbox');
    
    selectAllCheckbox.addEventListener('change', function() {
      const visibleCheckboxes = document.querySelectorAll('.advancement-student-item:not([style*="display: none"]) .advancement-student-checkbox');
      visibleCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
      });
    });
    
    // Update select all when individual checkboxes change
    studentCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const visibleCheckboxes = document.querySelectorAll('.advancement-student-item:not([style*="display: none"]) .advancement-student-checkbox');
        const checkedVisible = document.querySelectorAll('.advancement-student-item:not([style*="display: none"]) .advancement-student-checkbox:checked');
        selectAllCheckbox.checked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === checkedVisible.length;
      });
    });
  }
}

function applyAdvancementFilters() {
  const course = document.getElementById('advancement_filter_course').value;
  const year = document.getElementById('advancement_filter_year').value;
  const section = document.getElementById('advancement_filter_section').value;
  const search = document.getElementById('advancement_filter_search').value.toLowerCase();
  
  const studentItems = document.querySelectorAll('.advancement-student-item');
  let visibleCount = 0;
  
  studentItems.forEach(item => {
    const itemCourse = item.dataset.course;
    const itemYear = item.dataset.year;
    const itemSection = item.dataset.section;
    const itemSearch = item.dataset.search;
    
    const matchCourse = !course || itemCourse === course;
    const matchYear = !year || itemYear === year;
    const matchSection = !section || itemSection === section;
    const matchSearch = !search || itemSearch.includes(search);
    
    if (matchCourse && matchYear && matchSection && matchSearch) {
      item.style.display = 'block';
      visibleCount++;
    } else {
      item.style.display = 'none';
      // Uncheck hidden items
      const checkbox = item.querySelector('.advancement-student-checkbox');
      checkbox.checked = false;
    }
  });
  
  // Update select all checkbox
  const selectAllCheckbox = document.getElementById('advancement_select_all');
  selectAllCheckbox.checked = false;
}

function confirmAdvancement() {
  const formData = new FormData(event.target);
  
  if (formData.has('advance_all')) {
    const batch = formData.get('advancement_batch') || '';
    const batchText = batch ? ` for ${batch}` : '';
    
    Swal.fire({
      title: 'Advance All Students?',
      html: `Are you sure you want to advance <strong>ALL students</strong> to the next year level${batchText}?<br><br>
             <small class="text-muted">4th year students will be automatically graduated and moved to the Recycle Bin.</small>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#28a745',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-arrow-up me-1"></i> Yes, Advance All',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        showAdvancementLoading('Advancing All Students...');
      }
    });
    
    return false;
  } else if (formData.has('process_failed_students')) {
    const fileInput = document.querySelector('input[name="failed_students_csv"]');
    const file = fileInput.files[0];
    
    if (!file) {
      Swal.fire({
        icon: 'error',
        title: 'No File Selected',
        text: 'Please upload a CSV file containing failed students.'
      });
      return false;
    }
    
    if (!file.name.toLowerCase().endsWith('.csv')) {
      Swal.fire({
        icon: 'error',
        title: 'Invalid File Type',
        text: 'Please upload a valid CSV file.'
      });
      return false;
    }
    
    Swal.fire({
      title: 'Process Failed Students?',
      html: `Process <strong>"${file.name}"</strong> to keep selected students in their current year level?<br><br>
             <small class="text-muted">Students in this file will not advance this year.</small>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#ffc107',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-file-upload me-1"></i> Process File',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        showAdvancementLoading('Processing Failed Students...');
      }
    });
    
    return false;
  } else if (formData.has('advance_except_selected')) {
    const selectedCheckboxes = document.querySelectorAll('.advancement-student-checkbox:checked');
    const batch = formData.get('advancement_batch') || '';
    const batchText = batch ? ` for ${batch}` : '';
    
    Swal.fire({
      title: 'Advance Students with Exceptions?',
      html: `Advance all students except <strong>${selectedCheckboxes.length} selected</strong> to the next year level${batchText}?<br><br>
             <small class="text-muted">Selected students will remain in their current year. 4th year students (not selected) will be graduated.</small>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#007bff',
      cancelButtonColor: '#6c757d',
      confirmButtonText: '<i class="fas fa-arrow-up me-1"></i> Advance with Exceptions',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        showAdvancementLoading('Processing Student Advancement...');
      }
    });
    
    return false;
  }
  
  return true;
}

function showAdvancementLoading(message) {
  Swal.fire({
    title: message,
    text: 'Please wait while we process the student advancement.',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  // Submit the form
  event.target.submit();
}

// Recycle bin functions
function restoreRecord(table, id, name) {
  Swal.fire({
    title: 'Restore Record?',
    text: `Restore "${name}" back to the active list?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, restore'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('recycle_action').value = 'restore';
      document.getElementById('recycle_table').value = table;
      document.getElementById('recycle_id').value = id;
      document.getElementById('recycleForm').submit();
    }
  });
}

function permanentDelete(table, id, name) {
  Swal.fire({
    title: 'Permanently Delete?',
    text: `This will permanently delete "${name}". This action cannot be undone!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete forever',
    input: 'text',
    inputPlaceholder: 'Type "DELETE" to confirm',
    inputValidator: (value) => {
      if (!value || value.toUpperCase() !== 'DELETE') {
        return 'You must type "DELETE" to confirm!';
      }
    }
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('recycle_action').value = 'delete';
      document.getElementById('recycle_table').value = table;
      document.getElementById('recycle_id').value = id;
      document.getElementById('recycleForm').submit();
    }
  });
}

// Auto-switch to recycle bin tab if hash is present
document.addEventListener('DOMContentLoaded', function() {
  if (window.location.hash === '#tabRecycleBin') {
    // Hide tab navigation and show only recycle bin
    const tabNav = document.querySelector('.nav-tabs');
    const tabContent = document.querySelector('.tab-content');
    const recycleBinTab = document.getElementById('tabRecycleBin');
    
    if (tabNav && tabContent && recycleBinTab) {
      // Hide navigation tabs
      tabNav.style.display = 'none';
      
      // Hide all tab panes
      document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('show', 'active');
      });
      
      // Show only recycle bin
      recycleBinTab.classList.add('show', 'active');
      
      // Remove border from tab content since nav is hidden
      tabContent.classList.remove('border', 'border-top-0');
      tabContent.style.padding = '0';
      tabContent.style.marginTop = '1rem';
      
      // Update page title
      const pageTitle = document.querySelector('h3');
      if (pageTitle) {
        pageTitle.innerHTML = '<i class="fas fa-recycle me-2 text-warning"></i>Recycle Bin';
      }
      
      // Add a back button
      const backButton = document.createElement('a');
      backButton.href = 'settings.php';
      backButton.className = 'btn btn-outline-secondary mb-3';
      backButton.innerHTML = '<i class="fas fa-arrow-left me-1"></i> Back to Settings';
      
      const container = document.querySelector('.container-fluid');
      if (container) {
        const titleElement = container.querySelector('h3');
        if (titleElement && titleElement.parentNode) {
          titleElement.parentNode.insertBefore(backButton, titleElement.nextSibling);
        }
      }
    }
  }
});
</script>

<?php include('includes/footer.php'); ?>
