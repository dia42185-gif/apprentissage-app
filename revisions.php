<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/Revision.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$revisionModel = new Revision($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        verifyCsrf();
        $notion = trim($_POST['notion'] ?? '');

        if ($notion === '') {
            $error = "La notion est obligatoire.";
        } else {
            $revisionModel->create($userId, [
                'formation_id'  => $_POST['formation_id'] ?? null,
                'category_id'   => $_POST['category_id'] ?? null,
                'notion'        => $notion,
                'learned_date'  => $_POST['learned_date'] ?? date('Y-m-d'),
                'mastery_level' => $_POST['mastery_level'] ?? 'debutant',
                'notes'         => trim($_POST['notes'] ?? ''),
            ]);
            header('Location: revisions.php');
            exit;
        }
    }

    if ($action === 'mark_reviewed') {
        verifyCsrf();
        $revisionId = (int) ($_POST['revision_id'] ?? 0);
        $newLevel = $_POST['new_mastery_level'] ?? 'moyen';
        $revisionModel->markReviewed($revisionId, $userId, $newLevel);
        header('Location: revisions.php');
        exit;
    }
}

$dueRevisions = $revisionModel->dueForUser($userId);
$allRevisions = $revisionModel->allForUser($userId);

$dueIds = array_column($dueRevisions, 'id');
$otherRevisions = array_filter($allRevisions, fn($r) => !in_array($r['id'], $dueIds, true));

$formations = $pdo->prepare("SELECT * FROM formations WHERE user_id = ? ORDER BY name");
$formations->execute([$userId]);
$formations = $formations->fetchAll();

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$pageTitle = 'Revisions';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Revisions</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        + Nouvelle notion
    </button>
</div>

<h5 class="mb-3">A revoir aujourd'hui</h5>

<?php if (empty($dueRevisions)): ?>
    <div class="alert alert-light border">Rien a reviser pour aujourd'hui.</div>
<?php endif; ?>

<div class="list-group mb-4">
    <?php foreach ($dueRevisions as $r): ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong><?= e($r['notion']) ?></strong>
                    <span class="badge <?= masteryLevelBadgeClass($r['mastery_level']) ?> ms-2"><?= masteryLevelLabel($r['mastery_level']) ?></span>
                    <div class="small text-muted">
                        <?= $r['formation_name'] ? e($r['formation_name']) : '' ?>
                        <?= $r['category_name'] ? ' - ' . e($r['category_name']) : '' ?>
                        <?php if (strtotime($r['next_review_date']) < strtotime('today')): ?>
                            <span class="text-danger">- en retard (prevu le <?= formatDateFr($r['next_review_date']) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($r['notes']): ?>
                        <p class="small mb-0 mt-1"><?= nl2br(e($r['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" class="d-flex align-items-center gap-2 mt-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_reviewed">
                <input type="hidden" name="revision_id" value="<?= (int) $r['id'] ?>">
                <label class="small mb-0">Niveau apres cette revision :</label>
                <select name="new_mastery_level" class="form-select form-select-sm" style="width: auto;">
                    <option value="debutant" <?= $r['mastery_level'] === 'debutant' ? 'selected' : '' ?>>Debutant</option>
                    <option value="moyen" <?= $r['mastery_level'] === 'moyen' ? 'selected' : '' ?>>Moyen</option>
                    <option value="bon" <?= $r['mastery_level'] === 'bon' ? 'selected' : '' ?>>Bon</option>
                    <option value="maitrise" <?= $r['mastery_level'] === 'maitrise' ? 'selected' : '' ?>>Maitrise</option>
                </select>
                <button type="submit" class="btn btn-sm btn-success">Marquer comme revise</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<h5 class="mb-3">Autres revisions</h5>

<?php if (empty($otherRevisions)): ?>
    <p class="text-muted">Aucune revision programmee.</p>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th>Notion</th>
            <th>Niveau</th>
            <th>Date de revision</th>
            <th>Formation</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($otherRevisions as $r): ?>
            <tr>
                <td><?= e($r['notion']) ?></td>
                <td><span class="badge <?= masteryLevelBadgeClass($r['mastery_level']) ?>"><?= masteryLevelLabel($r['mastery_level']) ?></span></td>
                <td><?= $r['next_review_date'] ? formatDateFr($r['next_review_date']) : '-' ?></td>
                <td><?= $r['formation_name'] ? e($r['formation_name']) : '-' ?></td>
                <td>
                    <form method="POST" action="revision_delete.php" onsubmit="return confirm('Supprimer cette notion ?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Nouvelle notion a reviser</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Notion *</label>
                <input type="text" name="notion" class="form-control" placeholder="Les closures en PHP" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Formation</label>
                    <select name="formation_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Categorie</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- Aucune --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date d'apprentissage</label>
                    <input type="date" name="learned_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Niveau de maitrise initial</label>
                    <select name="mastery_level" class="form-select">
                        <option value="debutant" selected>Debutant</option>
                        <option value="moyen">Moyen</option>
                        <option value="bon">Bon</option>
                        <option value="maitrise">Maitrise</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
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