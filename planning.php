<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Task.php';
require_once __DIR__ . '/src/LearningSession.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$taskModel = new Task($pdo);
$sessionModel = new LearningSession($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_task') {
        verifyCsrf();
        $title = trim($_POST['title'] ?? '');
        $taskDate = $_POST['task_date'] ?? '';
        $taskId = (int) ($_POST['task_id'] ?? 0);

        if ($title === '' || $taskDate === '') {
            $error = "Le titre et la date sont obligatoires.";
        } else {
            $existingTask = $taskId > 0 ? $taskModel->find($taskId, $userId) : null;
            $data = [
                'title'       => $title,
                'description' => trim($_POST['description'] ?? ''),
                'task_date'   => $taskDate,
                'start_time'  => $_POST['start_time'] ?? null,
                'end_time'    => $_POST['end_time'] ?? null,
                'category_id' => $_POST['category_id'] ?? null,
                'formation_id'=> $_POST['formation_id'] ?? null,
                'goal_id'     => $_POST['goal_id'] ?? null,
                'priority'    => $_POST['priority'] ?? 'normal',
                'status'      => $_POST['status'] ?? 'a_faire',
            ];
            $targetStatus = $data['status'];

            if ($taskId > 0 && !$existingTask) {
                $error = 'Tache introuvable.';
            } elseif ($data['status'] === 'terminee' && (!$existingTask || $existingTask['status'] !== 'terminee')) {
                $error = 'Terminez la tache avec le bouton de suivi pour enregistrer son temps reel.';
            } else {
                try {
                    if ($taskId > 0) {
                        if ($existingTask && $existingTask['status'] === 'en_cours' && $targetStatus !== 'en_cours') {
                            $sessionModel->pauseTask($taskId, $userId);
                        }
                        if ($targetStatus === 'en_cours') {
                            $data['status'] = $existingTask['status'];
                        }
                        $taskModel->update($taskId, $userId, $data);
                        if ($targetStatus === 'en_cours') {
                            $sessionModel->startTask($taskId, $userId);
                        }
                    } else {
                        if ($targetStatus === 'en_cours') {
                            $data['status'] = 'a_faire';
                        }
                        $taskId = $taskModel->create($userId, $data);
                        if ($targetStatus === 'en_cours') {
                            $sessionModel->startTask($taskId, $userId);
                        }
                    }
                } catch (RuntimeException $e) {
                    $error = $e->getMessage();
                    if ($taskId > 0) {
                        $fallbackStatus = $existingTask['status'] ?? 'a_faire';
                        $taskModel->updateStatus($taskId, $userId, $fallbackStatus);
                    }
                }

                if ($error === '') {
                    $redirect = 'planning.php?view=' . urlencode($_POST['view'] ?? 'day') . '&date=' . urlencode($taskDate);
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        }
    }

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
            $redirect = 'planning.php?view=' . urlencode($_POST['view'] ?? 'day') . '&date=' . urlencode($_POST['redirect_date'] ?? date('Y-m-d'));
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$view = $_GET['view'] ?? 'day';
$activeDate = $_GET['date'] ?? date('Y-m-d');

if (!DateTime::createFromFormat('Y-m-d', $activeDate)) {
    $activeDate = date('Y-m-d');
}

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$formations = $pdo->prepare("SELECT * FROM formations WHERE user_id = ? AND status != 'abandonnee' ORDER BY name");
$formations->execute([$userId]);
$formations = $formations->fetchAll();

$goals = $pdo->prepare("SELECT * FROM goals WHERE user_id = ? AND status IN ('a_faire','en_cours') ORDER BY title");
$goals->execute([$userId]);
$goals = $goals->fetchAll();

if ($view === 'week') {
    $weekDates = weekDatesFor($activeDate);
    $tasksByDate = $taskModel->forRange($userId, $weekDates[0], $weekDates[6]);
} else {
    $dayTasks = $taskModel->forDay($userId, $activeDate);
}
$activeTaskIds = array_flip($sessionModel->activeTaskIds($userId));

$pageTitle = 'Planning';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Planning</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="prepareCreateForm('<?= e($activeDate) ?>')">
        + Nouvelle tache
    </button>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group">
        <a href="planning.php?view=day&date=<?= e($activeDate) ?>" class="btn btn-sm <?= $view === 'day' ? 'btn-dark' : 'btn-outline-secondary' ?>">Jour</a>
        <a href="planning.php?view=week&date=<?= e($activeDate) ?>" class="btn btn-sm <?= $view === 'week' ? 'btn-dark' : 'btn-outline-secondary' ?>">Semaine</a>
    </div>

    <?php if ($view === 'day'): ?>
        <?php
            $prevDay = date('Y-m-d', strtotime($activeDate . ' -1 day'));
            $nextDay = date('Y-m-d', strtotime($activeDate . ' +1 day'));
        ?>
        <div class="d-flex align-items-center gap-2">
            <a href="planning.php?view=day&date=<?= $prevDay ?>" class="btn btn-sm btn-outline-secondary">&laquo;</a>
            <strong><?= dayLabelFr($activeDate) ?> <?= formatDateFr($activeDate) ?></strong>
            <a href="planning.php?view=day&date=<?= $nextDay ?>" class="btn btn-sm btn-outline-secondary">&raquo;</a>
            <a href="planning.php?view=day&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-link">Aujourd'hui</a>
        </div>
    <?php else: ?>
        <?php
            $prevWeek = date('Y-m-d', strtotime($activeDate . ' -7 days'));
            $nextWeek = date('Y-m-d', strtotime($activeDate . ' +7 days'));
        ?>
        <div class="d-flex align-items-center gap-2">
            <a href="planning.php?view=week&date=<?= $prevWeek ?>" class="btn btn-sm btn-outline-secondary">&laquo;</a>
            <strong>Semaine du <?= formatDateFr($weekDates[0]) ?> au <?= formatDateFr($weekDates[6]) ?></strong>
            <a href="planning.php?view=week&date=<?= $nextWeek ?>" class="btn btn-sm btn-outline-secondary">&raquo;</a>
            <a href="planning.php?view=week&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-link">Cette semaine</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($view === 'day'): ?>

    <?php if (empty($dayTasks)): ?>
        <div class="alert alert-light border">Aucune tache prevue ce jour-la.</div>
    <?php endif; ?>

    <div class="list-group">
        <?php foreach ($dayTasks as $t): ?>
            <?php
                $taskJson = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-start gap-2">
                        <?php if ($t['status'] === 'terminee'): ?>
                            <form method="POST" class="mt-1">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="view" value="day">
                                <input type="hidden" name="redirect_date" value="<?= e($activeDate) ?>">
                                <input type="hidden" name="new_status" value="a_faire">
                                <button type="submit" class="btn btn-sm btn-success">OK</button>
                            </form>
                        <?php elseif ($t['status'] === 'annulee'): ?>
                            <span class="badge text-bg-secondary mt-1">Annulee</span>
                        <?php else: ?>
                            <div class="d-flex gap-1 mt-1">
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="<?= isset($activeTaskIds[(int) $t['id']]) ? 'pause_tracking' : 'start_tracking' ?>">
                                    <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="view" value="day">
                                    <input type="hidden" name="redirect_date" value="<?= e($activeDate) ?>">
                                    <button type="submit" class="btn btn-sm <?= isset($activeTaskIds[(int) $t['id']]) ? 'btn-warning' : 'btn-outline-primary' ?>">
                                        <?= isset($activeTaskIds[(int) $t['id']]) ? 'Pause' : ($t['status'] === 'en_cours' ? 'Reprendre' : 'Demarrer') ?>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="prepareTaskCompletion(<?= (int) $t['id'] ?>)" data-bs-toggle="modal" data-bs-target="#completeTaskModal">
                                    Terminer
                                </button>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="<?= $t['status'] === 'terminee' ? 'text-decoration-line-through text-muted' : '' ?>">
                                <strong><?= e($t['title']) ?></strong>
                            </div>
                            <small class="text-muted">
                                <?php if ($t['start_time']): ?>
                                    <?= substr($t['start_time'], 0, 5) ?><?= $t['end_time'] ? ' - ' . substr($t['end_time'], 0, 5) : '' ?>
                                <?php endif; ?>
                                <?php if ($t['formation_name']): ?>
                                    - <?= e($t['formation_name']) ?>
                                <?php endif; ?>
                                <?php if ($t['category_name']): ?>
                                    - <?= e($t['category_name']) ?>
                                <?php endif; ?>
                            </small>
                            <div>
                                <span class="badge <?= taskStatusBadgeClass($t['status']) ?>"><?= taskStatusLabel($t['status']) ?></span>
                                <small class="ms-1"><?= priorityLabel($t['priority']) ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-link" onclick='prepareEditForm(<?= $taskJson ?>)' data-bs-toggle="modal" data-bs-target="#taskModal">
                            Modifier
                        </button>
                        <form method="POST" action="task_delete.php" onsubmit="return confirm('Supprimer cette tache ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <input type="hidden" name="redirect" value="planning.php?view=day&date=<?= e($activeDate) ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger">Supprimer</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>

    <div class="row g-2">
        <?php foreach ($weekDates as $d): ?>
            <div class="col">
                <div class="card h-100 <?= $d === date('Y-m-d') ? 'border-primary' : '' ?>">
                    <div class="card-header text-center py-2">
                        <div class="small text-muted"><?= dayLabelFr($d) ?></div>
                        <div><?= date('d/m', strtotime($d)) ?></div>
                    </div>
                    <div class="card-body p-2">
                        <?php $dayItems = $tasksByDate[$d] ?? []; ?>
                        <?php if (empty($dayItems)): ?>
                            <p class="text-muted small mb-0">-</p>
                        <?php endif; ?>
                        <?php foreach ($dayItems as $t): ?>
                            <?php $taskJson = htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8'); ?>
                            <div class="mb-2 pb-2 border-bottom small" style="cursor: pointer;" onclick='prepareEditForm(<?= $taskJson ?>)' data-bs-toggle="modal" data-bs-target="#taskModal">
                                <div class="<?= $t['status'] === 'terminee' ? 'text-decoration-line-through text-muted' : '' ?>">
                                    <?= e($t['title']) ?>
                                </div>
                                <?php if ($t['start_time']): ?>
                                    <span class="text-muted"><?= substr($t['start_time'], 0, 5) ?></span>
                                <?php endif; ?>
                                <span class="badge <?= taskStatusBadgeClass($t['status']) ?>" style="font-size: 0.65rem;"><?= taskStatusLabel($t['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer p-2 text-center">
                        <a href="planning.php?view=day&date=<?= e($d) ?>" class="btn btn-sm btn-outline-secondary w-100 mb-2">
                            Gerer cette journee
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="prepareCreateForm('<?= $d ?>')" data-bs-toggle="modal" data-bs-target="#taskModal">
                            +
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php if ($view === 'day'): ?>
<div class="modal fade" id="completeTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="completeTaskId">
                <input type="hidden" name="view" value="day">
                <input type="hidden" name="redirect_date" value="<?= e($activeDate) ?>">
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
<script>
function prepareTaskCompletion(taskId) {
    document.getElementById('completeTaskId').value = taskId;
    document.getElementById('manualDurationMin').value = 0;
}
</script>
<?php endif; ?>

<div class="modal fade" id="taskModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="taskForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_task">
        <input type="hidden" name="task_id" id="f_task_id" value="">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalLabel">Nouvelle tache</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Titre *</label>
                <input type="text" name="title" id="f_title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" id="f_description" class="form-control" rows="2"></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date *</label>
                    <input type="date" name="task_date" id="f_task_date" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Heure de debut</label>
                    <input type="time" name="start_time" id="f_start_time" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Heure de fin</label>
                    <input type="time" name="end_time" id="f_end_time" class="form-control">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Categorie</label>
                    <select name="category_id" id="f_category_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Formation liee</label>
                    <select name="formation_id" id="f_formation_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Objectif lie</label>
                    <select name="goal_id" id="f_goal_id" class="form-select">
                        <option value="">-- Aucun --</option>
                        <?php foreach ($goals as $g): ?>
                            <option value="<?= (int) $g['id'] ?>"><?= e($g['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Priorite</label>
                    <select name="priority" id="f_priority" class="form-select">
                        <option value="urgent">Urgent</option>
                        <option value="important">Important</option>
                        <option value="normal" selected>Normal</option>
                        <option value="faible">Faible</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Statut</label>
                    <select name="status" id="f_status" class="form-select">
                        <option value="a_faire">A faire</option>
                        <option value="en_cours">En cours</option>
                        <option value="terminee">Terminee</option>
                        <option value="reportee">Reportee</option>
                        <option value="annulee">Annulee</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('taskForm').reset();
    document.getElementById('f_task_id').value = '';
}

function prepareCreateForm(date) {
    resetForm();
    document.getElementById('taskModalLabel').textContent = 'Nouvelle tache';
    document.getElementById('f_task_date').value = date;
}

function prepareEditForm(task) {
    resetForm();
    document.getElementById('taskModalLabel').textContent = 'Modifier la tache';
    document.getElementById('f_task_id').value = task.id;
    document.getElementById('f_title').value = task.title || '';
    document.getElementById('f_description').value = task.description || '';
    document.getElementById('f_task_date').value = task.task_date || '';
    document.getElementById('f_start_time').value = task.start_time ? task.start_time.substring(0,5) : '';
    document.getElementById('f_end_time').value = task.end_time ? task.end_time.substring(0,5) : '';
    document.getElementById('f_category_id').value = task.category_id || '';
    document.getElementById('f_formation_id').value = task.formation_id || '';
    document.getElementById('f_goal_id').value = task.goal_id || '';
    document.getElementById('f_priority').value = task.priority || 'normal';
    document.getElementById('f_status').value = task.status || 'a_faire';
}
</script>

<?php require __DIR__ . '/views/footer.php'; ?>