<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/VacationPeriod.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vacation.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$vacationModel = new VacationPeriod($pdo);
$id = (int) ($_POST['id'] ?? 0);

$vacationModel->delete($id, currentUserId());

header('Location: vacation.php');
exit;