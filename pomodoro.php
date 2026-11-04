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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'log_session') {
    verifyCsrf();

    try {
        $intervalJson = $_POST['intervals'] ?? '';
        $intervals = is_string($intervalJson)
            ? json_decode($intervalJson, true, 512, JSON_THROW_ON_ERROR)
            : null;
        if (!is_array($intervals) || $intervals === []) {
            throw new InvalidArgumentException('Aucun intervalle de travail mesure n’a ete trouve.');
        }
        $sessionModel->createIntervals($userId, [
            'task_id' => $_POST['task_id'] ?? null,
            'formation_id' => $_POST['formation_id'] ?? null,
            'category_id' => $_POST['category_id'] ?? null,
            'session_type' => 'pratique',
            'notes' => trim($_POST['notes'] ?? ''),
        ], $intervals);
        header('Location: pomodoro.php?logged=1');
        exit;
    } catch (JsonException | InvalidArgumentException $e) {
        $error = $e instanceof JsonException
            ? 'Les horaires de la session sont invalides.'
            : $e->getMessage();
    }
}

$formations = $pdo->prepare("SELECT * FROM formations WHERE user_id = ? AND status = 'en_cours' ORDER BY name");
$formations->execute([$userId]);
$formations = $formations->fetchAll();

$categories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$categories->execute([$userId]);
$categories = $categories->fetchAll();

