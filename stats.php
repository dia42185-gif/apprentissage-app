<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/LearningSession.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$sessionModel = new LearningSession($pdo);

$weeklyStmt = $pdo->prepare("
    SELECT YEARWEEK(session_date, 3) AS yw,
           MIN(session_date) AS week_start,
           FLOOR(SUM(duration_sec) / 60) AS total_min
    FROM learning_sessions
    WHERE user_id = ?
      AND session_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 8 WEEK) AND CURDATE()
    GROUP BY YEARWEEK(session_date, 3)
    ORDER BY yw ASC
");
$weeklyStmt->execute([$userId]);
$weeklyRows = $weeklyStmt->fetchAll();

$weekLabels = [];
$weekMinutes = [];
foreach ($weeklyRows as $row) {
    $weekLabels[] = 'Sem. ' . date('d/m', strtotime($row['week_start']));
    $weekMinutes[] = floor(((int) $row['total_min']) / 6) / 10;
}

$statusStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS nb
    FROM formations WHERE user_id = ?
    GROUP BY status
");
$statusStmt->execute([$userId]);
$statusRows = $statusStmt->fetchAll();

$statusLabels = [];
$statusCounts = [];
foreach ($statusRows as $row) {
    $statusLabels[] = formationStatusLabel($row['status']);
    $statusCounts[] = (int) $row['nb'];
}

$categoryStmt = $pdo->prepare("
    SELECT c.name, FLOOR(SUM(s.duration_sec) / 60) AS total_min
    FROM learning_sessions s
    JOIN categories c ON c.id = s.category_id
    WHERE s.user_id = ?
      AND s.session_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
    GROUP BY c.name
    ORDER BY total_min DESC
");
$categoryStmt->execute([$userId]);
$categoryRows = $categoryStmt->fetchAll();

$categoryLabels = [];
$categoryMinutes = [];
foreach ($categoryRows as $row) {
    $categoryLabels[] = $row['name'];
    $categoryMinutes[] = floor(((int) $row['total_min']) / 6) / 10;
}

$tasksStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'terminee' THEN 1 ELSE 0 END) AS done,
        SUM(CASE WHEN status != 'annulee' THEN 1 ELSE 0 END) AS total
    FROM tasks WHERE user_id = ?
");
$tasksStmt->execute([$userId]);
$tasksStats = $tasksStmt->fetch();

$formationsStmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'terminee' THEN 1 ELSE 0 END) AS done,
        SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status != 'abandonnee' THEN 1 ELSE 0 END) AS total
    FROM formations WHERE user_id = ?
");
$formationsStmt->execute([$userId]);
$formationsStats = $formationsStmt->fetch();

$streak = $sessionModel->currentStreak($userId);
$totalMinutesAllTime = $sessionModel->totalMinutesAllTime($userId);

$pageTitle = 'Statistiques';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-4">Statistiques</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="fs-4"><?= formatDuration($totalMinutesAllTime) ?></div>
                <div class="small text-muted">Temps total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="fs-4"><?= $streak ?></div>
                <div class="small text-muted">Jours consecutifs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="fs-4"><?= (int) $formationsStats['done'] ?>/<?= (int) $formationsStats['total'] ?></div>
                <div class="small text-muted">Formations terminees</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="fs-4"><?= (int) $tasksStats['done'] ?>/<?= (int) $tasksStats['total'] ?></div>
                <div class="small text-muted">Taches terminees</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title">Heures d'apprentissage par semaine (8 dernieres semaines)</h6>
                <?php if (empty($weekLabels)): ?>
                    <p class="text-muted small mb-0">Pas encore de donnees.</p>
                <?php else: ?>
                    <canvas id="weeklyChart" height="90"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title">Formations par statut</h6>
                <?php if (empty($statusLabels)): ?>
                    <p class="text-muted small mb-0">Pas encore de formation.</p>
                <?php else: ?>
                    <canvas id="statusChart" height="200"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title">Temps par categorie (30 derniers jours)</h6>
                <?php if (empty($categoryLabels)): ?>
                    <p class="text-muted small mb-0">Pas encore de donnees sur cette periode.</p>
                <?php else: ?>
                    <canvas id="categoryChart" height="120"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($weekLabels) || !empty($statusLabels) || !empty($categoryLabels)): ?>
<script src="public/js/chart.umd.js"></script>
<script>
const baseColor = '#0d6efd';
const palette = ['#0d6efd', '#198754', '#fd7e14', '#dc3545', '#6f42c1', '#20c997', '#adb5bd'];

<?php if (!empty($weekLabels)): ?>
new Chart(document.getElementById('weeklyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($weekLabels) ?>,
        datasets: [{
            label: 'Heures',
            data: <?= json_encode($weekMinutes) ?>,
            backgroundColor: baseColor
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Heures' } } }
    }
});
<?php endif; ?>

<?php if (!empty($statusLabels)): ?>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode($statusCounts) ?>,
            backgroundColor: palette
        }]
    },
    options: {
        plugins: { legend: { position: 'bottom' } }
    }
});
<?php endif; ?>

<?php if (!empty($categoryLabels)): ?>
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($categoryLabels) ?>,
        datasets: [{
            label: 'Heures',
            data: <?= json_encode($categoryMinutes) ?>,
            backgroundColor: palette
        }]
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, title: { display: true, text: 'Heures' } } }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php require __DIR__ . '/views/footer.php'; ?>