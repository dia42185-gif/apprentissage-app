<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/LearningSession.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: time_tracking.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$sessionModel = new LearningSession($pdo);
$id = (int) ($_POST['id'] ?? 0);

$sessionModel->delete($id, currentUserId());

header('Location: time_tracking.php');
exit;