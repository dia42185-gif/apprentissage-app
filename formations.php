<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Formation.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$formationModel = new Formation($pdo);
$userId = currentUserId();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrf();

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = "Le nom de la formation est obligatoire.";
    } else {
        $formationModel->create($userId, [
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

        header('Location: formations.php');
        exit;
    }
}

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$statusFilter = $_GET['status'] ?? null;
$formations = $formationModel->allForUser($userId, $statusFilter);

$pageTitle = 'Formations';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Mes formations</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        + Nouvelle formation
    </button>
</div>

<div class="mb-3">
    <a href="formations.php" class="btn btn-sm <?= !$statusFilter ? 'btn-dark' : 'btn-outline-secondary' ?>">Toutes</a>
    <a href="formations.php?status=en_cours" class="btn btn-sm <?= $statusFilter === 'en_cours' ? 'btn-dark' : 'btn-outline-secondary' ?>">En cours</a>
    <a href="formations.php?status=a_commencer" class="btn btn-sm <?= $statusFilter === 'a_commencer' ? 'btn-dark' : 'btn-outline-secondary' ?>">À commencer</a>
    <a href="formations.php?status=en_pause" class="btn btn-sm <?= $statusFilter === 'en_pause' ? 'btn-dark' : 'btn-outline-secondary' ?>">En pause</a>
    <a href="formations.php?status=terminee" class="btn btn-sm <?= $statusFilter === 'terminee' ? 'btn-dark' : 'btn-outline-secondary' ?>">Terminées</a>
</div>

<?php if (empty($formations)): ?>
    <div class="alert alert-light border">
        Aucune formation pour l'instant. Clique sur "+ Nouvelle formation" pour commencer.
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($formations as $f): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge <?= formationStatusBadgeClass($f['status']) ?>"><?= formationStatusLabel($f['status']) ?></span>
                        <small><?= priorityLabel($f['priority']) ?></small>
                    </div>
                    <h5 class="card-title mb-1"><?= e($f['name']) ?></h5>
                    <?php if ($f['category_name']): ?>
                        <p class="text-muted small mb-2"><?= e($f['category_name']) ?></p>
                    <?php endif; ?>

                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= (float)$f['progress_percent'] ?>%"></div>
                    </div>
                    <p class="small text-muted mb-3">
                        <?= (float) $f['progress_percent'] ?>% · <?= (int) $f['modules_count'] ?> module(s)
                    </p>

                    <a href="formation_detail.php?id=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                        Voir le détail
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Nouvelle formation</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Nom *</label>
                <input type="text" name="name" class="form-control" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catégorie</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Aucune —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Plateforme</label>
                    <input type="text" name="platform" class="form-control" placeholder="Udemy, YouTube...">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Lien</label>
                <input type="url" name="url" class="form-control" placeholder="https://...">
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Niveau</label>
                    <input type="text" name="level" class="form-control" placeholder="Débutant...">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Statut</label>
                    <select name="status" class="form-select">
                        <option value="a_commencer">À commencer</option>
                        <option value="en_cours">En cours</option>
                        <option value="en_pause">En pause</option>
                        <option value="terminee">Terminée</option>
                        <option value="abandonnee">Abandonnée</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Priorité</label>
                    <select name="priority" class="form-select">
                        <option value="urgent">Urgent</option>
                        <option value="important">Important</option>
                        <option value="normal" selected>Normal</option>
                        <option value="faible">Faible</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date d'inscription</label>
                    <input type="date" name="registration_date" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Date de début</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fin prévue</label>
                    <input type="date" name="planned_end_date" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Heures estimées</label>
                <input type="number" step="0.5" name="estimated_hours" class="form-control" placeholder="20">
            </div>

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary">Créer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>