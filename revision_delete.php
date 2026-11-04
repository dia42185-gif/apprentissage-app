<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/Revision.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: revisions.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$revisionModel = new Revision($pdo);
$id = (int) ($_POST['id'] ?? 0);

$revisionModel->delete($id, currentUserId());

header('Location: revisions.php');
exit;