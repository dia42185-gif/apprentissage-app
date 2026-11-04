<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/src/JournalEntry.php';

startSecureSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: journal.php');
    exit;
}

verifyCsrf();

$pdo = getPDO();
$journalModel = new JournalEntry($pdo);
$id = (int) ($_POST['id'] ?? 0);

$journalModel->delete($id, currentUserId());

header('Location: journal.php');
exit;