<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/Formation.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: formations.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$formationModel = new Formation($pdo);
$id = (int) ($_POST['id'] ?? 0);

$formationModel->delete($id, currentUserId());

header('Location: formations.php');
exit;