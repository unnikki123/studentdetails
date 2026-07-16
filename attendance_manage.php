<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once 'config.php';

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : '';

try {
    if ($action === 'list') {
        $hasCol = function (string $col) use ($pdo): bool {
            try {
                $pdo->query("SELECT `$col` FROM attendance_uploads LIMIT 1");
                return true;
            } catch (Throwable $e) {
                return false;
            }
        };

        $cols = ['id', 'file_name', 'session_name'];
        foreach (['academic_year', 'semester', 'section', 'branch'] as $metaCol) {
            if ($hasCol($metaCol)) $cols[] = $metaCol;
        }
        if ($hasCol('file_hash')) $cols[] = 'file_hash';
        if ($hasCol('created_at')) $cols[] = 'created_at';
        if ($hasCol('uploaded_at')) $cols[] = 'uploaded_at';

        $select = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));
        $stmt = $pdo->query("SELECT $select FROM attendance_uploads ORDER BY id DESC LIMIT 200");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    if ($action === 'delete') {
        $uploadId = isset($_POST['upload_id']) && $_POST['upload_id'] !== '' ? (int)$_POST['upload_id'] : 0;
        $fileName = isset($_POST['file_name']) ? trim((string)$_POST['file_name']) : '';

        if ($uploadId <= 0 && $fileName === '') {
            echo json_encode(['success' => false, 'message' => 'Missing upload_id or file_name']);
            exit;
        }

        if ($uploadId > 0) {
            $stmt = $pdo->prepare('SELECT id, file_name, session_name FROM attendance_uploads WHERE id = ? LIMIT 1');
            $stmt->execute([$uploadId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, file_name, session_name FROM attendance_uploads WHERE file_name = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$fileName]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Upload record not found']);
            exit;
        }

        $id = (int)$row['id'];
        $session = (string)($row['session_name'] ?? '');
        if ($session === '') {
            echo json_encode(['success' => false, 'message' => 'Upload session_name missing']);
            exit;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM attendance_subjects WHERE session_name = ?');
        $stmt->execute([$session]);
        $deletedSubjects = $stmt->rowCount();

        $stmt = $pdo->prepare('DELETE FROM attendance_summary WHERE session_name = ?');
        $stmt->execute([$session]);
        $deletedSummary = $stmt->rowCount();

        $stmt = $pdo->prepare('DELETE FROM attendance_uploads WHERE id = ?');
        $stmt->execute([$id]);
        $deletedUploads = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Deleted attendance data for file: ' . (string)($row['file_name'] ?? ''),
            'deleted' => [
                'attendance_subjects' => $deletedSubjects,
                'attendance_summary' => $deletedSummary,
                'attendance_uploads' => $deletedUploads,
            ],
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Attendance manage failed', 'error' => $e->getMessage()]);
}
