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

$goalId = (int) ($_GET['id'] ?? 0);
$goal = $goalModel->find($goalId, $userId);

if (!$goal) {
    header('Location: goals.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');

    if ($title === '') {
        $error = "Le titre est obligatoire.";
    } else {
        $goalModel->update($goalId, $userId, [
            'title'            => $title,
            'description'      => trim($_POST['description'] ?? ''),
            'type'             => $_POST['type'] ?? 'quotidien',
            'start_date'       => $_POST['start_date'] ?? null,
            'deadline'         => $_POST['deadline'] ?? null,
            'priority'         => $_POST['priority'] ?? 'normal',
            'status'           => $_POST['status'] ?? 'a_faire',
            'progress_percent' => $_POST['progress_percent'] ?? 0,
        ]);

        header('Location: goals.php');
        exit;
    }
}

$pageTitle = 'Modifier l\'objectif';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-4">Modifier l'objectif</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm">
    <?= csrfField() ?>

    <div class="mb-3">
        <label class="form-label">Titre *</label>
        <input type="text" name="title" class="form-control" value="<?= e($goal['title']) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($goal['description']) ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
                <?php foreach (['quotidien','hebdomadaire','long_terme'] as $t): ?>
                    <option value="<?= $t ?>" <?= $goal['type'] === $t ? 'selected' : '' ?>><?= goalTypeLabel($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Priorite</label>
            <select name="priority" class="form-select">
                <?php foreach (['urgent','important','normal','faible'] as $p): ?>
                    <option value="<?= $p ?>" <?= $goal['priority'] === $p ? 'selected' : '' ?>><?= priorityLabel($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Statut</label>
            <select name="status" class="form-select">
                <?php foreach (['a_faire','en_cours','terminee','abandonnee'] as $s): ?>
                    <option value="<?= $s ?>" <?= $goal['status'] === $s ? 'selected' : '' ?>><?= goalStatusLabel($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Date de debut</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($goal['start_date']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Date limite</label>
            <input type="date" name="deadline" class="form-control" value="<?= e($goal['deadline']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Progression (%)</label>
            <input type="number" min="0" max="100" name="progress_percent" class="form-control" value="<?= e((string)$goal['progress_percent']) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="goals.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
</form>

<hr class="my-4">

<form method="POST" action="goal_delete.php" onsubmit="return confirm('Supprimer definitivement cet objectif ?');">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $goal['id'] ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer cet objectif</button>
</form>

<?php require __DIR__ . '/views/footer.php'; ?>