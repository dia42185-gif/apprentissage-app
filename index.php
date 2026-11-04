<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Formation.php';
require_once __DIR__ . '/src/Goal.php';
require_once __DIR__ . '/src/Task.php';
require_once __DIR__ . '/src/LearningSession.php';
require_once __DIR__ . '/src/Revision.php';
require_once __DIR__ . '/includes/motivation.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();

$formationModel = new Formation($pdo);
$goalModel = new Goal($pdo);
$taskModel = new Task($pdo);
$sessionModel = new LearningSession($pdo);

$today = date('Y-m-d');

$todayTasks = $taskModel->forDay($userId, $today);
$trackableTodayTasks = array_filter($todayTasks, fn($t) => $t['status'] !== 'annulee');
$todayDone = array_filter($trackableTodayTasks, fn($t) => $t['status'] === 'terminee');
$todayRemaining = array_filter($trackableTodayTasks, fn($t) => $t['status'] !== 'terminee');

$minutesToday = $sessionModel->minutesForDate($userId, $today);
$plannedMinutesToday = array_sum(array_map(fn($t) => (int) ($t['duration_min'] ?? 0), $trackableTodayTasks));

$inProgressFormations = $formationModel->inProgressForUser($userId, 5);

$activeGoals = $goalModel->activeForUser($userId, 5);

$overdueTasks = $taskModel->overdueForUser($userId, 5);

$revisionModel = new Revision($pdo);
$dueRevisions = $revisionModel->dueForUser($userId);
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$minutesThisWeek = $sessionModel->totalMinutes($userId, $startOfWeek, $today);
$streak = $sessionModel->currentStreak($userId);

$motivation = getMotivationalMessage(
    $minutesToday,
    $plannedMinutesToday,
    count($overdueTasks),
    count($todayDone),
    count($trackableTodayTasks)
);

