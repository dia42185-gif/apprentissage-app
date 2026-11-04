<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/VacationPeriod.php';
require_once __DIR__ . '/src/Forecast.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$vacationModel = new VacationPeriod($pdo);
$forecastModel = new Forecast($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verifyCsrf();

    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    if ($startDate === '' || $endDate === '' || $endDate < $startDate) {
        $error = "Les dates de debut et de fin sont obligatoires, et la fin doit etre apres le debut.";
    } else {
        $learningDays = $_POST['learning_days'] ?? [];
        $learningDaysStr = !empty($learningDays) ? implode(',', array_map('intval', $learningDays)) : '1,2,3,4,5,6,7';

        $vacationModel->create($userId, [
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'hours_per_day'     => $_POST['hours_per_day'] ?? 2,
            'learning_days'     => $learningDaysStr,
            'goals_description' => trim($_POST['goals_description'] ?? ''),
        ]);

        header('Location: vacation.php');
        exit;
    }
}

$vacations = $vacationModel->allForUser($userId);
$activeVacation = $vacationModel->currentOrNext($userId);

$activeVacationForecast = null;
if ($activeVacation && $activeVacation['start_date'] <= date('Y-m-d')) {
    $activeVacationForecast = $forecastModel->forVacation($activeVacation, $userId, $vacationModel);
}

$pageTitle = 'Vacances';
require __DIR__ . '/views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Periodes de vacances</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        + Nouvelle periode
    </button>
</div>

<?php if ($activeVacation && $activeVacationForecast): ?>
    <div class="card shadow-sm mb-4 border-primary">
        <div class="card-body">
            <h5 class="card-title">Période sélectionnée</h5>
            <p class="mb-2">
                Du <?= formatDateFr($activeVacation['start_date']) ?> au <?= formatDateFr($activeVacation['end_date']) ?>
                - <?= (float) $activeVacation['hours_per_day'] ?>h d'apprentissage prevues par jour
            </p>

            <?php if ($activeVacationForecast): ?>
                <p class="mb-2">
                    Jours d'apprentissage restants : <?= $activeVacationForecast['remaining_days'] ?>.
                    Capacité calculée : <?= $activeVacationForecast['hours_available'] ?>h.
                </p>
            <?php endif; ?>

            <?php if ($activeVacationForecast && $activeVacationForecast['hours_needed'] > 0): ?>
                <p>
                    Temps estimé restant pour les formations en cours : <?= $activeVacationForecast['hours_needed'] ?>h.
                </p>

                <p class="mb-0">
                    Écart estimé entre la capacité et le temps restant :
                    <?= $activeVacationForecast['balance'] ?>h.
                </p>
            <?php elseif ($activeVacationForecast): ?>
                <div class="alert alert-light border mb-0">
                    Aucune formation en cours actuellement, ou toutes tes formations en cours sont deja terminees.
                </div>
            <?php endif; ?>

            <?php if ($activeVacation['goals_description']): ?>
                <hr>
                <p class="mb-0"><strong>Objectifs de la periode :</strong><br><?= nl2br(e($activeVacation['goals_description'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($vacations)): ?>
    <div class="alert alert-light border">
        Aucune periode de vacances definie. Clique sur "+ Nouvelle periode" pour en creer une.
    </div>
<?php endif; ?>

<div class="row g-3">
    <?php foreach ($vacations as $v): ?>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1">
                                <?= formatDateFr($v['start_date']) ?> - <?= formatDateFr($v['end_date']) ?>
                            </h6>
                            <p class="small text-muted mb-2">
                                <?= (float) $v['hours_per_day'] ?>h/jour -
                                <?= $vacationModel->totalDays($v) ?> jours au total
                            </p>
                            <?php if ($v['goals_description']): ?>
                                <p class="small mb-0"><?= nl2br(e($v['goals_description'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="vacation_delete.php" onsubmit="return confirm('Supprimer cette periode ?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-link text-danger">Supprimer</button>
                        </form>
                    </div>
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
          <h5 class="modal-title">Nouvelle periode de vacances</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de debut *</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date de fin *</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Heures d'apprentissage par jour</label>
                <input type="number" step="0.5" name="hours_per_day" class="form-control" value="2">
            </div>

            <div class="mb-3">
                <label class="form-label">Jours d'apprentissage</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php
                        $dayLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
                    ?>
                    <?php foreach ($dayLabels as $num => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="learning_days[]" value="<?= $num ?>" id="day<?= $num ?>" checked>
                            <label class="form-check-label" for="day<?= $num ?>"><?= $label ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Objectifs pour cette periode</label>
                <textarea name="goals_description" class="form-control" rows="2" placeholder="Terminer la formation PHP, avancer sur..."></textarea>
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