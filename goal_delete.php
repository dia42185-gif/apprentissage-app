<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/Goal.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: goals.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$goalModel = new Goal($pdo);
$id = (int) ($_POST['id'] ?? 0);

$goalModel->delete($id, currentUserId());

header('Location: goals.php');
exit;