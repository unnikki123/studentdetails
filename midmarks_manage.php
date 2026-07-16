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
        $cols = ['id', 'file_name', 'academic_year', 'section'];

        $hasCol = function (string $col) use ($pdo): bool {
            try {
                $pdo->query("SELECT `$col` FROM midmarks_uploads LIMIT 1");
                return true;
            } catch (Throwable $e) {
                return false;
            }
        };

        foreach (['semester_info', 'file_hash', 'created_at'] as $c) {
            if ($hasCol($c)) $cols[] = $c;
        }

        $select = implode(', ', array_map(function ($c) { return "`$c`"; }, $cols));
        $stmt = $pdo->query("SELECT $select FROM midmarks_uploads ORDER BY id DESC LIMIT 200");
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
            $stmt = $pdo->prepare('SELECT id, file_name FROM midmarks_uploads WHERE id = ? LIMIT 1');
            $stmt->execute([$uploadId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, file_name FROM midmarks_uploads WHERE file_name = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$fileName]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Upload record not found']);
            exit;
        }

        $id = (int)$row['id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM midmarks_subject_marks WHERE upload_id = ?');
        $stmt->execute([$id]);
        $deletedMarks = $stmt->rowCount();

        $stmt = $pdo->prepare('DELETE FROM midmarks_uploads WHERE id = ?');
        $stmt->execute([$id]);
        $deletedUploads = $stmt->rowCount();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Deleted midmarks data for file: ' . (string)($row['file_name'] ?? ''),
            'deleted' => [
                'midmarks_subject_marks' => $deletedMarks,
                'midmarks_uploads' => $deletedUploads,
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
    echo json_encode(['success' => false, 'message' => 'Midmarks manage failed', 'error' => $e->getMessage()]);
}
