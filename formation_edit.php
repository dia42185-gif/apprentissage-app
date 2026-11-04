<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Formation.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$formationModel = new Formation($pdo);

$formationId = (int) ($_GET['id'] ?? 0);
$formation = $formationModel->find($formationId, $userId);

if (!$formation) {
    header('Location: formations.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = "Le nom est obligatoire.";
    } else {
        $formationModel->update($formationId, $userId, [
            'category_id'       => $_POST['category_id'] ?? null,
            'name'              => $name,
            'description'       => trim($_POST['description'] ?? ''),
            'platform'          => trim($_POST['platform'] ?? ''),
            'url'               => trim($_POST['url'] ?? ''),
            'level'             => trim($_POST['level'] ?? ''),
            'status'            => $_POST['status'] ?? 'a_commencer',
            'priority'          => $_POST['priority'] ?? 'normal',
            'registration_date' => $_POST['registration_date'] ?? null,
            'start_date'        => $_POST['start_date'] ?? null,
            'planned_end_date'  => $_POST['planned_end_date'] ?? null,
            'estimated_hours'   => $_POST['estimated_hours'] ?? 0,
            'notes'             => trim($_POST['notes'] ?? ''),
        ]);

        header('Location: formation_detail.php?id=' . $formationId);
        exit;
    }
}

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$pageTitle = 'Modifier — ' . $formation['name'];
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-4">Modifier la formation</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm">
    <?= csrfField() ?>

    <div class="mb-3">
        <label class="form-label">Nom *</label>
        <input type="text" name="name" class="form-control" value="<?= e($formation['name']) ?>" required>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Catégorie</label>
            <select name="category_id" class="form-select">
                <option value="">— Aucune —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $formation['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">Plateforme</label>
            <input type="text" name="platform" class="form-control" value="<?= e($formation['platform']) ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Lien</label>
        <input type="url" name="url" class="form-control" value="<?= e($formation['url']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($formation['description']) ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Niveau</label>
            <input type="text" name="level" class="form-control" value="<?= e($formation['level']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Statut</label>
            <select name="status" class="form-select">
                <?php foreach (['a_commencer','en_cours','en_pause','terminee','abandonnee'] as $s): ?>
                    <option value="<?= $s ?>" <?= $formation['status'] === $s ? 'selected' : '' ?>>
                        <?= formationStatusLabel($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Priorité</label>
            <select name="priority" class="form-select">
                <?php foreach (['urgent','important','normal','faible'] as $p): ?>
                    <option value="<?= $p ?>" <?= $formation['priority'] === $p ? 'selected' : '' ?>>
                        <?= priorityLabel($p) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">Date d'inscription</label>
            <input type="date" name="registration_date" class="form-control" value="<?= e($formation['registration_date']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Date de début</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($formation['start_date']) ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">Fin prévue</label>
            <input type="date" name="planned_end_date" class="form-control" value="<?= e($formation['planned_end_date']) ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Heures estimées</label>
        <input type="number" step="0.5" name="estimated_hours" class="form-control" value="<?= e((string)$formation['estimated_hours']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($formation['notes']) ?></textarea>
    </div>

    <div class="d-flex justify-content-between">
        <a href="formation_detail.php?id=<?= (int) $formation['id'] ?>" class="btn btn-secondary">Annuler</a>
        <div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
    </div>
</form>

<hr class="my-4">

<form method="POST" action="formation_delete.php" onsubmit="return confirm('Supprimer définitivement cette formation et tous ses modules ?');">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $formation['id'] ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer cette formation</button>
</form>

<?php require __DIR__ . '/views/footer.php'; ?>