$stmt = $pdo->prepare("
    SELECT AVG(progress_percent) AS avg_progress, COUNT(*) AS total_count,
           SUM(CASE WHEN status = 'terminee' THEN 1 ELSE 0 END) AS done_count,
           SUM(CASE WHEN status = 'en_cours' THEN 1 ELSE 0 END) AS active_count
    FROM formations WHERE user_id = ? AND status != 'abandonnee'
");
$stmt->execute([$userId]);
$globalStats = $stmt->fetch();

$weekEnd = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-6 days', strtotime($weekEnd)));
$dailySessionsStmt = $pdo->prepare("
    SELECT session_date, FLOOR(SUM(duration_sec) / 60) AS total_minutes
    FROM learning_sessions
    WHERE user_id = ? AND session_date BETWEEN ? AND ?
    GROUP BY session_date
");
$dailySessionsStmt->execute([$userId, $weekStart, $weekEnd]);
$dailySessions = [];
foreach ($dailySessionsStmt->fetchAll() as $row) {
    $dailySessions[$row['session_date']] = (int) $row['total_minutes'];
}

$chartDate = new DateTimeImmutable($weekStart);
$chartLabels = [];
$chartMinutes = [];
for ($day = 0; $day < 7; $day++) {
    $date = $chartDate->modify("+{$day} days");
    $dateKey = $date->format('Y-m-d');
    $chartLabels[] = $date->format('d/m');
    $chartMinutes[] = $dailySessions[$dateKey] ?? 0;
}

$formationStatusesStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM formations
    WHERE user_id = ?
    GROUP BY status
");
$formationStatusesStmt->execute([$userId]);
$formationStatusCounts = array_fill_keys(
    ['a_commencer', 'en_cours', 'en_pause', 'terminee', 'abandonnee'],
    0
);
foreach ($formationStatusesStmt->fetchAll() as $row) {
    $formationStatusCounts[$row['status']] = (int) $row['total'];
}
$formationStatusLabels = array_map('formationStatusLabel', array_keys($formationStatusCounts));
$formationStatusValues = array_values($formationStatusCounts);
$formationTotal = array_sum($formationStatusValues);

$pageTitle = 'Dashboard';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-1">Bonjour <?= e($_SESSION['user_name'] ?? '') ?></h2>
<p class="text-muted mb-4">Voici ton programme du jour.</p>

<div class="alert <?= motivationBadgeClass($motivation['type']) ?>">
    <?= e($motivation['text']) ?>
</div>

<?php if (!empty($overdueTasks)): ?>
    <div class="alert alert-warning">
        <strong>Attention :</strong> tu as <?= count($overdueTasks) ?> tache<?= count($overdueTasks) > 1 ? 's' : '' ?> en retard.
        <a href="planning.php" class="alert-link">Voir le planning</a>
    </div>
<?php endif; ?>

<?php if (!empty($dueRevisions)): ?>
    <div class="alert alert-info">
        <strong>Revisions du jour :</strong>
        <?= implode(', ', array_map('e', array_column($dueRevisions, 'notion'))) ?>
        - <a href="revisions.php" class="alert-link">Voir</a>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Aujourd'hui</h5>
                    <a href="today.php" class="small">Voir la page complete</a>
                </div>

                <?php if (empty($trackableTodayTasks)): ?>
                    <p class="text-muted small">Aucune tâche active aujourd'hui.</p>
                <?php else: ?>
                    <p class="small mb-2">
                        Objectif : <?= formatDuration($plannedMinutesToday) ?> -
                        Realise : <?= formatDuration($minutesToday) ?>
                    </p>
                    <div class="progress mb-3" style="height: 8px;">
                        <?php
                            $pct = $plannedMinutesToday > 0 ? min(100, round(($minutesToday / $plannedMinutesToday) * 100)) : 0;
                        ?>
                        <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                    </div>

                    <ul class="list-unstyled mb-0">
                        <?php foreach ($trackableTodayTasks as $t): ?>
                            <li class="mb-1 <?= $t['status'] === 'terminee' ? 'text-decoration-line-through text-muted' : '' ?>">
                                <?php if ($t['start_time']): ?>
                                    <span class="text-muted"><?= substr($t['start_time'], 0, 5) ?></span>
                                <?php endif; ?>
                                <?= e($t['title']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Cette semaine</h5>
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fs-4"><?= formatDuration($minutesThisWeek) ?></div>
                        <div class="small text-muted">Temps</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-4"><?= $streak ?></div>
                        <div class="small text-muted">Jours consecutifs</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-4"><?= count($todayDone) ?>/<?= count($trackableTodayTasks) ?></div>
                        <div class="small text-muted">Taches du jour</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Temps d'apprentissage (7 derniers jours)</h5>
                <div class="dashboard-chart">
                    <canvas id="learning-time-chart" role="img" aria-label="Temps d'apprentissage quotidien en minutes sur les sept derniers jours"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Formations par statut</h5>
                <?php if ($formationTotal > 0): ?>
                    <div class="dashboard-chart dashboard-chart-doughnut">
                        <canvas id="formation-status-chart" role="img" aria-label="Répartition des formations par statut"></canvas>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Aucune formation enregistrée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Formations en cours</h5>
                    <a href="formations.php" class="small">Voir tout</a>
                </div>

                <?php if (empty($inProgressFormations)): ?>
                    <p class="text-muted small">Aucune formation en cours actuellement.</p>
                <?php endif; ?>

                <?php foreach ($inProgressFormations as $f): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <a href="formation_detail.php?id=<?= (int) $f['id'] ?>" class="text-decoration-none">
                                <?= e($f['name']) ?>
                            </a>
                            <span class="small text-muted"><?= (float) $f['progress_percent'] ?>%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" style="width: <?= (float) $f['progress_percent'] ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Objectifs du moment</h5>
                    <a href="goals.php" class="small">Voir tout</a>
                </div>

                <?php if (empty($activeGoals)): ?>
                    <p class="text-muted small">Aucun objectif actif.</p>
                <?php endif; ?>

                <?php foreach ($activeGoals as $g): ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between">
                            <span><?= e($g['title']) ?></span>
                            <span class="small"><?= priorityLabel($g['priority']) ?></span>
                        </div>
                        <div class="small text-muted">
                            <?= goalTypeLabel($g['type']) ?>
                            <?php if ($g['deadline']): ?>
                                - echeance <?= formatDateFr($g['deadline']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
    $weekStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT session_date) AS active_days
        FROM learning_sessions
        WHERE user_id = ? AND session_date BETWEEN ? AND ?
    ");
    $weekStmt->execute([$userId, $startOfWeek, $today]);
    $activeDaysThisWeek = (int) $weekStmt->fetchColumn();
?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title mb-2">Cette semaine en resume</h5>
        <p class="mb-0">
            Tu as appris <?= $activeDaysThisWeek ?> jour<?= $activeDaysThisWeek > 1 ? 's' : '' ?> sur 7,
            pour un total de <?= formatDuration($minutesThisWeek) ?>.
        </p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title mb-3">Progression globale</h5>
        <div class="row text-center">
            <div class="col-4">
                <div class="fs-4"><?= $globalStats['avg_progress'] ? round((float) $globalStats['avg_progress'], 1) : 0 ?>%</div>
                <div class="small text-muted">Progression moyenne</div>
            </div>
            <div class="col-4">
                <div class="fs-4"><?= (int) $globalStats['active_count'] ?></div>
                <div class="small text-muted">Formations actives</div>
            </div>
            <div class="col-4">
                <div class="fs-4"><?= (int) $globalStats['done_count'] ?>/<?= (int) $globalStats['total_count'] ?></div>
                <div class="small text-muted">Formations terminees</div>
            </div>
        </div>
    </div>
</div>

<script src="public/js/chart.umd.js"></script>
<script>
    new Chart(document.getElementById('learning-time-chart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            datasets: [{
                label: 'Minutes',
                data: <?= json_encode($chartMinutes) ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.65)',
                borderColor: 'rgb(13, 110, 253)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (context) => `${context.parsed.y} min` } }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Minutes' } }
            }
        }
    });

    <?php if ($formationTotal > 0): ?>
    new Chart(document.getElementById('formation-status-chart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($formationStatusLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            datasets: [{
                data: <?= json_encode($formationStatusValues) ?>,
                backgroundColor: ['#6c757d', '#0d6efd', '#ffc107', '#198754', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    <?php endif; ?>
</script>

<?php require __DIR__ . '/views/footer.php'; ?>