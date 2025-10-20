<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
require_once("../db.php");
require_once("includes/activity_logger.php");
require_once("includes/alert_helper.php");

// Helper functions
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// System constants
$COURSES = ["BSIT", "BSHM"];
$YEARS = ["1st Year", "2nd Year", "3rd Year", "4th Year"];
$SECTIONS = ["A", "B", "C", "D"];

$import_results = [];
$has_errors = false;

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_error_alert('upload', 'CSV file', 'File upload failed');
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        set_error_alert('upload', 'CSV file', 'Please upload a valid CSV file');
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            $header = fgetcsv($handle); // Read header row
            $row_number = 1;
            $success_count = 0;
            $error_count = 0;
            
            // Expected header format
            $expected_headers = [
                'student_code', 'first_name', 'middle_name', 'last_name', 
                'fullname', 'email', 'contact', 'course', 'year_level', 'section'
            ];
            
            // Validate header
            if (!$header || count($header) < count($expected_headers)) {
                set_error_alert('import', 'CSV file', 'Invalid CSV format. Please use the template.');
                $has_errors = true;
            } else {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $row_number++;
                    
                    // Skip empty rows
                    if (count(array_filter($data)) === 0) continue;
                    
                    // Pad data array to match expected columns
                    $data = array_pad($data, count($expected_headers), '');
                    
                    // Extract and validate data
                    $student_code = trim($data[0]);
                    $first_name = trim($data[1]);
                    $middle_name = trim($data[2]);
                    $last_name = trim($data[3]);
                    $fullname = trim($data[4]);
                    $email = trim($data[5]);
                    $contact = trim($data[6]);
                    $course = trim($data[7]);
                    $year_level = trim($data[8]);
                    $section = trim($data[9]);
                    
                    $errors = [];
                    
                    // Validate required fields
                    if (empty($student_code)) $errors[] = "Student code is required";
                    if (empty($first_name)) $errors[] = "First name is required";
                    if (empty($last_name)) $errors[] = "Last name is required";
                    if (empty($fullname)) $errors[] = "Full name is required";
                    if (empty($email)) $errors[] = "Email is required";
                    if (empty($course)) $errors[] = "Course is required";
                    if (empty($year_level)) $errors[] = "Year level is required";
                    if (empty($section)) $errors[] = "Section is required";
                    
                    // Validate email format
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email format";
                    }
                    
                    // Validate course
                    if (!empty($course) && !in_array($course, $COURSES)) {
                        $errors[] = "Invalid course (must be: " . implode(", ", $COURSES) . ")";
                    }
                    
                    // Validate year level
                    if (!empty($year_level) && !in_array($year_level, $YEARS)) {
                        $errors[] = "Invalid year level (must be: " . implode(", ", $YEARS) . ")";
                    }
                    
                    // Validate section
                    if (!empty($section) && !in_array($section, $SECTIONS)) {
                        $errors[] = "Invalid section (must be: " . implode(", ", $SECTIONS) . ")";
                    }
                    
                    // Check for duplicate student code
                    if (!empty($student_code)) {
                        $check_sql = "SELECT id FROM students WHERE student_code = ? AND archived_at IS NULL";
                        $stmt = $conn->prepare($check_sql);
                        $stmt->bind_param("s", $student_code);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            $errors[] = "Student code already exists";
                        }
                        $stmt->close();
                    }
                    
                    // Check for duplicate email
                    if (!empty($email)) {
                        $check_sql = "SELECT id FROM students WHERE email = ? AND archived_at IS NULL";
                        $stmt = $conn->prepare($check_sql);
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            $errors[] = "Email already exists";
                        }
                        $stmt->close();
                    }
                    
                    if (!empty($errors)) {
                        $import_results[] = [
                            'row' => $row_number,
                            'status' => 'error',
                            'student_code' => $student_code,
                            'fullname' => $fullname,
                            'errors' => $errors
                        ];
                        $error_count++;
                        $has_errors = true;
                    } else {
                        // Insert student record
                        $sql = "INSERT INTO students (student_code, first_name, middle_name, last_name, fullname, email, contact, course, year_level, section, date_created) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssssssss", $student_code, $first_name, $middle_name, $last_name, $fullname, $email, $contact, $course, $year_level, $section);
                        
                        if ($stmt->execute()) {
                            $student_id = $conn->insert_id;
                            log_activity($conn, ActivityActions::STUDENT_CREATED, ActivityTargets::STUDENT, $student_id, $fullname, "Student imported via CSV: $student_code - $fullname ($course $year_level $section)");
                            
                            $import_results[] = [
                                'row' => $row_number,
                                'status' => 'success',
                                'student_code' => $student_code,
                                'fullname' => $fullname,
                                'errors' => []
                            ];
                            $success_count++;
                        } else {
                            $import_results[] = [
                                'row' => $row_number,
                                'status' => 'error',
                                'student_code' => $student_code,
                                'fullname' => $fullname,
                                'errors' => ['Database error: ' . $conn->error]
                            ];
                            $error_count++;
                            $has_errors = true;
                        }
                        $stmt->close();
                    }
                }
                
                // Show summary message
                if ($success_count > 0 && $error_count === 0) {
                    set_success_alert('imported', 'students', "$success_count records");
                } elseif ($success_count > 0 && $error_count > 0) {
                    set_alert("Imported $success_count students successfully. $error_count records had errors.", 'warning');
                } elseif ($error_count > 0) {
                    set_error_alert('import', 'students', "All $error_count records had errors");
                }
            }
            
            fclose($handle);
        } else {
            set_error_alert('import', 'CSV file', 'Could not read uploaded file');
        }
    }
}

