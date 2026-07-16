<?php
/**
 * Update student profile photo
 * POST: multipart/form-data with photo file and csrf_token
 */
session_start();
header('Content-Type: application/json');
require_once '../config.php';

// Check authentication
if (!isset($_SESSION['student_authenticated']) || $_SESSION['student_authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$rollNumber = $_SESSION['student_roll_no'] ?? '';

if (empty($rollNumber)) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Validate CSRF token
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (empty($sessionToken) || $postedToken !== $sessionToken) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['profile_photo'];

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 2MB limit']);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, and PNG files are allowed']);
    exit;
}

// Validate image dimensions
$imageInfo = getimagesize($file['tmp_name']);
if (!$imageInfo) {
    echo json_encode(['success' => false, 'message' => 'Invalid image file']);
    exit;
}

// Resize image to max 300x300 pixels for better performance and storage
$maxWidth = 300;
$maxHeight = 300;
$width = $imageInfo[0];
$height = $imageInfo[1];

// Calculate new dimensions maintaining aspect ratio
if ($width > $maxWidth || $height > $maxHeight) {
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = round($width * $ratio);
    $newHeight = round($height * $ratio);
} else {
    $newWidth = $width;
    $newHeight = $height;
}

// Create new image
$sourceImage = imagecreatefromstring(file_get_contents($file['tmp_name']));
if (!$sourceImage) {
    echo json_encode(['success' => false, 'message' => 'Failed to process image']);
    exit;
}

$newImage = imagecreatetruecolor($newWidth, $newHeight);

// Handle transparency for PNG
if ($mimeType === 'image/png') {
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
    imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
}

// Resize image
imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/student_photos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename using roll number
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = $rollNumber . '_' . time() . '.' . $fileExtension;
$filePath = $uploadDir . $fileName;

// Save resized image
if ($mimeType === 'image/png') {
    imagepng($newImage, $filePath, 9); // Maximum compression
} else {
    imagejpeg($newImage, $filePath, 85); // Good quality
}

// Free memory
imagedestroy($sourceImage);
imagedestroy($newImage);

// Delete old photo if exists
try {
    $stmt = $pdo->prepare("SELECT student_photo FROM students WHERE roll_no = ? LIMIT 1");
    $stmt->execute([$rollNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student && !empty($student['student_photo'])) {
        $oldPhotoPath = $uploadDir . $student['student_photo'];
        if (file_exists($oldPhotoPath)) {
            unlink($oldPhotoPath);
        }
    }
} catch (PDOException $e) {
    // Continue even if old photo deletion fails
}

// Move uploaded file is now handled by image processing above

// Update database
try {
    $stmt = $pdo->prepare("UPDATE students SET student_photo = ? WHERE roll_no = ?");
    $stmt->execute([$fileName, $rollNumber]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated successfully',
        'photo_path' => 'uploads/student_photos/' . $fileName
    ]);

} catch (PDOException $e) {
    // Delete uploaded file if database update fails
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
