<?php
/**
 * Student Profile Update Page
 * Allows students to update their profile photo and date of birth
 * Authentication via OTP sent to rollno@pvpsit.ac.in
 */
session_start();
require_once 'config.php';

// Redirect if already authenticated
if (isset($_SESSION['student_authenticated']) && $_SESSION['student_authenticated'] === true) {
    // Show profile update form
    $showProfileForm = true;
} else {
    $showProfileForm = false;
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: student_profile.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pre-fill roll number if provided in URL
$prefilledRollNumber = isset($_GET['roll']) ? htmlspecialchars($_GET['roll']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile Update - PVPSIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/fontawesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 800px;
            width: 100%;
        }
        .current-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #667eea;
        }
        .photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #999;
            border: 4px dashed #ccc;
        }
    </style>
</head>
<body>
    <?php if (!$showProfileForm): ?>
    <!-- Login Form -->
    <div class="auth-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-primary">Student Profile Update</h2>
            <p class="text-muted">PVPSIT Student Portal</p>
        </div>
        
        <div id="step1">
            <form id="loginForm" onsubmit="return false;">
                <div class="mb-3">
                    <label for="rollNumber" class="form-label">Roll Number</label>
                    <input type="text" class="form-control" id="rollNumber" name="roll_number" 
                           placeholder="e.g., 24501A1201" pattern="[0-9A-Za-z]+"
                           value="<?php echo $prefilledRollNumber; ?>">
                    <div class="form-text">OTP will be sent to your college email (rollno@pvpsit.ac.in)</div>
                </div>
                <button type="button" class="btn btn-primary w-100" id="sendOtpBtn">
                    <i class="fas fa-paper-plane me-2"></i>Send OTP
                </button>
            </form>
        </div>

        <div id="step2" style="display: none;">
            <form id="otpForm">
                <div class="mb-3">
                    <label for="otp" class="form-label">Enter OTP</label>
                    <input type="text" class="form-control" id="otp" name="otp" 
                           placeholder="6-digit OTP" required maxlength="6" pattern="[0-9]{6}">
                    <div class="form-text">OTP sent to <span id="emailDisplay"></span></div>
                </div>
                <button type="submit" class="btn btn-success w-100 mb-2">
                    <i class="fas fa-check me-2"></i>Verify OTP
                </button>
                <button type="button" class="btn btn-link w-100" id="resendOtp">
                    Resend OTP
                </button>
            </form>
        </div>

        <div id="loading" style="display: none;" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted" id="loadingText">Sending OTP...</p>
        </div>

        <div id="alertContainer"></div>
    </div>

    <?php else: ?>
    <!-- Profile Update Form -->
    <div class="profile-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-primary">Update Profile</h2>
            <a href="?action=logout" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>

        <div class="row">
            <div class="col-md-4 text-center mb-4">
                <div id="currentPhotoContainer">
                    <div class="photo-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <h5 class="mt-3" id="studentName">Loading...</h5>
                <p class="text-muted" id="studentRollNo"><?php echo htmlspecialchars($_SESSION['student_roll_no'] ?? ''); ?></p>
            </div>
            <div class="col-md-8">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#photoTab">
                            <i class="fas fa-camera me-2"></i>Profile Photo
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dobTab">
                            <i class="fas fa-calendar me-2"></i>Date of Birth
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Photo Upload Tab -->
                    <div class="tab-pane fade show active" id="photoTab">
                        <form id="photoUploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label for="profilePhoto" class="form-label">Upload Profile Photo</label>
                                <input type="file" class="form-control" id="profilePhoto" name="profile_photo" 
                                       accept="image/jpeg,image/jpg,image/png" required>
                                <div class="form-text">
                                    Allowed formats: JPG, JPEG, PNG<br>
                                    Maximum size: 2MB
                                </div>
                            </div>
                            <div id="photoPreview" class="mb-3" style="display: none;">
                                <img id="previewImage" class="img-thumbnail" style="max-width: 200px;">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Upload Photo
                            </button>
                        </form>
                    </div>

                    <!-- DOB Update Tab -->
                    <div class="tab-pane fade" id="dobTab">
                        <form id="dobUpdateForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" required>
                                <div class="form-text">Your date of birth will be displayed as DD-MM only (year hidden for privacy)</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Date of Birth
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="profileAlertContainer"></div>
    </div>
    <?php endif; ?>

    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content photo-modal-content">
                <div class="modal-header photo-modal-header">
                    <h5 class="modal-title photo-modal-title">
                        <i class="fas fa-user-circle me-2"></i>Profile Photo
                    </h5>
                    <button type="button" class="btn-close photo-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body photo-modal-body">
                    <div class="photo-container">
                        <img id="modalPhoto" src="" alt="Profile Photo" class="photo-modal-img">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .photo-modal-content {
        border: none;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .photo-modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 20px 25px;
    }

    .photo-modal-title {
        color: white;
        font-weight: 600;
        font-size: 1.25rem;
        margin: 0;
    }

    .photo-modal-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }

    .photo-modal-close:hover {
        opacity: 1;
    }

    .photo-modal-body {
        padding: 30px;
        background: #f8f9fa;
    }

    .photo-container {
        display: flex;
        justify-content: center;
        align-items: center;
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .photo-modal-img {
        max-width: 100%;
        max-height: 400px;
        border-radius: 10px;
        object-fit: contain;
        transition: transform 0.3s ease;
    }

    .photo-modal-img:hover {
        transform: scale(1.02);
    }

    #photoModal .modal-backdrop {
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPhotoModal(photoPath) {
            const timestamp = new Date().getTime();
            document.getElementById('modalPhoto').src = photoPath + '?t=' + timestamp;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            let CSRF_TOKEN = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

            // Fetch CSRF token if not available
            async function getCsrfToken() {
                if (!CSRF_TOKEN) {
                    try {
                        const response = await fetch('api/get_csrf_token.php');
                        const data = await response.json();
                        if (data.csrf_token) {
                            CSRF_TOKEN = data.csrf_token;
                        }
                    } catch (error) {
                        console.error('Failed to fetch CSRF token:', error);
                    }
                }
                return CSRF_TOKEN;
            }

            function showAlert(message, type = 'danger') {
                const container = document.getElementById('alertContainer') || document.getElementById('profileAlertContainer');
                container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            }

            function showLoading(show, text = 'Loading...') {
                const loading = document.getElementById('loading');
                const loadingText = document.getElementById('loadingText');
                if (loading) {
                    loading.style.display = show ? 'block' : 'none';
                    if (loadingText) loadingText.textContent = text;
                }
            }

            // Login Form Handler
            document.getElementById('sendOtpBtn')?.addEventListener('click', async function(e) {
                e.preventDefault();
                const rollNumberInput = document.getElementById('rollNumber');
                const rollNumber = rollNumberInput ? rollNumberInput.value.trim() : '';
                
                console.log('Roll number input element:', rollNumberInput);
                console.log('Roll number entered:', rollNumber);
                
                if (!rollNumber) {
                    showAlert('Please enter your roll number');
                    return;
                }

                // Get CSRF token
                const csrfToken = await getCsrfToken();
                console.log('CSRF token:', csrfToken);

                showLoading(true, 'Sending OTP...');

                try {
                    const response = await fetch('api/send_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ roll_number: rollNumber, csrf_token: csrfToken })
                    });

                    const data = await response.json();
                    console.log('Response:', data);

                    if (data.success) {
                        document.getElementById('step1').style.display = 'none';
                        document.getElementById('step2').style.display = 'block';
                        document.getElementById('emailDisplay').textContent = `${rollNumber}@pvpsit.ac.in`;
                        showAlert('OTP sent successfully! Check your college email.', 'success');
                    } else {
                        showAlert(data.message || 'Failed to send OTP');
                    }
                } catch (error) {
                    showAlert('Error: ' + error.message);
                } finally {
                    showLoading(false);
                }
            });

            // OTP Form Handler
            document.getElementById('otpForm')?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const rollNumber = document.getElementById('rollNumber').value.trim();
                const otp = document.getElementById('otp').value.trim();

                if (!otp || otp.length !== 6) {
                    showAlert('Please enter a valid 6-digit OTP');
                    return;
                }

                // Get CSRF token
                const csrfToken = await getCsrfToken();

                showLoading(true, 'Verifying OTP...');

                try {
                    const response = await fetch('api/verify_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ roll_number: rollNumber, otp: otp, csrf_token: csrfToken })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('Login successful! Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert(data.message || 'Invalid OTP');
                    }
                } catch (error) {
                    showAlert('Error: ' + error.message);
                } finally {
                    showLoading(false);
                }
            });

            // Resend OTP
            document.getElementById('resendOtp')?.addEventListener('click', async function() {
                const rollNumber = document.getElementById('rollNumber').value.trim();
                const csrfToken = await getCsrfToken();
                showLoading(true, 'Resending OTP...');

                try {
                    const response = await fetch('api/send_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ roll_number: rollNumber, csrf_token: csrfToken })
                    });

                    const data = await response.json();
                    showAlert(data.success ? 'OTP resent successfully!' : data.message, data.success ? 'success' : 'danger');
                } catch (error) {
                    showAlert('Error: ' + error.message);
                } finally {
                    showLoading(false);
                }
            });

            // Photo Preview
            document.getElementById('profilePhoto')?.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.getElementById('photoPreview');
                        const img = document.getElementById('previewImage');
                        preview.style.display = 'block';
                        img.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Load current profile data
            <?php if ($showProfileForm): ?>
            async function loadProfileData() {
                try {
                    const response = await fetch('api/get_student_profile.php');
                    const data = await response.json();
                    
                    if (data.success) {
                        document.getElementById('studentName').textContent = data.student_name || 'Unknown';
                        
                        if (data.photo_path) {
                            // Add timestamp to prevent caching
                            const timestamp = new Date().getTime();
                            document.getElementById('currentPhotoContainer').innerHTML = 
                                `<img src="${data.photo_path}?t=${timestamp}" class="current-photo" alt="Profile Photo" onclick="showPhotoModal('${data.photo_path}')" style="cursor: pointer;">`;
                        }
                        
                        if (data.date_of_birth) {
                            document.getElementById('dateOfBirth').value = data.date_of_birth;
                        }
                    }
                } catch (error) {
                    console.error('Error loading profile:', error);
                }
            }
            loadProfileData();

            // Photo Upload Form Handler
            document.getElementById('photoUploadForm')?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                showLoading(true, 'Uploading photo...');

                try {
                    const response = await fetch('api/update_photo.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('Profile photo updated successfully!', 'success');
                        // Reload profile data to show new photo
                        loadProfileData();
                        // Reset form
                        this.reset();
                        document.getElementById('photoPreview').style.display = 'none';
                    } else {
                        showAlert(data.message || 'Failed to upload photo');
                    }
                } catch (error) {
                    showAlert('Error: ' + error.message);
                } finally {
                    showLoading(false);
                }
            });

            // DOB Update Form Handler
            document.getElementById('dobUpdateForm')?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                showLoading(true, 'Updating date of birth...');

                try {
                    const response = await fetch('api/update_dob.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('Date of birth updated successfully!', 'success');
                    } else {
                        showAlert(data.message || 'Failed to update date of birth');
                    }
                } catch (error) {
                    showAlert('Error: ' + error.message);
                } finally {
                    showLoading(false);
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