// Generate CSV template
if (isset($_GET['download_template'])) {
    $filename = "student_import_template_" . date("Ymd") . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    $output = fopen("php://output", "w");
    
    // CSV header
    fputcsv($output, [
        'student_code', 'first_name', 'middle_name', 'last_name', 
        'fullname', 'email', 'contact', 'course', 'year_level', 'section'
    ]);
    
    // Sample data
    fputcsv($output, [
        '2024-0001', 'John', 'M', 'Doe', 'John M. Doe', 'john.doe@example.com', '09123456789', 'BSIT', '1st Year', 'A'
    ]);
    fputcsv($output, [
        '2024-0002', 'Jane', 'S', 'Smith', 'Jane S. Smith', 'jane.smith@example.com', '09987654321', 'BSHM', '2nd Year', 'B'
    ]);
    
    fclose($output);
    exit();
}

include('includes/header.php');
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="m-0">Import Students</h3>
            <div class="text-muted">Import student records from CSV file</div>
        </div>
        <div class="d-flex gap-2">
            <a href="?download_template=1" class="btn btn-outline-secondary">
                <i class="fas fa-download me-1"></i> Download Template
            </a>
            <a href="students.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to Students
            </a>
        </div>
    </div>

    <!-- Instructions Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="m-0"><i class="fas fa-info-circle me-2"></i>Import Instructions</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h6>CSV Format Requirements:</h6>
                    <ul class="mb-3">
                        <li><strong>Required columns:</strong> student_code, first_name, last_name, fullname, email, course, year_level, section</li>
                        <li><strong>Optional columns:</strong> middle_name, contact</li>
                        <li><strong>Course values:</strong> <?php echo implode(", ", $COURSES); ?></li>
                        <li><strong>Year level values:</strong> <?php echo implode(", ", $YEARS); ?></li>
                        <li><strong>Section values:</strong> <?php echo implode(", ", $SECTIONS); ?></li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Tip:</strong> Download the template above to get the correct CSV format with sample data.
                    </div>
                </div>
                <div class="col-md-4">
                    <h6>File Requirements:</h6>
                    <ul>
                        <li>File format: CSV (.csv)</li>
                        <li>Encoding: UTF-8</li>
                        <li>Maximum file size: 10MB</li>
                        <li>No duplicate student codes</li>
                        <li>No duplicate email addresses</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="m-0"><i class="fas fa-upload me-2"></i>Upload CSV File</h5>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="importForm">
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Select a CSV file containing student records to import.</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i>Import Students
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Results -->
    <?php if (!empty($import_results)): ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="m-0">
                <i class="fas fa-list me-2"></i>Import Results
            </h5>
            <span class="badge bg-<?php echo $has_errors ? 'warning' : 'success'; ?>">
                <?php echo count($import_results); ?> records processed
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Row</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 150px;">Student Code</th>
                            <th>Full Name</th>
                            <th>Errors/Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($import_results as $result): ?>
                        <tr class="<?php echo $result['status'] === 'success' ? 'table-success' : 'table-danger'; ?>">
                            <td class="text-center"><?php echo $result['row']; ?></td>
                            <td>
                                <?php if ($result['status'] === 'success'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Success
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i>Error
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?php echo h($result['student_code']); ?></td>
                            <td><?php echo h($result['fullname']); ?></td>
                            <td>
                                <?php if (!empty($result['errors'])): ?>
                                    <ul class="list-unstyled m-0 text-danger small">
                                        <?php foreach ($result['errors'] as $error): ?>
                                            <li><i class="fas fa-exclamation-triangle me-1"></i><?php echo h($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-success small">
                                        <i class="fas fa-check me-1"></i>Student imported successfully
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
// Form validation
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('csv_file');
    const file = fileInput.files[0];
    
    if (!file) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'No File Selected',
            text: 'Please select a CSV file to import.'
        });
        return;
    }
    
    if (!file.name.toLowerCase().endsWith('.csv')) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Please select a valid CSV file.'
        });
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: 'File size must be less than 10MB.'
        });
        return;
    }
    
    // Show loading message
    Swal.fire({
        title: 'Importing Students...',
        text: 'Please wait while we process your file.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
});
</script>

<?php render_alert_script(); ?>

<?php include('includes/footer.php'); ?>