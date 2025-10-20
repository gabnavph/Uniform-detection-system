<?php
// ========================================
// admin/includes/header.php
// System Config Integration (Sidebar Layout)
// ========================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once("../db.php");
require_once("settings_helper.php");

// Dynamic Settings
$system_name = get_setting($conn, 'system_name', 'Uniform Monitoring System');
$school_name = get_setting($conn, 'school_name', 'Your School Name');
$school_logo = get_setting($conn, 'school_logo', ''); // optional
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($system_name); ?></title>
  
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="assets/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <!-- Custom Styles -->
  <link rel="stylesheet" href="assets/css/custom.css">
  
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <style>
    .sidebar-logo {
      padding: 15px;
      text-align: center;
      border-bottom: 1px solid #dee2e6;
      margin-bottom: 10px;
    }
    
    .sidebar-logo img {
      max-width: 60px;
      max-height: 60px;
      margin-bottom: 8px;
    }
    
    .sidebar-title {
      font-weight: 600;
      font-size: 14px;
      color: #495057;
      line-height: 1.2;
    }
    
    .sidebar {
      background-color: #f8f9fa;
      border-right: 1px solid #dee2e6;
      min-height: calc(100vh - 60px);
      transition: all 0.3s ease;
      width: 250px;
      position: relative;
      margin-top: 0;
      z-index: 1010;
    }
    
    .sidebar.collapsed {
      width: 70px;
    }
    
    .sidebar .nav-link {
      color: #495057;
      padding: 8px 15px;
      border-radius: 0;
      transition: all 0.2s;
      white-space: nowrap;
      overflow: hidden;
    }
    
    .sidebar .nav-link:hover {
      background-color: #e9ecef;
      color: #495057;
    }
    
    .sidebar .nav-link.active {
      background-color: #7B1113;
      color: white;
    }
    
    .sidebar.collapsed .nav-link {
      text-align: center;
      padding: 12px 8px;
    }
    
    .sidebar.collapsed .nav-link .nav-text {
      display: none;
    }
    
    .sidebar.collapsed .sidebar-title {
      display: none;
    }
    
    .sidebar.collapsed .section-title {
      display: none !important;
    }
    
    .sidebar.collapsed .sidebar-logo {
      padding: 10px;
      border-bottom: 1px solid #dee2e6;
    }
    
    .main-content {
      padding: 20px;
      transition: all 0.3s ease;
      margin-left: 0;
    }
    
    .sidebar-toggle {
      position: absolute;
      top: 10px;
      right: -15px;
      background: #7B1113;
      color: white;
      border: none;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s;
      z-index: 1000;
    }
    
    .sidebar-toggle:hover {
      background: #5a0c0e;
      transform: scale(1.1);
    }
    
    /* Top Bar Styles */
    .navbar .dropdown-toggle::after {
      margin-left: 0.5em;
    }
    
    .navbar .dropdown-toggle:focus {
      box-shadow: none;
    }
    
    .profile-avatar {
      background: linear-gradient(135deg, #7B1113 0%, #a01517 100%);
      transition: all 0.2s ease;
    }
    
    .profile-avatar:hover {
      transform: scale(1.05);
    }
    
    .navbar .dropdown-menu {
      border: 1px solid rgba(0,0,0,.125);
      border-radius: 0.375rem;
      min-width: 180px;
      margin-top: 0.25rem;
    }
    
    .navbar .dropdown-item {
      padding: 0.5rem 1rem;
      transition: all 0.2s ease;
    }
    
    .navbar .dropdown-item:hover {
      background-color: #f8f9fa;
    }
    
    .navbar .dropdown-item.text-danger:hover {
      background-color: #fff5f5;
    }
    
    @media (max-width: 991.98px) {
      .sidebar {
        display: none;
      }
      .sidebar-toggle {
        display: none;
      }
    }
  </style>
  
  <script>
    function confirmLogout() {
      Swal.fire({
        title: 'Confirm Logout',
        text: 'Are you sure you want to logout?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = 'logout.php';
        }
      });
    }
  </script>
</head>

<body>

<!-- ===== TOP NAVBAR (Mobile Only) ===== -->
<nav class="navbar navbar-expand-lg navbar-dark d-lg-none" style="background:#7B1113;">
  <div class="container-fluid">
    <!-- Brand -->
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <?php if ($school_logo): ?>
        <img src="../<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" style="height: 30px; margin-right: 5px;">
      <?php endif; ?>
      <span class="fw-bold"><?php echo htmlspecialchars($school_name); ?></span>
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-expanded="false">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mobileNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="students.php"><i class="fas fa-users me-2"></i>Students</a></li>
        <li class="nav-item"><a class="nav-link" href="penalties.php"><i class="fas fa-exclamation-triangle me-2"></i>Penalties</a></li>
        <li class="nav-item"><a class="nav-link" href="payments.php"><i class="fas fa-cash-register me-2"></i>Payments</a></li>
        <li class="nav-item"><a class="nav-link" href="logs.php"><i class="fas fa-list me-2"></i>Logs</a></li>
        <li class="nav-item"><a class="nav-link" href="activity_logs.php"><i class="fas fa-history me-2"></i>Activity Logs</a></li>
        <li class="nav-item"><a class="nav-link" href="../scan_uniform.php" target="_blank"><i class="fas fa-qrcode me-2"></i>Scan Uniform</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="fas fa-user-shield me-2"></i>Admin Users</a></li>
        <li class="nav-item"><a class="nav-link" href="reports_daily.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
        
        <!-- Super Admin: Settings -->
        <?php if (is_super_admin($conn)): ?>
        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
        <?php endif; ?>
        
        <li class="nav-item">
          <a class="nav-link text-warning" href="#" onclick="confirmLogout()">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ===== MAIN LAYOUT ===== -->
