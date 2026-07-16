<?php
session_start();
require_once 'view_counter.php';
require_once 'config.php';

// Cache control
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Details Portal</title>

<!-- Cache control -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<link href="assets/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/fontawesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">

<link href="assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" rel="stylesheet">
<link href="assets/css/mobile-animations.css?v=<?= filemtime(__DIR__ . '/assets/css/mobile-animations.css') ?>" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
</head>

<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>
<?php include __DIR__ . '/includes/disclaimer.php'; ?>
<?php include __DIR__ . '/includes/modal_skills.php'; ?>
<?php include __DIR__ . '/includes/search_results.php'; ?>
<?php include __DIR__ . '/includes/hero.php'; ?>
<?php include __DIR__ . '/includes/offcanvas.php'; ?>
<?php include __DIR__ . '/includes/login_modal.php'; ?>
<?php include __DIR__ . '/includes/privacy_policy.php'; ?>
<?php include __DIR__ . '/includes/modal_below65.php'; ?>
<?php include __DIR__ . '/includes/modal_65to75.php'; ?>
<?php include __DIR__ . '/includes/mobile_nav.php'; ?>
<?php include __DIR__ . '/includes/mobile_search.php'; ?>

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
                <div class="photo-actions mt-4">
                    <a id="updateProfileBtn" href="student_profile.php" class="btn btn-primary photo-modal-btn">
                        <i class="fas fa-user-edit me-2"></i>Update Profile
                    </a>
                    <a id="downloadPdfBtn" href="#" class="btn btn-success photo-modal-btn">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </a>
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

.photo-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.photo-modal-btn {
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 500;
    transition: all 0.3s ease;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.photo-modal-btn.btn-success {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
}

.photo-modal-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
}

.photo-modal-btn.btn-success:hover {
    box-shadow: 0 10px 20px rgba(16, 185, 129, 0.4);
}

.photo-modal-btn:active {
    transform: translateY(0);
}

#photoModal .modal-backdrop {
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(5px);
}
</style>

<script src="assets/vendor/jquery/3.6.0/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

<!-- Load JavaScript files in dependency order (traditional scripts for maximum compatibility) -->
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/filters.js?v=<?= filemtime(__DIR__ . '/assets/js/filters.js') ?>"></script>
<script src="assets/js/reports.js?v=<?= filemtime(__DIR__ . '/assets/js/reports.js') ?>"></script>
<script src="assets/js/search.js?v=<?= filemtime(__DIR__ . '/assets/js/search.js') ?>"></script>
<script src="assets/js/index.js?v=<?= filemtime(__DIR__ . '/assets/js/index.js') ?>"></script>
<script src="assets/js/attendance-animations.js?v=<?= filemtime(__DIR__ . '/assets/js/attendance-animations.js') ?>"></script>

<script>
function showPrivacyPolicy() {
  const privacyModalEl = document.getElementById('privacyPolicyModal');
  if (privacyModalEl) {
    try {
      const privacyModal = new bootstrap.Modal(privacyModalEl);
      privacyModal.show();
    } catch (e) {
      console.error('Bootstrap modal error:', e);
      if (typeof $ !== 'undefined') {
        $('#privacyPolicyModal').modal('show');
      }
    }
  }
}
</script>

</body>
</html>