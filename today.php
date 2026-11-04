<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Task.php';
require_once __DIR__ . '/src/LearningSession.php';
require_once __DIR__ . '/includes/motivation.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$taskModel = new Task($pdo);
$sessionModel = new LearningSession($pdo);

$today = date('Y-m-d');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['start_tracking', 'pause_tracking', 'complete_task', 'toggle_status'], true)) {
    verifyCsrf();
    $taskId = (int) ($_POST['task_id'] ?? 0);

        if ($taskId < 1) {
            $error = 'Tache invalide.';
        } elseif ($action === 'start_tracking') {
            try {
                $sessionModel->startTask($taskId, $userId);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'pause_tracking') {
            $sessionModel->pauseTask($taskId, $userId);
        } elseif ($action === 'complete_task') {
            $manualMinutes = filter_var($_POST['manual_duration_min'] ?? null, FILTER_VALIDATE_INT);
            if ($manualMinutes === false) {
                $error = 'Saisissez un nombre entier de minutes supplementaires.';
            } else {
                try {
                    $sessionModel->completeTask($taskId, $userId, $manualMinutes);
                } catch (InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        } elseif (($_POST['new_status'] ?? '') === 'a_faire') {
            $taskModel->updateStatus($taskId, $userId, 'a_faire');
        }

        if ($error === '') {
            header('Location: today.php');
            exit;
        }
    }
}

$todayTasks = $taskModel->forDay($userId, $today);
$doneTasks = array_filter($todayTasks, fn($t) => $t['status'] === 'terminee');
$remainingTasks = array_values(array_filter($todayTasks, fn($t) => !in_array($t['status'], ['terminee', 'annulee'], true)));

$minutesToday = $sessionModel->minutesForDate($userId, $today);
$activeTaskIds = array_flip($sessionModel->activeTaskIds($userId));
$plannedMinutes = array_sum(array_map(
    fn($t) => $t['status'] === 'annulee' ? 0 : (int) ($t['duration_min'] ?? 0),
    $todayTasks
));
$trackableTasks = array_filter($todayTasks, fn($t) => $t['status'] !== 'annulee');
$remainingMinutes = max(0, $plannedMinutes - $minutesToday);

$overdueTasks = $taskModel->overdueForUser($userId, 10);

$motivation = getMotivationalMessage(
    $minutesToday,
    $plannedMinutes,
    count($overdueTasks),
    count($doneTasks),
    count($trackableTasks)
);

$nextTask = null;
foreach ($remainingTasks as $t) {
    if ($t['start_time']) {
        $nextTask = $t;
        break;
    }
}
if (!$nextTask && !empty($remainingTasks)) {
    $nextTask = $remainingTasks[0];
}

$pageTitle = 'Ma journee';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-1">Ma journee</h2>
<p class="text-muted mb-4"><?= dayLabelFr($today) ?> <?= formatDateFr($today) ?></p>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="alert <?= motivationBadgeClass($motivation['type']) ?>">
    <?= e($motivation['text']) ?>
</div>

<?php if (!empty($overdueTasks)): ?>
    <div class="alert alert-warning">
        <strong>Taches en retard :</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($overdueTasks as $ot): ?>
                <li><?= e($ot['title']) ?> - prevue le <?= formatDateFr($ot['task_date']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Objectif du jour</div>
                <div class="fs-5"><?= formatDuration($plannedMinutes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Realise</div>
                <div class="fs-5"><?= formatDuration($minutesToday) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Reste</div>
                <div class="fs-5"><?= formatDuration($remainingMinutes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Taches</div>
                <div class="fs-5"><?= count($doneTasks) ?>/<?= count($trackableTasks) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($nextTask): ?>
    <div class="alert alert-primary">
        <strong>Prochaine tache :</strong> <?= e($nextTask['title']) ?>
        <?php if ($nextTask['start_time']): ?>
            a <?= substr($nextTask['start_time'], 0, 5) ?>
        <?php endif; ?>
    </div>
<?php elseif (empty($todayTasks)): ?>
    <div class="alert alert-light border">
        Aucune tache prevue aujourd'hui.
        <a href="planning.php?view=day&date=<?= $today ?>" class="alert-link">Ajouter une tache</a>
    </div>
<?php elseif (!empty($trackableTasks) && count($doneTasks) === count($trackableTasks)): ?>
    <div class="alert alert-success">
        Toutes les taches du jour sont terminees.
    </div>
<?php else: ?>
    <div class="alert alert-light border">
        Aucune tache active aujourd'hui.
    </div>
<?php endif; ?>

<h5 class="mb-3">Planning du jour</h5>

<div class="list-group mb-4">
    <?php foreach ($todayTasks as $t): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <?php if ($t['status'] === 'terminee'): ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="new_status" value="a_faire">
                        <button type="submit" class="btn btn-sm btn-success">OK</button>
                    </form>
                <?php elseif ($t['status'] === 'annulee'): ?>
                    <span class="badge text-bg-secondary">Annulee</span>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="<?= isset($activeTaskIds[(int) $t['id']]) ? 'pause_tracking' : 'start_tracking' ?>">
                        <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= isset($activeTaskIds[(int) $t['id']]) ? 'btn-warning' : 'btn-outline-primary' ?>">
                            <?= isset($activeTaskIds[(int) $t['id']]) ? 'Pause' : ($t['status'] === 'en_cours' ? 'Reprendre' : 'Demarrer') ?>
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="prepareTaskCompletion(<?= (int) $t['id'] ?>)" data-bs-toggle="modal" data-bs-target="#completeTaskModal">
                        Terminer
                    </button>
                <?php endif; ?>
                <div>
                    <div class="<?= $t['status'] === 'terminee' ? 'text-decoration-line-through text-muted' : '' ?>">
                        <?= e($t['title']) ?>
                    </div>
                    <small class="text-muted">
                        <?php if ($t['start_time']): ?>
                            <?= substr($t['start_time'], 0, 5) ?><?= $t['end_time'] ? ' - ' . substr($t['end_time'], 0, 5) : '' ?>
                        <?php endif; ?>
                        <?php if ($t['formation_name']): ?>
                            - <?= e($t['formation_name']) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <span class="badge <?= taskStatusBadgeClass($t['status']) ?>"><?= taskStatusLabel($t['status']) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="completeTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="completeTaskId">
                <div class="modal-header">
                    <h5 class="modal-title">Terminer la tache</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="manualDurationMin">Temps reel additionnel (minutes)</label>
                    <input type="number" name="manual_duration_min" id="manualDurationMin" class="form-control" min="0" max="1440" value="0" required>
                    <div class="form-text">Saisissez le temps reel distinct de tous les intervalles du chronometre et des Pomodoros associes, meme s'ils ne sont pas encore enregistres. N'utilisez pas la duree planifiee.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer et terminer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<a href="planning.php?view=day&date=<?= $today ?>" class="btn btn-outline-primary btn-sm">Gerer le planning d'aujourd'hui</a>

<script>
function prepareTaskCompletion(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('manualDurationMin').value = 0;
}
</script>

<?php require __DIR__ . '/views/footer.php'; ?>