<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/Task.php';
require_once __DIR__ . '/src/LearningSession.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: planning.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$taskModel = new Task($pdo);
$sessionModel = new LearningSession($pdo);
$id = (int) ($_POST['id'] ?? 0);

$userId = currentUserId();
$pdo->beginTransaction();
try {
    $lock = $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
    $lock->execute([$userId]);
    $sessionModel->pauseTask($id, $userId);
    $taskModel->delete($id, $userId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$redirect = $_POST['redirect'] ?? 'planning.php';
if (!str_starts_with($redirect, 'planning.php')) {
    $redirect = 'planning.php';
}

header('Location: ' . $redirect);
exit;