<div class="container-fluid p-0">
  <div class="row g-0">
    
    <!-- ===== SIDEBAR (Desktop Only) ===== -->
    <aside class="d-none d-lg-block sidebar" id="sidebar">
      
      <!-- Sidebar Toggle Button -->
      <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
      </button>
      
      <!-- Sidebar Logo + Title -->
      <div class="sidebar-logo">
        <?php if ($school_logo): ?>
          <img src="../<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo">
        <?php endif; ?>
        <div class="sidebar-title"><?php echo htmlspecialchars($system_name); ?></div>
      </div>

      <nav class="nav flex-column">
        <!-- Main Navigation -->
        <div class="px-2">
          <small class="text-muted text-uppercase fw-bold px-2 mb-2 d-block section-title">Main</small>
          <a class="nav-link" href="index.php" title="Dashboard">
            <i class="fas fa-home me-2"></i><span class="nav-text">Dashboard</span>
          </a>
          <a class="nav-link" href="students.php" title="Students">
            <i class="fas fa-users me-2"></i><span class="nav-text">Students</span>
          </a>
          <a class="nav-link" href="../scan_uniform.php" target="_blank" title="Scan Uniform">
            <i class="fas fa-qrcode me-2"></i><span class="nav-text">Scan Uniform</span>
          </a>
        </div>

        <!-- Management -->
        <div class="px-2 mt-3">
          <small class="text-muted text-uppercase fw-bold px-2 mb-2 d-block section-title">Management</small>
          <a class="nav-link" href="penalties.php" title="Penalties">
            <i class="fas fa-exclamation-triangle me-2"></i><span class="nav-text">Penalties</span>
          </a>
          <a class="nav-link" href="payments.php" title="Payments">
            <i class="fas fa-cash-register me-2"></i><span class="nav-text">Payments</span>
          </a>
        </div>

        <!-- Reports & Logs -->
        <div class="px-2 mt-3">
          <small class="text-muted text-uppercase fw-bold px-2 mb-2 d-block section-title">Reports & Logs</small>
          <a class="nav-link" href="logs.php" title="Inspection Logs">
            <i class="fas fa-list me-2"></i><span class="nav-text">Inspection Logs</span>
          </a>
          <a class="nav-link" href="activity_logs.php" title="Activity Logs">
            <i class="fas fa-history me-2"></i><span class="nav-text">Activity Logs</span>
          </a>
          <a class="nav-link" href="reports_daily.php" title="Daily Reports">
            <i class="fas fa-chart-bar me-2"></i><span class="nav-text">Daily Reports</span>
          </a>
        </div>

        <!-- Administration -->
        <div class="px-2 mt-3">
          <small class="text-muted text-uppercase fw-bold px-2 mb-2 d-block section-title">Administration</small>
          <a class="nav-link" href="admin_users.php" title="Admin Users">
            <i class="fas fa-user-shield me-2"></i><span class="nav-text">Admin Users</span>
          </a>
          
          <!-- Super Admin: Settings -->
          <?php if (is_super_admin($conn)): ?>
          <a class="nav-link" href="settings.php" title="Settings">
            <i class="fas fa-cog me-2"></i><span class="nav-text">Settings</span>
          </a>
          <?php endif; ?>
        </div>

        <!-- Logout -->
        <div class="px-2 mt-4 border-top pt-3">
          <a class="nav-link text-danger" href="#" onclick="confirmLogout()" title="Logout">
            <i class="fas fa-sign-out-alt me-2"></i><span class="nav-text">Logout</span>
          </a>
        </div>
      </nav>

    </aside>

    <!-- ===== MAIN CONTENT WRAPPER ===== -->
    <main class="col-12 col-lg-10 main-content" id="mainContent">

<!-- Bootstrap JavaScript -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Highlight active menu item and handle sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const filename = currentPath.split('/').pop();
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    
    // Remove active class from all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current page link
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === filename || href.includes(filename))) {
            link.classList.add('active');
        }
    });
    
    // Special handling for dashboard
    if (filename === 'index.php' || filename === '') {
        const dashboardLink = document.querySelector('a[href="index.php"]');
        if (dashboardLink) {
            dashboardLink.classList.add('active');
        }
    }
    
    // Sidebar toggle functionality
    if (sidebarToggle && sidebar) {
        // Check localStorage for sidebar state
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (sidebarCollapsed) {
            sidebar.classList.add('collapsed');
            updateToggleIcon(true);
        }
        
        sidebarToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (isCollapsed) {
                sidebar.classList.remove('collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
                updateToggleIcon(false);
            } else {
                sidebar.classList.add('collapsed');
                localStorage.setItem('sidebarCollapsed', 'true');
                updateToggleIcon(true);
            }
        });
        
        function updateToggleIcon(isCollapsed) {
            const icon = sidebarToggle.querySelector('i');
            if (isCollapsed) {
                icon.className = 'fas fa-chevron-right';
                sidebarToggle.title = 'Expand Sidebar';
            } else {
                icon.className = 'fas fa-bars';
                sidebarToggle.title = 'Collapse Sidebar';
            }
        }
    }
});
</script>
