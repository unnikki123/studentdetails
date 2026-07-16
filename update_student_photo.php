<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rollNo = strtoupper(trim($_POST['roll_no'] ?? ''));
$removePhoto = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';

if ($rollNo === '') {
    echo json_encode(['success' => false, 'message' => 'Roll number is required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, student_photo FROM students WHERE roll_no = ? LIMIT 1');
    $stmt->execute([$rollNo]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    $deleteExistingPhoto = function (?string $path): void {
        if (!$path) return;
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $normalized;
        $uploadRoot = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_photos' . DIRECTORY_SEPARATOR;
        $realUploadRoot = realpath($uploadRoot);
        $realFile = realpath($fullPath);
        if ($realUploadRoot && $realFile && str_starts_with($realFile, $realUploadRoot) && is_file($realFile)) {
            @unlink($realFile);
        }
    };

    if ($removePhoto) {
        $deleteExistingPhoto($student['student_photo'] ?? null);
        $stmt = $pdo->prepare('UPDATE students SET student_photo = NULL WHERE roll_no = ?');
        $stmt->execute([$rollNo]);
        echo json_encode(['success' => true, 'message' => 'Profile photo removed', 'student_photo' => null]);
        exit;
    }

    if (!isset($_FILES['student_photo']) || !is_uploaded_file($_FILES['student_photo']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Please select a photo']);
        exit;
    }

    $file = $_FILES['student_photo'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Photo upload failed']);
        exit;
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Photo must be 2 MB or smaller']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WebP photos are allowed']);
        exit;
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_photos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => 'Could not create upload folder']);
        exit;
    }

    $safeRoll = preg_replace('/[^A-Z0-9_-]/', '_', $rollNo);
    $filename = $safeRoll . '_' . time() . '.' . $extensions[$mime];
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
    $relativePath = 'uploads/student_photos/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Could not save photo']);
        exit;
    }

    $deleteExistingPhoto($student['student_photo'] ?? null);

    $stmt = $pdo->prepare('UPDATE students SET student_photo = ? WHERE roll_no = ?');
    $stmt->execute([$relativePath, $rollNo]);

    echo json_encode(['success' => true, 'message' => 'Profile photo updated', 'student_photo' => $relativePath]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
