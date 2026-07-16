# Student Details Management System

A web-based system for managing and searching student information, including attendance, marks, and results data.

## Project Structure

### Core Files
- **`index.php`** - Main frontend interface with search functionality and card-based data display
- **`public_search.php`** - API endpoint for searching student data across multiple database tables
- **`config.php`** - Database configuration and connection settings

### Authentication & Dashboard
- **`dashboard.php`** - Main dashboard page for administrators
- **`login_process.php`** - Handles user authentication and login logic
- **`logout.php`** - User logout functionality

### Upload & Data Management
- **`upload_attendance.php`** - Upload and manage student attendance data
- **`upload_marks.php`** - Upload and manage student marks/results data
- **`upload_attendance_debug.php`** - Debug version of attendance upload functionality

### Database & Setup
- **`database.sql`** - Database schema and initial setup SQL script
- **`table_rename_script.sql`** - SQL script for renaming database tables
- **`rename_tables.php`** - PHP script to execute table renaming operations

### Search & Query
- **`search_student.php`** - Additional student search functionality

### Testing & Debugging Files
*(Note: Most test files have been disabled for production by returning 404 responses)*

- **`debug.php`** - General debugging utilities
- **`debug_login.php`** - Login debugging tools
- **`debug_search.php`** - Search debugging interface (disabled)
- **`test.php`** - General test script
- **`test_ajax.php`** - AJAX functionality testing
- **`test_ajax_direct.php`** - Direct AJAX API testing (disabled)
- **`test_login.php`** - Login testing
- **`test_minimal.html`** - Minimal HTML test page
- **`test_search.php`** - Search functionality testing
- **`test_search_simple.html`** - Simple search test interface

## Features

### Frontend (`index.php`)
- Responsive Bootstrap UI with hero section
- AJAX-powered student search
- Card-based display of student data grouped by table type
- Integrated tables showing attendance, marks, and results
- Transpose view functionality for tables
- Month-based sorting for attendance tables
- Special layout for Student Results card with grades display
- Hidden columns (S NO, technical fields)
- Search results with college name and semester info

### Backend (`public_search.php`)
- Searches across multiple database tables for roll number matches
- Handles various table naming conventions (attendance, results, etc.)
- Returns structured JSON response with student info and table data
- Error handling with logging
- Support for `student_results`, `subject_results`, and renamed attendance tables

### Database Tables
- **`students`** - Basic student information (roll_no, name, email, etc.)
- **`student_results`** - Student academic results and summary data
- **`subject_results`** - Individual subject grades and marks
- **Renamed attendance tables** - Monthly attendance data (e.g., `dec2s22025`, `jan2s22026`)
- **`attendance`** & **`marks`** - Traditional attendance and marks tables

## Technology Stack

- **Frontend**: HTML, CSS, JavaScript (jQuery), Bootstrap 5
- **Backend**: PHP 8.2, PDO for database access
- **Database**: MySQL/MariaDB
- **Server**: Apache (XAMPP)

## Installation & Setup

1. Place project files in web server root (e.g., `htdocs/studentdetails/`)
2. Import `database.sql` to create the database schema
3. Configure database connection in `config.php`
4. Ensure proper permissions for file uploads if needed

## Usage

1. Access `index.php` in browser
2. Enter student roll number in search bar
3. View results in organized cards showing:
   - Student basic information
   - Attendance data (grouped by month)
   - Academic results and grades
   - Subject-wise marks

## Development Notes

- All test/debug files return 404 to prevent accidental access in production
- Console logging has been removed from production code
- Error handling includes PHP error logging for debugging
- Flexible table grouping based on column structure
- Month/year extraction from table names for proper sorting

---

**Designed and Developed by JUK**
