<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Goal.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$goalModel = new Goal($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();

    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $error = "Le titre est obligatoire.";
    } else {
        $goalModel->create($userId, [
            'title'       => $title,
            'description' => trim($_POST['description'] ?? ''),
            'type'        => $_POST['type'] ?? 'quotidien',
            'start_date'  => $_POST['start_date'] ?? null,
            'deadline'    => $_POST['deadline'] ?? null,
            'priority'    => $_POST['priority'] ?? 'normal',
        ]);

        header('Location: goals.php');
        exit;
    }
}

$typeFilter = $_GET['type'] ?? null;
$goals = $goalModel->allForUser($userId, $typeFilter);

$pageTitle = 'Objectifs';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Mes objectifs</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        + Nouvel objectif
    </button>
</div>

<div class="mb-3">
    <a href="goals.php" class="btn btn-sm <?= !$typeFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">Tous</a>
    <a href="goals.php?type=quotidien" class="btn btn-sm <?= $typeFilter === 'quotidien' ? 'btn-dark' : 'btn-outline-secondary' ?>">Quotidiens</a>
    <a href="goals.php?type=hebdomadaire" class="btn btn-sm <?= $typeFilter === 'hebdomadaire' ? 'btn-dark' : 'btn-outline-secondary' ?>">Hebdomadaires</a>
    <a href="goals.php?type=long_terme" class="btn btn-sm <?= $typeFilter === 'long_terme' ? 'btn-dark' : 'btn-outline-secondary' ?>">Long terme</a>
</div>

<?php if (empty($goals)): ?>
    <div class="alert alert-light border">
        Aucun objectif pour l'instant. Clique sur "+ Nouvel objectif" pour commencer.
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($goals as $g): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-secondary"><?= goalTypeLabel($g['type']) ?></span>
                        <small><?= priorityLabel($g['priority']) ?></small>
                    </div>
                    <h5 class="card-title mb-1"><?= e($g['title']) ?></h5>
                    <?php if ($g['description']): ?>
                        <p class="text-muted small mb-2"><?= e($g['description']) ?></p>
                    <?php endif; ?>

                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= (float) $g['progress_percent'] ?>%"></div>
                    </div>
                    <p class="small text-muted mb-2">
                        <?= (float) $g['progress_percent'] ?>% - <?= goalStatusLabel($g['status']) ?>
                    </p>

                    <?php if ($g['deadline']): ?>
                        <p class="small mb-3">
                            Echeance : <?= formatDateFr($g['deadline']) ?>
                            <?php if ($g['status'] !== 'terminee' && strtotime($g['deadline']) < strtotime('today')): ?>
                                <span class="text-danger">(en retard)</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <a href="goal_edit.php?id=<?= (int) $g['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                        Modifier
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Nouvel objectif</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Titre *</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="quotidien">Quotidien</option>
                        <option value="hebdomadaire">Hebdomadaire</option>
                        <option value="long_terme">Long terme</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Priorite</label>
                    <select name="priority" class="form-select">
                        <option value="urgent">Urgent</option>
                        <option value="important">Important</option>
                        <option value="normal" selected>Normal</option>
                        <option value="faible">Faible</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de debut</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date limite</label>
                    <input type="date" name="deadline" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Creer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>