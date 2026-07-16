<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'dashboard_counter.php';
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details Management - Dashboard</title>
    
    <!-- Cache control meta tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0d1117;
            --ink-soft: #1e2530;
            --ink-muted: #4b5563;
            --ink-faint: #9ca3af;
            --surface: #ffffff;
            --surface-2: #F8FAFC;
            --surface-3: #f1f3f5;
            --border: rgba(0, 0, 0, 0.09);
            --border-md: rgba(0, 0, 0, 0.15);
            --accent: #4F46E5;
            --accent-light: #EEF2FF;
            --accent-dark: #4338CA;
            --secondary: #06B6D4;
            --secondary-light: #ECFEFF;
            --secondary-dark: #0891B2;
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --danger: #F43F5E;
            --danger-light: #FFE4E6;
            --orange: #EA580C;
            --orange-light: #FFEDD5;
            --radius: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.10), 0 4px 12px rgba(0, 0, 0, 0.06);
        }
        
        * {
            font-family: 'Inter', 'Plus Jakarta Sans', sans-serif;
        }
        
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        
        body {
            background: var(--surface-2);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #fff !important;
            font-size: 18px;
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: linear-gradient(180deg, var(--ink-soft) 0%, var(--ink) 100%);
            border-right: 1px solid var(--border);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: 0.875rem 1rem;
            border-radius: var(--radius);
            margin: 4px 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255,255,255,.1);
            transform: translateX(4px);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            box-shadow: var(--shadow-sm);
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            background: var(--surface);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.25rem;
            font-weight: 700;
            font-size: 18px;
            color: var(--ink);
        }
        
        .card-header.bg-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark)) !important;
            color: #fff !important;
            border-bottom-color: transparent !important;
        }
        
        .card-header.bg-success {
            background: linear-gradient(135deg, var(--success), #059669) !important;
            color: #fff !important;
            border-bottom-color: transparent !important;
        }
        
        .card-header.bg-warning {
            background: linear-gradient(135deg, var(--warning), #D97706) !important;
            color: #fff !important;
            border-bottom-color: transparent !important;
        }
        
        .card-header.bg-danger {
            background: linear-gradient(135deg, var(--danger), #E11D48) !important;
            color: #fff !important;
            border-bottom-color: transparent !important;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-sm);
        }
        
        .search-result {
            max-height: 400px;
            overflow-y: auto;
        }

        .student-photo-admin {
            width: 96px;
            height: 96px;
            border-radius: 16px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: 800;
            box-shadow: var(--shadow-sm);
        }

        .student-photo-admin img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .student-photo-preview {
            width: 110px;
            height: 110px;
            border-radius: 18px;
            overflow: hidden;
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-faint);
            font-size: 34px;
        }

        .student-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .upload-section {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }
        
        .drop-zone {
            border: 2px dashed var(--accent);
            border-radius: var(--radius);
            padding: 2rem;
            background: var(--accent-light);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .drop-zone:hover {
            border-color: var(--accent-dark);
            background: #E0E7FF;
        }
        
        .drop-zone.dragover {
            border-color: var(--accent-dark);
            background: #C7D2FE;
            transform: scale(1.02);
        }
        
        .btn {
            border-radius: var(--radius);
            font-weight: 600;
            padding: 0.625rem 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:active::after {
            width: 300px;
            height: 300px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-dark), var(--accent));
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            border: none;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .form-control {
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.5rem;
        }
        
        h2 {
            font-weight: 700;
            color: var(--ink);
            font-size: 28px;
        }
        
        .section {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            h2 {
                font-size: 22px;
            }
            
            .card-header {
                font-size: 16px;
                padding: 0.875rem 1rem;
            }
            
            .upload-section {
                padding: 1rem;
            }
            
            .drop-zone {
                padding: 1.5rem;
            }
            
            .sidebar {
                position: fixed;
                top: 56px;
                left: -280px;
                width: 280px;
                height: calc(100vh - 56px);
                z-index: 1000;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 56px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .col-md-10 {
                width: 100%;
                padding: 0 1rem;
                margin-left: 0 !important;
            }
            
            .search-result {
                max-height: 300px;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 16px;
            }
            
            h2 {
                font-size: 18px;
            }
            
            .card {
                border-radius: 8px;
            }
            
            .upload-section {
                padding: 0.75rem;
                border-radius: 8px;
            }
            
            .drop-zone {
                padding: 1rem;
                font-size: 0.875rem;
            }
            
            .form-control {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
            
            .form-label {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--accent), var(--accent-dark)); box-shadow: var(--shadow);">
        <div class="container-fluid">
            <button class="navbar-toggler d-md-none me-2" type="button" onclick="toggleSidebar()">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Student Management System
            </a>
            <button class="navbar-toggler d-none d-md-block" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="openChangePassword()">
                            <i class="fas fa-key me-1"></i>Change Password
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" onclick="logout()">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar" id="sidebar">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#search" onclick="showSection('search')">
                                <i class="fas fa-search me-2"></i>Search Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#upload" onclick="showSection('upload')">
                                <i class="fas fa-upload me-2"></i>Upload Data
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#reports" onclick="showSection('reports')">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#enrollments" onclick="showSection('enrollments')">
                                <i class="fas fa-user-graduate me-2"></i>Course Enrollments
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4 main-content">
                <!-- Student Search Section -->
                <div id="search-section" class="section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-search me-2"></i>Student Search</h2>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rollNumber" class="form-label">Roll Number</label>
                                    <input type="text" class="form-control" id="rollNumber" placeholder="Enter roll number">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button class="btn btn-primary me-2" onclick="searchStudent()">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                    <button class="btn btn-secondary" onclick="clearSearch()">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Results -->
                    <div id="searchResults" class="mt-4" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Student Details</h5>
                            </div>
                            <div class="card-body search-result">
                                <div id="studentInfo"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Section -->
                <div id="upload-section" class="section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-upload me-2"></i>Data Upload</h2>
                    </div>

                    <div class="upload-section">
                        <div class="row">
                            <!-- Attendance Upload -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Attendance Upload</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="attendanceForm" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label">Academic Year</label>
                                                <input type="text" class="form-control" id="attendanceAcademicYear" name="academic_year" placeholder="e.g., 2025-26" required>
                                            </div>
                                            <div class="mb-3">
                                                <div class="drop-zone" id="attendanceDrop">
                                                    <div class="small text-muted mb-2">Drag & drop CSV/Excel file(s) here, or click to select.</div>
                                                    <input type="file" class="form-control" id="attendanceFile" name="attendanceFile" accept=".csv,.xlsx,.xls" multiple required>
                                                </div>
                                                <div class="form-text">
                                                    Upload CSV or Excel attendance data. The table should include Program, Branch, Year, Semester, Section, Date, Roll No, subject codes, Total, and % columns.
                                                </div>
                                                <div class="form-text" id="attendanceFileNameHelp">
                                                    Filename/session format example: <b>jan2s22026.csv</b>, <b>feb2s22026.csv</b> (month should start with <b>jan/feb/.../dec</b>, year at end like <b>2026</b>).
                                                    <div class="small text-muted">Session name preview: <span class="fw-bold" id="attendanceSessionPreview">-</span></div>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-upload me-1"></i>Upload Attendance
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <!-- Mid Marks Upload -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Mid Marks CSV</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="midmarksForm" enctype="multipart/form-data">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">College Name (optional)</label>
                                                    <input type="text" class="form-control" id="midCollegeName" name="college_name" placeholder="College name">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Semester Info (optional)</label>
                                                    <input type="text" class="form-control" id="midSemesterInfo" name="semester_info" placeholder="e.g., II B.Tech II Sem">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Academic Year (optional)</label>
                                                    <input type="text" class="form-control" id="midAcademicYear" name="academic_year" placeholder="e.g., 2025-26">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Section (optional)</label>
                                                    <input type="text" class="form-control" id="midSection" name="section" placeholder="e.g., A">
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label">Details (optional)</label>
                                                    <input type="text" class="form-control" id="midDetails" name="details" placeholder="Any notes about this upload">
                                                </div>
                                                <div class="col-12">
                                                    <div class="drop-zone" id="midmarksDrop">
                                                        <div class="small text-muted mb-2">Drag & drop CSV file(s) here, or click to select.</div>
                                                        <input type="file" class="form-control" id="midmarksFile" name="midmarksFile" accept=".csv,.xlsx,.xls" multiple required>
                                                    </div>
                                                    <div class="form-text">
                                                        Expected format: Excel/CSV with 2-row header (Row 1: Programm, Branch, Year, Semester, Section, Date, S.No, Hallticket, subject codes..., Total, Per.(%); Row 2: B.TECH, IT, II, I, I, 2025-2026, 1, 24501A12029, marks...). The system auto-extracts semester info, academic year, section, and department from the file.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="fas fa-upload me-1"></i>Upload Mid Marks
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Attendance Uploads (Manage)</h5>
                                        <button class="btn btn-sm btn-outline-primary" type="button" id="refreshAttendanceUploads">
                                            <i class="fas fa-rotate me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="small text-muted mb-2">Delete will remove rows from <b>attendance_uploads</b>, <b>attendance_summary</b>, and <b>attendance_subjects</b> for the selected file.</div>

                                        <div id="attendanceUploadsStatus" class="alert d-none" role="alert"></div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>FILE NAME</th>
                                                        <th>SESSION NAME</th>
                                                        <th>ACADEMIC YEAR</th>
                                                        <th>SEMESTER</th>
                                                        <th>BRANCH</th>
                                                        <th>SECTION</th>
                                                        <th>UPLOADED</th>
                                                        <th class="text-end"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="attendanceUploadsBody">
                                                    <tr><td colspan="7" class="text-muted">Loading...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Mid Marks Uploads (Manage)</h5>
                                        <button class="btn btn-sm btn-outline-primary" type="button" id="refreshMidmarksUploads">
                                            <i class="fas fa-rotate me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="small text-muted mb-2">Delete will remove rows from <b>midmarks_uploads</b> and <b>midmarks_subject_marks</b> for the selected file.</div>

                                        <div id="midmarksUploadsStatus" class="alert d-none" role="alert"></div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>FILE NAME</th>
                                                        <th>SEMESTER INFO</th>
                                                        <th>ACADEMIC YEAR</th>
                                                        <th>SECTION</th>
                                                        <th>UPLOADED</th>
                                                        <th class="text-end"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="midmarksUploadsBody">
                                                    <tr><td colspan="6" class="text-muted">Loading...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Status -->
                        <div id="uploadStatus" class="mt-3" style="display: none;"></div>
                    </div>
                </div>

                <!-- Reports Section -->
                <div id="reports-section" class="section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-chart-bar me-2"></i>Reports</h2>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted">Reports functionality will be implemented here.</p>
                        </div>
                    </div>
                </div>

                <!-- Course Enrollments Section -->
                <div id="enrollments-section" class="section" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-user-graduate me-2"></i>Course Enrollments</h2>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Manage Student Course Enrollments</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Configure which courses each student is enrolled in per semester. This filters attendance data to show only enrolled courses.</p>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Roll Number</label>
                                    <input type="text" class="form-control" id="enrollRollNo" placeholder="Enter roll number">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Academic Year</label>
                                    <select class="form-select" id="enrollAcademicYear">
                                        <option value="">Select</option>
                                        <option value="2025-26">2025-26</option>
                                        <option value="2024-25">2024-25</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="enrollYear">
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Semester</label>
                                    <select class="form-select" id="enrollSemester">
                                        <option value="">Select</option>
                                        <option value="1-1">1-1</option>
                                        <option value="1-2">1-2</option>
                                        <option value="2-1">2-1</option>
                                        <option value="2-2">2-2</option>
                                        <option value="3-1">3-1</option>
                                        <option value="3-2">3-2</option>
                                        <option value="4-1">4-1</option>
                                        <option value="4-2">4-2</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button class="btn btn-primary me-2" onclick="loadStudentEnrollments()">
                                        <i class="fas fa-search me-1"></i>Load
                                    </button>
                                    <button class="btn btn-success" onclick="showBulkImportModal()">
                                        <i class="fas fa-upload me-1"></i>Bulk Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Enrolled Courses</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="showAddEnrollmentModal()">
                                <i class="fas fa-plus me-1"></i>Add Course
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="enrollmentsStatus" class="alert d-none" role="alert"></div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ROLL NO</th>
                                            <th>ACADEMIC YEAR</th>
                                            <th>YEAR</th>
                                            <th>SEMESTER</th>
                                            <th>SUBJECT CODE</th>
                                            <th>SUBJECT NAME</th>
                                            <th class="text-end">ACTIONS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="enrollmentsBody">
                                        <tr><td colspan="7" class="text-muted">Load a student to view enrollments</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="changePasswordForm">
                        <div class="mb-3">
                            <label for="cpCurrent" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="cpCurrent" autocomplete="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label for="cpNew" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="cpNew" autocomplete="new-password" required>
                        </div>
                        <div class="mb-3">
                            <label for="cpConfirm" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="cpConfirm" autocomplete="new-password" required>
                        </div>
                        <div id="cpMsg" class="alert d-none" role="alert"></div>
                        <button id="cpSubmit" type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDobModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Date of Birth</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDobForm">
                        <input type="hidden" id="editDobRollNo" name="roll_no">
                        <div class="mb-3">
                            <label for="editDob" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="editDob" name="date_of_birth" required>
                        </div>
                        <div id="editDobMsg" class="alert d-none" role="alert"></div>
                        <button id="editDobSubmit" type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i>Update Date of Birth
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editPhotoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPhotoForm" enctype="multipart/form-data">
                        <input type="hidden" id="editPhotoRollNo" name="roll_no">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="student-photo-preview" id="editPhotoPreview">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <div class="fw-bold" id="editPhotoStudentName">Student</div>
                                <div class="small text-muted font-mono" id="editPhotoRollLabel"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="studentPhotoFile" class="form-label">Profile Photo</label>
                            <input type="file" class="form-control" id="studentPhotoFile" name="student_photo" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Use JPG, PNG, or WebP. Maximum size: 2 MB.</div>
                        </div>
                        <div id="editPhotoMsg" class="alert d-none" role="alert"></div>
                        <div class="d-flex gap-2">
                            <button id="editPhotoSubmit" type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-upload me-1"></i>Upload Photo
                            </button>
                            <button id="removePhotoBtn" type="button" class="btn btn-outline-danger">
                                <i class="fas fa-trash me-1"></i>Remove
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Show different sections
        function showSection(section) {
            $('.section').hide();
            $('#' + section + '-section').show();
            
            $('.sidebar .nav-link').removeClass('active');
            $('[href="#' + section + '"]').addClass('active');
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        function openChangePassword() {
            const modalEl = document.getElementById('changePasswordModal');
            if (!modalEl || !window.bootstrap) return;
            $('#changePasswordForm')[0].reset();
            $('#cpMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');
            $('#cpSubmit').prop('disabled', false);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        $('#changePasswordForm').on('submit', function(e){
            e.preventDefault();

            const current = $('#cpCurrent').val();
            const next = $('#cpNew').val();
            const confirmPw = $('#cpConfirm').val();

            if (!current || !next || !confirmPw) return;
            if (next !== confirmPw) {
                $('#cpMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('New password and confirm password do not match');
                return;
            }
            if (String(next).length < 6) {
                $('#cpMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('New password must be at least 6 characters');
                return;
            }

            $('#cpSubmit').prop('disabled', true);
            $('#cpMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');

            $.ajax({
                url: 'change_password.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    current_password: current,
                    new_password: next,
                    confirm_password: confirmPw
                },
                success: function(res){
                    if(res && res.success){
                        $('#cpMsg').removeClass('d-none alert-danger').addClass('alert alert-success').text('Password updated successfully');
                        $('#changePasswordForm')[0].reset();
                    } else {
                        $('#cpMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text((res && res.message) ? res.message : 'Failed to update password');
                    }
                },
                error: function(){
                    $('#cpMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('Failed to update password due to server error');
                },
                complete: function(){
                    $('#cpSubmit').prop('disabled', false);
                }
            });
        });

        // Edit Date of Birth
        function editDateOfBirth(rollNo, currentDob) {
            const modalEl = document.getElementById('editDobModal');
            if (!modalEl || !window.bootstrap) return;
            $('#editDobRollNo').val(rollNo);
            $('#editDob').val(currentDob);
            $('#editDobMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');
            $('#editDobSubmit').prop('disabled', false);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        $('#editDobForm').on('submit', function(e){
            e.preventDefault();

            const rollNo = $('#editDobRollNo').val();
            const dob = $('#editDob').val();

            if (!rollNo || !dob) return;

            $('#editDobSubmit').prop('disabled', true);
            $('#editDobMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');

            $.ajax({
                url: 'update_dob.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    roll_no: rollNo,
                    date_of_birth: dob
                },
                success: function(res){
                    if(res && res.success){
                        $('#editDobMsg').removeClass('d-none alert-danger').addClass('alert alert-success').text('Date of birth updated successfully');
                        // Refresh student info
                        searchStudent();
                        setTimeout(() => {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('editDobModal')).hide();
                        }, 1000);
                    } else {
                        $('#editDobMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text((res && res.message) ? res.message : 'Failed to update date of birth');
                    }
                },
                error: function(){
                    $('#editDobMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('Failed to update date of birth due to server error');
                },
                complete: function(){
                    $('#editDobSubmit').prop('disabled', false);
                }
            });
        });

        function escapeAttr(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
            });
        }

        function studentInitials(name, rollNo) {
            const text = String(name || rollNo || 'ST').trim();
            const initials = text.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('');
            return initials || 'ST';
        }

        function renderAdminPhoto(photoPath, name, rollNo) {
            if (photoPath) {
                return `<img src="${escapeAttr(photoPath)}" alt="${escapeAttr(name)} photo" onerror="this.parentElement.textContent='${studentInitials(name, rollNo)}'; this.remove();">`;
            }
            return `<span>${studentInitials(name, rollNo)}</span>`;
        }

        function setPhotoPreview(photoPath) {
            const preview = $('#editPhotoPreview');
            if (photoPath) {
                preview.html(`<img src="${escapeAttr(photoPath)}" alt="Selected profile photo">`);
            } else {
                preview.html('<i class="fas fa-user-graduate"></i>');
            }
        }

        function editProfilePhoto(rollNo, studentName, currentPhoto) {
            const modalEl = document.getElementById('editPhotoModal');
            if (!modalEl || !window.bootstrap) return;
            $('#editPhotoRollNo').val(rollNo);
            $('#editPhotoStudentName').text(studentName || 'Student');
            $('#editPhotoRollLabel').text(rollNo || '');
            $('#studentPhotoFile').val('');
            $('#editPhotoMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');
            $('#editPhotoSubmit, #removePhotoBtn').prop('disabled', false);
            $('#removePhotoBtn').toggleClass('d-none', !currentPhoto);
            setPhotoPreview(currentPhoto || '');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        $('#studentPhotoFile').on('change', function(){
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;
            setPhotoPreview(URL.createObjectURL(file));
        });

        $('#editPhotoForm').on('submit', function(e){
            e.preventDefault();

            const fileInput = $('#studentPhotoFile')[0];
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                $('#editPhotoMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('Please select a photo');
                return;
            }

            const formData = new FormData(this);
            $('#editPhotoSubmit, #removePhotoBtn').prop('disabled', true);
            $('#editPhotoMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');

            $.ajax({
                url: 'update_student_photo.php',
                method: 'POST',
                dataType: 'json',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res){
                    if (res && res.success) {
                        $('#editPhotoMsg').removeClass('d-none alert-danger').addClass('alert alert-success').text('Profile photo updated successfully');
                        searchStudent();
                        setTimeout(() => {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('editPhotoModal')).hide();
                        }, 900);
                    } else {
                        $('#editPhotoMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text((res && res.message) ? res.message : 'Failed to update profile photo');
                    }
                },
                error: function(){
                    $('#editPhotoMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('Failed to update profile photo due to server error');
                },
                complete: function(){
                    $('#editPhotoSubmit, #removePhotoBtn').prop('disabled', false);
                }
            });
        });

        $('#removePhotoBtn').on('click', function(){
            const rollNo = $('#editPhotoRollNo').val();
            if (!rollNo || !confirm('Remove this profile photo?')) return;

            $('#editPhotoSubmit, #removePhotoBtn').prop('disabled', true);
            $('#editPhotoMsg').addClass('d-none').removeClass('alert-success alert-danger').text('');

            $.ajax({
                url: 'update_student_photo.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    roll_no: rollNo,
                    remove_photo: '1'
                },
                success: function(res){
                    if (res && res.success) {
                        $('#editPhotoMsg').removeClass('d-none alert-danger').addClass('alert alert-success').text('Profile photo removed');
                        setPhotoPreview('');
                        searchStudent();
                        setTimeout(() => {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('editPhotoModal')).hide();
                        }, 900);
                    } else {
                        $('#editPhotoMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text((res && res.message) ? res.message : 'Failed to remove profile photo');
                    }
                },
                error: function(){
                    $('#editPhotoMsg').removeClass('d-none alert-success').addClass('alert alert-danger').text('Failed to remove profile photo due to server error');
                },
                complete: function(){
                    $('#editPhotoSubmit, #removePhotoBtn').prop('disabled', false);
                }
            });
        });

        // Search student by roll number
        function searchStudent() {
            const rollNumber = $('#rollNumber').val().trim();
            if (!rollNumber) {
                alert('Please enter a roll number');
                return;
            }

            $.ajax({
                url: 'search_student.php',
                method: 'POST',
                data: { roll_number: rollNumber },
                success: function(response) {
                    if (response.success) {
                        displayStudentInfo(response.data);
                        $('#searchResults').show();
                    } else {
                        alert('Student not found or error occurred: ' + response.message);
                        $('#searchResults').hide();
                    }
                },
                error: function() {
                    alert('Error occurred while searching');
                }
            });
        }

        function getGradeColor(grade) {
    const gradeUpper = (grade || '').toString().toUpperCase().trim();
    if (gradeUpper === 'F') {
        return 'danger'; // Red for F grade
    } else {
        return 'success'; // Light green for all other grades
    }
}

function getSubjectName(subjectCode) {
    const subjectMap = {
        '23hs1301__3_': 'Human Skills',
        '23bs1305__3_': 'Business Skills',
        '23es1304__3_': 'Environmental Studies',
        '23it3301__3_': 'IT Fundamentals',
        '23it3302__3_': 'Programming Basics',
        '23it3351__1_5_': 'Web Development',
        '23it3352__1_5_': 'Database Management',
        '23so8355__2_': 'Software Engineering',
        '23ac1301__0_': 'Accounting Basics',
        '23it3501__3_': 'Advanced Programming',
        '23it3502__3_': 'Data Structures',
        '23it3503__3_': 'Algorithms',
        '23hs1501__3_': 'Communication Skills',
        '23it4511__3_': 'Network Security',
        '23it3591__2_': 'Machine Learning',
        '23it3551__1_5_': 'Cloud Computing',
        '23it3552__1_5_': 'DevOps',
        '23so8555__2_': 'Mobile Development',
        '23es1553__1_': 'Renewable Energy',
        '20it4701c__3_': 'IT Project Management',
        '20it4702e__3_': 'IT Ethics',
        '20it4703e__3_': 'Digital Marketing',
        '20hs7701b__3_': 'Professional Ethics',
        '20me2701b__3_': 'Mechanical Engineering',
        '20ce2702a__3_': 'Computer Engineering',
        '20sa8756__2_': 'System Administration',
        '20it3781b__3_': 'Cybersecurity'
    };
    
    return subjectMap[subjectCode] || subjectCode; // Return original code if not found
}

        // Display student information
        function displayStudentInfo(data) {
            const rollNo = data.student.roll_no || data.student.roll_number;
            const studentName = data.student.student_name || data.student.name || 'Student';
            const currentPhoto = data.student.student_photo || '';
            const dob = data.student.date_of_birth || '';
            const rollArg = escapeAttr(JSON.stringify(rollNo));
            const nameArg = escapeAttr(JSON.stringify(studentName));
            const photoArg = escapeAttr(JSON.stringify(currentPhoto));
            const dobArg = escapeAttr(JSON.stringify(dob));
            let html = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="student-photo-admin">
                                ${renderAdminPhoto(currentPhoto, studentName, rollNo)}
                            </div>
                            <div>
                                <h5 class="mb-1">Basic Information</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="editProfilePhoto(${rollArg}, ${nameArg}, ${photoArg})">
                                    <i class="fas fa-camera me-1"></i>Profile Photo
                                </button>
                            </div>
                        </div>
                        <table class="table table-sm">
            `;
            
            // Only show roll number (always available)
            html += `<tr><th>Roll Number:</th><td>${rollNo}</td></tr>`;
            
            // Only show name if available
            if (data.student.student_name && data.student.student_name !== 'N/A') {
                html += `<tr><th>Name:</th><td>${data.student.student_name}</td></tr>`;
            } else if (data.student.name && data.student.name !== 'N/A') {
                html += `<tr><th>Name:</th><td>${data.student.name}</td></tr>`;
            }
            
            // Only show email if available
            if (data.student.email && data.student.email !== 'N/A') {
                html += `<tr><th>Email:</th><td>${data.student.email}</td></tr>`;
            }
            
            // Only show phone if available
            if (data.student.phone && data.student.phone !== 'N/A') {
                html += `<tr><th>Phone:</th><td>${data.student.phone}</td></tr>`;
            }
            
            // Show date of birth
            const dobDisplay = dob || 'Not set';
            html += `<tr><th>Date of Birth:</th><td>${dobDisplay} <button class="btn btn-sm btn-outline-primary ms-2" onclick="editDateOfBirth(${rollArg}, ${dobArg})"><i class="fas fa-edit"></i> Edit</button></td></tr>`;
            
            html += `
                    </table>
                </div>
                <div class="col-md-6">
                    <h5>Attendance Summary</h5>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h4 class="text-primary">${data.attendance.total}</h4>
                                    <small>Total Days</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-success">
                                <div class="card-body">
                                    <h4 class="text-white">${data.attendance.present}</h4>
                                    <small>Present</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card bg-danger">
                                <div class="card-body">
                                    <h4 class="text-white">${data.attendance.absent}</h4>
                                    <small>Absent</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

            if (data.marks && data.marks.length > 0) {
                html += `
                    <h5>Academic Performance</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Marks</th>
                                    <th>Exam Type</th>
                                    <th>Semester</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                data.marks.forEach(mark => {
                    html += `
                        <tr>
                            <td>${mark.subject}</td>
                            <td>${mark.marks}</td>
                            <td>${mark.exam_type || 'N/A'}</td>
                            <td>${mark.semester || 'N/A'}</td>
                        </tr>
                    `;
                });
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                html += '<p class="text-muted">No marks data available for this student.</p>';
            }

            // Display student results if available
            if (data.student_results && data.student_results.length > 0) {
                html += `
                    <h5>Student Results</h5>
                    <div class="card data-card mb-4">
                        <div class="card-body">
                `;
                
                data.student_results.forEach(result => {
                    // Get subject marks (exclude basic columns)
                    const subjectColumns = Object.keys(result).filter(key => 
                        !['id', 'roll_no', 'student_name', 'semester', 'department', 'upload_date', 'file_name', 'created_at', 'updated_at'].includes(key)
                    );
                    
                    const subjectMarks = subjectColumns.map(subject => {
                        const mark = result[subject];
                        return mark && typeof mark === 'string' && mark.trim() && mark !== 'N/A' ? `${subject}: ${mark}` : null;
                    }).filter(mark => mark !== null);
                    
                    html += `
                    <div class="student-result-item mb-4 p-3 border rounded bg-light">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="roll-number-display bg-warning text-dark rounded-circle p-3 mb-2">
                                        <h4 class="mb-0">${result.roll_no}</h4>
                                    </div>
                                    <small class="text-muted">Roll Number</small>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <small class="text-muted">Student Name</small>
                                        <div class="fw-bold">${result.student_name && result.student_name !== 'N/A' ? result.student_name : '-'}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Semester</small>
                                        <div class="fw-bold">${result.semester && result.semester !== 'N/A' ? result.semester : '-'}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Department</small>
                                        <div class="fw-bold">${result.department && result.department !== 'N/A' ? result.department : '-'}</div>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">Upload Date</small>
                                        <div class="fw-bold">${new Date(result.upload_date).toLocaleDateString()}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (subjectMarks.length > 0) {
                        html += `
                        <div class="subject-grades-section">
                            <h6 class="text-muted mb-3">Subject Grades</h6>
                            <div class="d-flex flex-wrap gap-2">
                                ${subjectMarks.map(mark => {
                                    const [subject, grade] = mark.split(': ');
                                    const gradeColor = getGradeColor(grade);
                                    return `
                                    <div class="grade-item text-center">
                                        <div class="grade-badge badge bg-${gradeColor} text-white p-2 mb-1">
                                            <div class="fs-6 fw-bold">${grade}</div>
                                        </div>
                                        <div class="subject-info">
                                            <small class="text-secondary">${subject.toUpperCase()}</small>
                                        </div>
                                    </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                        `;
                    }
                    
                    html += `</div>`;
                });
                
                html += `
                        </div>
                    </div>
                `;
            } else {
                html += '<p class="text-muted">No student results data available for this roll number.</p>';
            }

            $('#studentInfo').html(html);
        }

        // Clear search
        function clearSearch() {
            $('#rollNumber').val('');
            $('#searchResults').hide();
        }

        // Upload forms
        function sanitizeSessionNameFromFilename(filename) {
            const base = String(filename || '').replace(/\.[^.]+$/, '');
            let s = base.replace(/[^a-zA-Z0-9_]/g, '_');
            s = s.toLowerCase().slice(0, 64);
            return s;
        }

        function updateAttendanceSessionPreview() {
            const fileInput = $('#attendanceFile')[0];
            const el = $('#attendanceSessionPreview');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                el.text('-');
                return;
            }
            const files = Array.from(fileInput.files || []);
            const first = files[0] ? (files[0].name || '') : '';
            const preview = sanitizeSessionNameFromFilename(first) || '-';
            el.text(files.length > 1 ? `${preview} (+${files.length - 1} more)` : preview);
        }

        $('#attendanceFile').on('change', updateAttendanceSessionPreview);

        function bindDropZone(zoneSelector, inputSelector, onFilesApplied) {
            const zone = $(zoneSelector);
            const input = $(inputSelector);
            if (!zone.length || !input.length) return;

            zone.on('dragenter dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.addClass('dragover');
            });

            zone.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                zone.removeClass('dragover');
            });

            zone.on('drop', function(e) {
                const dtFiles = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                if (!dtFiles || !dtFiles.length) return;

                try {
                    const dt = new DataTransfer();
                    Array.from(dtFiles).forEach(f => dt.items.add(f));
                    input[0].files = dt.files;
                } catch (err) {
                    // Fallback: if DataTransfer is unavailable, user can still click-to-select.
                    return;
                }

                input.trigger('change');
                if (typeof onFilesApplied === 'function') {
                    onFilesApplied(input[0].files);
                }
            });
        }

        bindDropZone('#attendanceDrop', '#attendanceFile', function(){
            updateAttendanceSessionPreview();
        });

        bindDropZone('#midmarksDrop', '#midmarksFile');

        function uploadSingleFile(type, formData) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'upload_' + type + '.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr) {
                        reject(xhr);
                    }
                });
            });
        }

        async function uploadMultipleFiles(type, files, buildFormData) {
            const list = Array.from(files || []);
            if (!list.length) {
                $('#uploadStatus').html('<div class="alert alert-warning">Please select a file to upload</div>').show();
                return;
            }

            let ok = 0;
            let failed = 0;
            const failSamples = [];

            for (let i = 0; i < list.length; i++) {
                const f = list[i];
                $('#uploadStatus').html(`<div class="alert alert-info">Uploading ${i + 1}/${list.length}: <b>${f.name}</b> ...</div>`).show();

                try {
                    const res = await uploadSingleFile(type, buildFormData(f));
                    if (res && res.success) {
                        ok++;
                    } else {
                        failed++;
                        if (failSamples.length < 3) {
                            failSamples.push(`${f.name}: ${(res && res.message) ? res.message : 'Upload failed'}`);
                        }
                    }
                } catch (e) {
                    failed++;
                    if (failSamples.length < 3) {
                        const serverMessage = e && e.responseJSON && (e.responseJSON.error || e.responseJSON.message);
                        failSamples.push(`${f.name}: ${serverMessage || 'server error'}`);
                    }
                }
            }

            let msg = `Finished uploading ${list.length} file(s). Success: ${ok}. Failed: ${failed}.`;
            if (failSamples.length) {
                msg += ' Sample: ' + failSamples.join(' | ');
            }
            $('#uploadStatus').html(`<div class="alert ${failed ? 'alert-warning' : 'alert-success'}">${msg}</div>`).show();

            if (type === 'attendance') {
                loadAttendanceUploads();
            } else if (type === 'midmarks') {
                loadMidmarksUploads();
            }
        }

        $('#attendanceForm').on('submit', function(e) {
            e.preventDefault();
            const fileInput = $('#attendanceFile')[0];
            const files = fileInput && fileInput.files ? fileInput.files : [];
            const academicYear = ($('#attendanceAcademicYear').val() || '').trim();
            if (!academicYear) {
                $('#uploadStatus').html('<div class="alert alert-warning">Please enter the academic year for attendance upload</div>').show();
                return;
            }
            uploadMultipleFiles('attendance', files, (file) => {
                const formData = new FormData();
                formData.append('attendanceFile', file);
                formData.append('academic_year', academicYear);
                return formData;
            });
        });

        $('#midmarksForm').on('submit', function(e) {
            e.preventDefault();
            const fileInput = $('#midmarksFile')[0];
            const files = fileInput && fileInput.files ? fileInput.files : [];
            const meta = {
                college_name: $('#midCollegeName').val() || '',
                semester_info: $('#midSemesterInfo').val() || '',
                academic_year: $('#midAcademicYear').val() || '',
                section: $('#midSection').val() || '',
                details: $('#midDetails').val() || '',
            };
            uploadMultipleFiles('midmarks', files, (file) => {
                const formData = new FormData();
                formData.append('midmarksFile', file);
                formData.append('college_name', meta.college_name);
                formData.append('semester_info', meta.semester_info);
                formData.append('academic_year', meta.academic_year);
                formData.append('section', meta.section);
                formData.append('details', meta.details);
                return formData;
            });
        });

        // uploadFile replaced by uploadMultipleFiles + uploadSingleFile

        function showMidmarksUploadsStatus(type, message) {
            const el = $('#midmarksUploadsStatus');
            el.removeClass('d-none alert-success alert-danger alert-info');
            el.addClass('alert-' + type);
            el.text(message);
        }

        function loadMidmarksUploads() {
            const body = $('#midmarksUploadsBody');
            if (!body.length) return;
            body.html('<tr><td colspan="6" class="text-muted">Loading...</td></tr>');

            $.ajax({
                url: 'midmarks_manage.php',
                method: 'GET',
                data: { action: 'list' },
                dataType: 'json',
                success: function(res) {
                    if (!res || !res.success) {
                        body.html('<tr><td colspan="6" class="text-danger">Failed to load uploads</td></tr>');
                        return;
                    }
                    const rows = Array.isArray(res.data) ? res.data : [];
                    if (!rows.length) {
                        body.html('<tr><td colspan="6" class="text-muted">No uploads found.</td></tr>');
                        return;
                    }

                    const html = rows.map(r => {
                        const id = r.id;
                        const fileName = r.file_name || '';
                        const semesterInfo = r.semester_info || '';
                        const academicYear = r.academic_year || '';
                        const section = r.section || '';
                        const createdAt = r.created_at || '';

                        return `
                            <tr>
                                <td>${fileName}</td>
                                <td>${semesterInfo}</td>
                                <td>${academicYear}</td>
                                <td>${section}</td>
                                <td>${createdAt}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteMidmarksUpload(${id}, '${String(fileName).replace(/'/g, "\\'")}')">Delete</button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                    body.html(html);
                },
                error: function() {
                    body.html('<tr><td colspan="6" class="text-danger">Failed to load uploads</td></tr>');
                }
            });
        }

        function deleteMidmarksUpload(uploadId, fileName) {
            if (!uploadId) return;
            const ok = confirm('Delete ALL midmarks records for file: ' + (fileName || '') + ' ?');
            if (!ok) return;

            showMidmarksUploadsStatus('info', 'Deleting...');

            $.ajax({
                url: 'midmarks_manage.php?action=delete',
                method: 'POST',
                data: { upload_id: uploadId },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        showMidmarksUploadsStatus('success', res.message || 'Deleted');
                        loadMidmarksUploads();
                    } else {
                        showMidmarksUploadsStatus('danger', (res && res.message) ? res.message : 'Delete failed');
                    }
                },
                error: function() {
                    showMidmarksUploadsStatus('danger', 'Delete failed due to server error');
                }
            });
        }

        $('#refreshMidmarksUploads').on('click', function(){
            loadMidmarksUploads();
        });

        function showAttendanceUploadsStatus(type, message) {
            const el = $('#attendanceUploadsStatus');
            el.removeClass('d-none alert-success alert-danger alert-info');
            el.addClass('alert-' + type);
            el.text(message);
        }

        function loadAttendanceUploads() {
            const body = $('#attendanceUploadsBody');
            body.html('<tr><td colspan="7" class="text-muted">Loading...</td></tr>');

            $.ajax({
                url: 'attendance_manage.php',
                method: 'GET',
                data: { action: 'list' },
                dataType: 'json',
                success: function(res) {
                    if (!res || !res.success) {
                        body.html('<tr><td colspan="7" class="text-danger">Failed to load uploads</td></tr>');
                        return;
                    }
                    const rows = Array.isArray(res.data) ? res.data : [];
                    if (!rows.length) {
                        body.html('<tr><td colspan="7" class="text-muted">No uploads found.</td></tr>');
                        return;
                    }
                    const html = rows.map(r => {
                        const id = r.id;
                        const fileName = r.file_name || '';
                        const session = r.session_name || '';
                        const academicYear = r.academic_year || '';
                        const semester = r.semester || '';
                        const branch = r.branch || '';
                        const section = r.section || '';
                        const createdAt = r.created_at || r.uploaded_at || '';
                        return `
                            <tr>
                                <td>${fileName}</td>
                                <td>${session}</td>
                                <td>${academicYear}</td>
                                <td>${semester}</td>
                                <td>${branch}</td>
                                <td>${section}</td>
                                <td>${createdAt}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteAttendanceUpload(${id}, '${String(fileName).replace(/'/g, "\\'")}')">Delete</button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                    body.html(html);
                },
                error: function() {
                    body.html('<tr><td colspan="7" class="text-danger">Failed to load uploads</td></tr>');
                }
            });
        }

        function deleteAttendanceUpload(uploadId, fileName) {
            if (!uploadId) return;
            const ok = confirm('Delete ALL attendance records for file: ' + (fileName || '') + ' ?');
            if (!ok) return;

            showAttendanceUploadsStatus('info', 'Deleting...');

            $.ajax({
                url: 'attendance_manage.php?action=delete',
                method: 'POST',
                data: { upload_id: uploadId },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        showAttendanceUploadsStatus('success', res.message || 'Deleted');
                        loadAttendanceUploads();
                    } else {
                        showAttendanceUploadsStatus('danger', (res && res.message) ? res.message : 'Delete failed');
                    }
                },
                error: function() {
                    showAttendanceUploadsStatus('danger', 'Delete failed due to server error');
                }
            });
        }

        $('#refreshAttendanceUploads').on('click', function(){
            loadAttendanceUploads();
        });

        // Initialize - show search section by default
        $(document).ready(function() {
            showSection('search');
            loadAttendanceUploads();
            loadMidmarksUploads();
        });

        /* Enrollment Management Functions */
        function showEnrollmentsStatus(type, message) {
            const el = $('#enrollmentsStatus');
            el.removeClass('d-none alert-success alert-danger alert-info');
            el.addClass('alert-' + type);
            el.text(message);
        }

        function loadStudentEnrollments() {
            const rollNo = $('#enrollRollNo').val().trim();
            const academicYear = $('#enrollAcademicYear').val();
            const semester = $('#enrollSemester').val();
            
            const body = $('#enrollmentsBody');
            
            if (!rollNo) {
                showEnrollmentsStatus('warning', 'Please enter a roll number');
                return;
            }
            
            body.html('<tr><td colspan="7" class="text-muted">Loading...</td></tr>');
            
            let data = { action: 'list' };
            if (rollNo) data.roll_no = rollNo;
            
            $.ajax({
                url: 'manage_enrollments.php',
                method: 'GET',
                data: data,
                dataType: 'json',
                success: function(res) {
                    if (!res || !res.success) {
                        body.html('<tr><td colspan="7" class="text-danger">Failed to load enrollments</td></tr>');
                        showEnrollmentsStatus('danger', (res && res.message) ? res.message : 'Load failed');
                        return;
                    }
                    
                    const rows = Array.isArray(res.data) ? res.data : [];
                    // Filter by academic year and semester if specified
                    const filteredRows = rows.filter(r => {
                        if (academicYear && r.academic_year !== academicYear) return false;
                        if (semester && r.semester !== semester) return false;
                        return true;
                    });
                    
                    if (!filteredRows.length) {
                        body.html('<tr><td colspan="7" class="text-muted">No enrollments found for the specified criteria.</td></tr>');
                        return;
                    }
                    
                    const html = filteredRows.map(r => `
                        <tr>
                            <td>${r.roll_no}</td>
                            <td>${r.academic_year}</td>
                            <td>${r.year}</td>
                            <td>${r.semester}</td>
                            <td>${r.subject_code}</td>
                            <td>${r.subject_name}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-danger" onclick="deleteEnrollment(${r.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    
                    body.html(html);
                    showEnrollmentsStatus('success', `Found ${filteredRows.length} enrollments`);
                },
                error: function() {
                    body.html('<tr><td colspan="7" class="text-danger">Failed to load enrollments due to server error</td></tr>');
                    showEnrollmentsStatus('danger', 'Server error occurred');
                }
            });
        }

        function deleteEnrollment(id) {
            if (!confirm('Are you sure you want to delete this enrollment?')) return;
            
            $.ajax({
                url: 'manage_enrollments.php',
                method: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        showEnrollmentsStatus('success', res.message || 'Deleted successfully');
                        loadStudentEnrollments();
                    } else {
                        showEnrollmentsStatus('danger', (res && res.message) ? res.message : 'Delete failed');
                    }
                },
                error: function() {
                    showEnrollmentsStatus('danger', 'Delete failed due to server error');
                }
            });
        }

        function showAddEnrollmentModal() {
            const rollNo = $('#enrollRollNo').val().trim();
            const academicYear = $('#enrollAcademicYear').val();
            const year = $('#enrollYear').val();
            const semester = $('#enrollSemester').val();
            
            if (!rollNo || !academicYear || !year || !semester) {
                showEnrollmentsStatus('warning', 'Please fill in all required fields first');
                return;
            }
            
            // Simple prompt for now - could be enhanced with a proper modal
            const subjectCode = prompt('Enter subject code:');
            if (!subjectCode) return;
            
            const subjectName = prompt('Enter subject name:');
            if (!subjectName) return;
            
            $.ajax({
                url: 'manage_enrollments.php',
                method: 'POST',
                data: {
                    action: 'add',
                    roll_no: rollNo,
                    academic_year: academicYear,
                    year: year,
                    semester: semester,
                    subject_code: subjectCode,
                    subject_name: subjectName
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        showEnrollmentsStatus('success', res.message || 'Added successfully');
                        loadStudentEnrollments();
                    } else {
                        showEnrollmentsStatus('danger', (res && res.message) ? res.message : 'Add failed');
                    }
                },
                error: function() {
                    showEnrollmentsStatus('danger', 'Add failed due to server error');
                }
            });
        }

        function showBulkImportModal() {
            const rollNo = $('#enrollRollNo').val().trim();
            const academicYear = $('#enrollAcademicYear').val();
            const semester = $('#enrollSemester').val();
            const year = $('#enrollYear').val();
            
            if (!rollNo || !academicYear || !semester || !year) {
                showEnrollmentsStatus('warning', 'Please fill in roll number, academic year, year, and semester first');
                return;
            }
            
            if (!confirm(`This will import all courses from attendance data for student ${rollNo} in ${academicYear}, semester ${semester}. Continue?`)) return;
            
            $.ajax({
                url: 'manage_enrollments.php',
                method: 'POST',
                data: {
                    action: 'bulk_import',
                    roll_no: rollNo,
                    academic_year: academicYear,
                    semester: semester,
                    year: year
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.success) {
                        showEnrollmentsStatus('success', res.message || 'Import completed');
                        loadStudentEnrollments();
                    } else {
                        showEnrollmentsStatus('danger', (res && res.message) ? res.message : 'Import failed');
                    }
                },
                error: function() {
                    showEnrollmentsStatus('danger', 'Import failed due to server error');
                }
            });
        }
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
        }
    </script>
    
    <footer class="text-center mt-5 py-3 bg-light">
        <p class="mb-0 text-muted small">* This is an unofficial view for convenience only. Final authority is the original/official records. We do not guarantee accuracy or completeness.</p>
        <p class="mb-0 text-muted small mt-2">Dashboard Views: <strong><?php echo number_format($dashboardCount); ?></strong></p>
    </footer>
</body>
</html>
