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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();

    $sessionDateInput = $_POST['session_date'] ?? '';
    $sessionDate = is_string($sessionDateInput) ? $sessionDateInput : '';
    $durationMin = (int) ($_POST['duration_min'] ?? 0);
    $parsedSessionDate = DateTimeImmutable::createFromFormat('!Y-m-d', $sessionDate);

    if (!$parsedSessionDate || $parsedSessionDate->format('Y-m-d') !== $sessionDate || $durationMin <= 0) {
        $error = "Une date valide et une durée supérieure à zéro sont obligatoires.";
    } else {
        try {
            $sessionModel->create($userId, [
                'formation_id'  => $_POST['formation_id'] ?? null,
                'category_id'   => $_POST['category_id'] ?? null,
                'session_date'  => $sessionDate,
                'duration_min'  => $durationMin,
                'session_type'  => $_POST['session_type'] ?? 'cours',
                'notes'         => trim($_POST['notes'] ?? ''),
            ]);

            header('Location: time_tracking.php');
            exit;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }
    }
}

$today = date('Y-m-d');
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$startOfMonth = date('Y-m-01');
$endOfWeek = $today;
$endOfMonth = $today;

$minutesToday = $sessionModel->minutesForDate($userId, $today);
$minutesThisWeek = $sessionModel->totalMinutes($userId, $startOfWeek, $endOfWeek);
$minutesThisMonth = $sessionModel->totalMinutes($userId, $startOfMonth, $endOfMonth);
$minutesAllTime = $sessionModel->totalMinutesAllTime($userId);
$streak = $sessionModel->currentStreak($userId);

$byFormation = $sessionModel->totalByFormation($userId);
$byCategory = $sessionModel->totalByCategory($userId);

$recentSessions = $sessionModel->allForUser($userId, 30);

$formations = $pdo->prepare("SELECT * FROM formations WHERE user_id = ? ORDER BY name");
$formations->execute([$userId]);
$formations = $formations->fetchAll();

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$pageTitle = 'Temps d\'apprentissage';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Temps d'apprentissage</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sessionModal">
        + Enregistrer une session
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Aujourd'hui</div>
                <div class="fs-4"><?= formatDuration($minutesToday) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Cette semaine</div>
                <div class="fs-4"><?= formatDuration($minutesThisWeek) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Ce mois-ci</div>
                <div class="fs-4"><?= formatDuration($minutesThisMonth) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Total</div>
                <div class="fs-4"><?= formatDuration($minutesAllTime) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($streak > 0): ?>
    <div class="alert alert-success">
        <?= $streak ?> jour<?= $streak > 1 ? 's' : '' ?> consecutif<?= $streak > 1 ? 's' : '' ?> d'apprentissage.
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title">Temps par formation</h6>
                <?php if (empty($byFormation)): ?>
                    <p class="text-muted small mb-0">Aucune donnee pour l'instant.</p>
                <?php endif; ?>
                <?php foreach ($byFormation as $row): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span><?= e($row['name']) ?></span>
                        <span class="text-muted"><?= formatDuration((int) $row['total_min']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title">Temps par categorie</h6>
                <?php if (empty($byCategory)): ?>
                    <p class="text-muted small mb-0">Aucune donnee pour l'instant.</p>
                <?php endif; ?>
                <?php foreach ($byCategory as $row): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span><?= e($row['name']) ?></span>
                        <span class="text-muted"><?= formatDuration((int) $row['total_min']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<h5 class="mb-3">Sessions recentes</h5>
<p class="small text-muted">Les intervalles suivis en meme temps pour une tache sont fusionnes afin d'eviter les doubles comptes. La suppression d'une session fusionnee retire tout l'intervalle affiche.</p>

<?php if (empty($recentSessions)): ?>
    <div class="alert alert-light border">Aucune session enregistree pour l'instant.</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-sm align-middle">
    <thead>
        <tr>
            <th>Date</th>
            <th>Duree</th>
            <th>Type</th>
            <th>Tache</th>
            <th>Formation</th>
            <th>Categorie</th>
            <th>Notes</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recentSessions as $s): ?>
            <tr>
                <td><?= formatDateFr($s['session_date']) ?></td>
                <td><?= formatDurationSeconds((int) $s['duration_sec']) ?></td>
                <td><?= sessionTypeLabel($s['session_type']) ?></td>
                <td><?= $s['task_title'] ? e($s['task_title']) : '-' ?></td>
                <td><?= $s['formation_name'] ? e($s['formation_name']) : '-' ?></td>
                <td><?= $s['category_name'] ? e($s['category_name']) : '-' ?></td>
                <td class="small text-muted"><?= e($s['notes']) ?></td>
                <td>
                    <form method="POST" action="session_delete.php" onsubmit="return confirm('Supprimer cette session ?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">Supprimer</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="modal fade" id="sessionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Enregistrer une session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date *</label>
                    <input type="date" name="session_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duree (minutes) *</label>
                    <input type="number" name="duration_min" class="form-control" min="1" placeholder="90" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Type</label>
                <select name="session_type" class="form-select">
                    <option value="cours">Cours</option>
                    <option value="pratique">Pratique</option>
                    <option value="revision">Revision</option>
                    <option value="lecture">Lecture</option>
                    <option value="projet">Projet</option>
                </select>
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

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Ce que j'ai travaille..."></textarea>
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

<?php require __DIR__ . '/views/footer.php'; ?>