$tasks = $pdo->prepare("
    SELECT id, title, task_date FROM tasks
    WHERE user_id = ? AND status != 'annulee'
    ORDER BY task_date DESC, title
    LIMIT 100
");
$tasks->execute([$userId]);
$tasks = $tasks->fetchAll();

$pageTitle = 'Pomodoro';
require __DIR__ . '/views/header.php';
?>

<h2 class="mb-4">Pomodoro</h2>

<?php if (isset($_GET['logged'])): ?>
    <div class="alert alert-success">Session enregistree dans ton suivi du temps.</div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm text-center">
            <div class="card-body py-5">

                <div class="mb-3">
                    <select id="modeSelect" class="form-select form-select-sm w-auto mx-auto">
                        <option value="25">Travail - 25 min</option>
                        <option value="5">Pause courte - 5 min</option>
                        <option value="15">Pause longue - 15 min</option>
                    </select>
                </div>

                <div id="timerDisplay" class="display-1 fw-bold mb-4">25:00</div>

                <div class="mb-4">
                    <button id="startBtn" class="btn btn-primary btn-lg px-4">Demarrer</button>
                    <button id="pauseBtn" class="btn btn-outline-secondary btn-lg px-4" style="display:none;">Pause</button>
                    <button id="resetBtn" class="btn btn-outline-danger btn-lg px-4 ms-2">Reinitialiser</button>
                </div>

                <p class="text-muted small mb-0">
                    Cycles de travail termines aujourd'hui : <span id="cycleCount">0</span>
                </p>
            </div>
        </div>

        <div class="card shadow-sm mt-3" id="logCard" style="display:none;">
            <div class="card-body">
                <h6 class="card-title">Enregistrer ce temps de travail</h6>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="log_session">
                    <input type="hidden" name="intervals" id="logIntervals" value="[]">

                    <div class="mb-2">
                        <label class="form-label small">Tache associee</label>
                        <select name="task_id" class="form-select form-select-sm">
                            <option value="">-- Aucune tache --</option>
                            <?php foreach ($tasks as $task): ?>
                                <option value="<?= (int) $task['id'] ?>"><?= e($task['title']) ?> — <?= formatDateFr($task['task_date']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Choisissez la tache suivie en parallele. Sa formation et sa categorie seront utilisees pour cette session.</small>
                    </div>

                    <div class="mb-2">
                        <select name="formation_id" class="form-select form-select-sm">
                            <option value="">-- Aucune formation --</option>
                            <?php foreach ($formations as $f): ?>
                                <option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">-- Aucune categorie --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes rapides (optionnel)">
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100">Enregistrer dans mon temps d'apprentissage</button>
                </form>
            </div>
        </div>
    </div>
</div>

<audio id="alarmSound" preload="auto">
    <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav">
</audio>

<script>
(function () {
    const modeSelect = document.getElementById('modeSelect');
    const display = document.getElementById('timerDisplay');
    const startBtn = document.getElementById('startBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const resetBtn = document.getElementById('resetBtn');
    const cycleCountEl = document.getElementById('cycleCount');
    const logCard = document.getElementById('logCard');
    const logIntervals = document.getElementById('logIntervals');
    const alarmSound = document.getElementById('alarmSound');

    const STORAGE_KEY = 'pomodoro_state';

    let remainingSeconds = parseInt(modeSelect.value, 10) * 60;
    let timerInterval = null;
    let isRunning = false;
    let cycleCount = 0;
    let endTimestamp = null;
    let workIntervals = [];
    let segmentStartedAt = null;
    let workSessionComplete = false;

    function saveState() {
        const state = {
            mode: modeSelect.value,
            remainingSeconds: remainingSeconds,
            isRunning: isRunning,
            endTimestamp: endTimestamp,
            cycleCount: cycleCount,
            workIntervals: workIntervals,
            segmentStartedAt: segmentStartedAt,
            workSessionComplete: workSessionComplete,
            savedAt: Date.now(),
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }

    function loadState() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;

        try {
            const state = JSON.parse(raw);
            modeSelect.value = state.mode || modeSelect.value;
            cycleCount = state.cycleCount || 0;
            workIntervals = Array.isArray(state.workIntervals) ? state.workIntervals : [];
            segmentStartedAt = Number.isFinite(state.segmentStartedAt) ? state.segmentStartedAt : null;
            workSessionComplete = state.workSessionComplete === true;
            cycleCountEl.textContent = cycleCount;

            if (state.isRunning && state.endTimestamp) {
                const secondsLeft = Math.round((state.endTimestamp - Date.now()) / 1000);
                if (secondsLeft > 0) {
                    remainingSeconds = secondsLeft;
                    endTimestamp = state.endTimestamp;
                    startTimer(true);
                } else {
                    remainingSeconds = 0;
                    endTimestamp = state.endTimestamp;
                    handleTimerEnd();
                }
            } else {
                remainingSeconds = state.remainingSeconds || (parseInt(modeSelect.value, 10) * 60);
                if (workSessionComplete) {
                    logIntervals.value = JSON.stringify(workIntervals);
                    logCard.style.display = workIntervals.length ? 'block' : 'none';
                }
            }
        } catch (e) {
            localStorage.removeItem(STORAGE_KEY);
        }
    }

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return m + ':' + s;
    }

    function updateDisplay() {
        display.textContent = formatTime(Math.max(0, remainingSeconds));
        document.title = formatTime(Math.max(0, remainingSeconds)) + ' - Pomodoro';
    }

    function closeWorkInterval(endTime) {
        if (!segmentStartedAt) return;
        const start = Math.floor(segmentStartedAt / 1000);
        const end = Math.floor(endTime / 1000);
        if (end > start) {
            workIntervals.push({start: start, end: end});
        }
        segmentStartedAt = null;
    }

    function handleTimerEnd() {
        const timerEnd = endTimestamp || Date.now();
        const wasWorkSession = modeSelect.value === '25';
        closeWorkInterval(timerEnd);
        clearInterval(timerInterval);
        isRunning = false;
        endTimestamp = null;
        startBtn.style.display = 'inline-block';
        pauseBtn.style.display = 'none';
        alarmSound.play().catch(() => {});

        if (wasWorkSession && workIntervals.length) {
            cycleCount++;
            cycleCountEl.textContent = cycleCount;
            workSessionComplete = true;
            logIntervals.value = JSON.stringify(workIntervals);
            logCard.style.display = 'block';
        }

        remainingSeconds = parseInt(modeSelect.value, 10) * 60;
        document.title = 'Pomodoro';
        updateDisplay();
        saveState();
    }

    function tick() {
        remainingSeconds = Math.round((endTimestamp - Date.now()) / 1000);

        if (remainingSeconds <= 0) {
            handleTimerEnd();
            return;
        }

        updateDisplay();
        saveState();
    }

    function startTimer(isResuming) {
        if (isRunning && !isResuming) return;
        isRunning = true;

        if (!isResuming) {
            endTimestamp = Date.now() + remainingSeconds * 1000;
        }
        if (modeSelect.value === '25' && segmentStartedAt === null) {
            segmentStartedAt = Date.now();
        }

        startBtn.style.display = 'none';
        pauseBtn.style.display = 'inline-block';
        timerInterval = setInterval(tick, 1000);
        updateDisplay();
        saveState();
    }

    startBtn.addEventListener('click', function () {
        if (workSessionComplete) return;
        startTimer(false);
    });

    pauseBtn.addEventListener('click', function () {
        clearInterval(timerInterval);
        closeWorkInterval(Date.now());
        isRunning = false;
        endTimestamp = null;
        startBtn.style.display = 'inline-block';
        pauseBtn.style.display = 'none';
        saveState();
    });

    resetBtn.addEventListener('click', function () {
        clearInterval(timerInterval);
        isRunning = false;
        endTimestamp = null;
        closeWorkInterval(Date.now());
        remainingSeconds = parseInt(modeSelect.value, 10) * 60;
        if (workIntervals.length) {
            workSessionComplete = true;
            logIntervals.value = JSON.stringify(workIntervals);
            logCard.style.display = 'block';
        }
        startBtn.style.display = 'inline-block';
        pauseBtn.style.display = 'none';
        updateDisplay();
        saveState();
    });

    modeSelect.addEventListener('change', function () {
        clearInterval(timerInterval);
        isRunning = false;
        endTimestamp = null;
        closeWorkInterval(Date.now());
        workSessionComplete = workIntervals.length > 0;
        if (workSessionComplete) {
            logIntervals.value = JSON.stringify(workIntervals);
            logCard.style.display = 'block';
        } else {
            logCard.style.display = 'none';
        }
        remainingSeconds = parseInt(modeSelect.value, 10) * 60;
        startBtn.style.display = 'inline-block';
        pauseBtn.style.display = 'none';
        updateDisplay();
        saveState();
    });

    const logForm = logCard.querySelector('form');
    if (logForm) {
        logForm.addEventListener('submit', function () {
            logIntervals.value = JSON.stringify(workIntervals);
            localStorage.removeItem(STORAGE_KEY);
        });
    }

    loadState();
    updateDisplay();
})();
</script>

<?php require __DIR__ . '/views/footer.php'; ?>