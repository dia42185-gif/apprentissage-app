<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Formation.php';
require_once __DIR__ . '/src/FormationModule.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$formationModel = new Formation($pdo);
$moduleModel = new FormationModule($pdo);

$formationId = (int) ($_GET['id'] ?? 0);
$formation = $formationModel->find($formationId, $userId);

if (!$formation) {
    header('Location: formations.php');
    exit;
}

$formationModel->recalculateProgress($formationId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_module') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $moduleModel->create($formationId, [
                'title'                  => $title,
                'estimated_duration_min' => $_POST['estimated_duration_min'] ?? 0,
                'planned_date'           => $_POST['planned_date'] ?? null,
                'difficulty'             => $_POST['difficulty'] ?? 'moyen',
                'notes'                  => trim($_POST['notes'] ?? ''),
                'url'                    => trim($_POST['url'] ?? ''),
            ]);
            $formationModel->recalculateProgress($formationId);
        }
    }

    if ($action === 'toggle_module') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'a_faire';

        if ($moduleModel->belongsToUser($moduleId, $userId)) {
            $moduleModel->toggleStatus($moduleId, $newStatus);
            $formationModel->recalculateProgress($formationId);
        }
    }

    if ($action === 'delete_module') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        if ($moduleModel->belongsToUser($moduleId, $userId)) {
            $moduleModel->delete($moduleId);
            $formationModel->recalculateProgress($formationId);
        }
    }

    header('Location: formation_detail.php?id=' . $formationId);
    exit;
}

$formation = $formationModel->find($formationId, $userId);
$modules = $moduleModel->allForFormation($formationId);

$pageTitle = $formation['name'];
require __DIR__ . '/views/header.php';
?>

<div class="mb-3">
    <a href="formations.php" class="text-decoration-none">&larr; Retour aux formations</a>
</div>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <span class="badge <?= formationStatusBadgeClass($formation['status']) ?> mb-2"><?= formationStatusLabel($formation['status']) ?></span>
        <h2 class="mb-1"><?= e($formation['name']) ?></h2>
        <?php if ($formation['category_name']): ?>
            <p class="text-muted mb-0"><?= e($formation['category_name']) ?><?= $formation['platform'] ? ' · ' . e($formation['platform']) : '' ?></p>
        <?php endif; ?>
    </div>
    <a href="formation_edit.php?id=<?= (int) $formation['id'] ?>" class="btn btn-outline-secondary btn-sm">Modifier</a>
</div>

<?php if ($formation['description']): ?>
    <p><?= nl2br(e($formation['description'])) ?></p>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="progress mb-2" style="height: 12px;">
            <div class="progress-bar bg-success" style="width: <?= (float) $formation['progress_percent'] ?>%"></div>
        </div>
        <p class="text-muted small">
            <?= (float) $formation['progress_percent'] ?>% terminé ·
            <?= (float) $formation['actual_hours'] ?>h effectuées
            <?php if ($formation['estimated_hours'] > 0): ?>
                / <?= (float) $formation['estimated_hours'] ?>h estimées
            <?php endif; ?>
        </p>
    </div>
</div>

<h4 class="mb-3">Modules</h4>

<?php if (empty($modules)): ?>
    <p class="text-muted">Aucun module pour l'instant. Ajoute le premier ci-dessous.</p>
<?php endif; ?>

<div class="list-group mb-4">
    <?php foreach ($modules as $m): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center">
            <div class="form-check d-flex align-items-center gap-2">
                <form method="POST" class="d-flex align-items-center gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_module">
                    <input type="hidden" name="module_id" value="<?= (int) $m['id'] ?>">
                    <input type="hidden" name="new_status" value="<?= $m['status'] === 'termine' ? 'a_faire' : 'termine' ?>">
                    <button type="submit" class="btn btn-sm <?= $m['status'] === 'termine' ? 'btn-success' : 'btn-outline-secondary' ?>">
                        <?= $m['status'] === 'termine' ? 'Terminé' : 'À faire' ?>
                    </button>
                </form>
                <div>
                    <div class="<?= $m['status'] === 'termine' ? 'text-decoration-line-through text-muted' : '' ?>">
                        <?= e($m['title']) ?>
                    </div>
                    <small class="text-muted">
                        <?= moduleStatusLabel($m['status']) ?>
                        <?php if ($m['estimated_duration_min']): ?>
                            · <?= formatDuration((int) $m['estimated_duration_min']) ?> estimé
                        <?php endif; ?>
                        <?php if ($m['planned_date']): ?>
                            · prévu le <?= formatDateFr($m['planned_date']) ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('Supprimer ce module ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_module">
                <input type="hidden" name="module_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="btn btn-sm btn-link text-danger">Supprimer</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-body">
        <h6 class="card-title">+ Ajouter un module</h6>
        <form method="POST" class="row g-2">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_module">
            <div class="col-md-4">
                <input type="text" name="title" class="form-control" placeholder="Titre du module" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="estimated_duration_min" class="form-control" placeholder="Durée (min)">
            </div>
            <div class="col-md-2">
                <input type="date" name="planned_date" class="form-control">
            </div>
            <div class="col-md-2">
                <select name="difficulty" class="form-select">
                    <option value="facile">Facile</option>
                    <option value="moyen" selected>Moyen</option>
                    <option value="difficile">Difficile</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>