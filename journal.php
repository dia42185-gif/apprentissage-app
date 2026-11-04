<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/JournalEntry.php';

startSecureSession();
requireLogin();

$pdo = getPDO();
$userId = currentUserId();
$journalModel = new JournalEntry($pdo);

$today = date('Y-m-d');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verifyCsrf();

    $entryDate = $_POST['entry_date'] ?? $today;

    $journalModel->save($userId, [
        'entry_date'     => $entryDate,
        'what_learned'   => trim($_POST['what_learned'] ?? ''),
        'what_was_hard'  => trim($_POST['what_was_hard'] ?? ''),
        'what_to_review' => trim($_POST['what_to_review'] ?? ''),
        'next_step'      => trim($_POST['next_step'] ?? ''),
    ]);

    header('Location: journal.php');
    exit;
}

$todayEntry = $journalModel->findByDate($userId, $today);
$pastEntries = $journalModel->allForUser($userId, 30);

$pastEntries = array_filter($pastEntries, fn($e) => $e['entry_date'] !== $today);

$pageTitle = 'Journal';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-4">Journal d'apprentissage</h2>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Aujourd'hui - <?= formatDateFr($today) ?></h5>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="entry_date" value="<?= $today ?>">

            <div class="mb-3">
                <label class="form-label">Qu'est-ce que j'ai appris aujourd'hui ?</label>
                <textarea name="what_learned" class="form-control" rows="2"><?= e($todayEntry['what_learned'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Qu'est-ce qui m'a pose probleme ?</label>
                <textarea name="what_was_hard" class="form-control" rows="2"><?= e($todayEntry['what_was_hard'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Qu'est-ce que je dois revoir ?</label>
                <textarea name="what_to_review" class="form-control" rows="2"><?= e($todayEntry['what_to_review'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Quelle est la prochaine etape ?</label>
                <textarea name="next_step" class="form-control" rows="2"><?= e($todayEntry['next_step'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </div>
</div>

<h5 class="mb-3">Entrees precedentes</h5>

<?php if (empty($pastEntries)): ?>
    <p class="text-muted">Aucune entree precedente pour l'instant.</p>
<?php endif; ?>

<div class="accordion" id="journalAccordion">
    <?php foreach ($pastEntries as $i => $entry): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#entry<?= $i ?>">
                    <?= dayLabelFr($entry['entry_date']) ?> <?= formatDateFr($entry['entry_date']) ?>
                </button>
            </h2>
            <div id="entry<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#journalAccordion">
                <div class="accordion-body">
                    <?php if ($entry['what_learned']): ?>
                        <p><strong>Appris :</strong><br><?= nl2br(e($entry['what_learned'])) ?></p>
                    <?php endif; ?>
                    <?php if ($entry['what_was_hard']): ?>
                        <p><strong>Difficultes :</strong><br><?= nl2br(e($entry['what_was_hard'])) ?></p>
                    <?php endif; ?>
                    <?php if ($entry['what_to_review']): ?>
                        <p><strong>A revoir :</strong><br><?= nl2br(e($entry['what_to_review'])) ?></p>
                    <?php endif; ?>
                    <?php if ($entry['next_step']): ?>
                        <p class="mb-2"><strong>Prochaine etape :</strong><br><?= nl2br(e($entry['next_step'])) ?></p>
                    <?php endif; ?>

                    <form method="POST" action="journal_delete.php" onsubmit="return confirm('Supprimer cette entree ?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">Supprimer</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/views/footer.php'; ?>