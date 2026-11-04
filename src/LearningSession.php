<?php
declare(strict_types=1);

class LearningSession
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, f.name AS formation_name, c.name AS category_name,
                   t.title AS task_title
            FROM learning_sessions s
            LEFT JOIN formations f ON f.id = s.formation_id AND f.user_id = s.user_id
            LEFT JOIN categories c ON c.id = s.category_id AND c.user_id = s.user_id
            LEFT JOIN tasks t ON t.id = s.task_id AND t.user_id = s.user_id
            WHERE s.user_id = ? AND s.ended_at IS NOT NULL
            ORDER BY s.session_date DESC, s.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM learning_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO learning_sessions
                (user_id, formation_id, category_id, task_id, session_date, duration_min, duration_sec, started_at, ended_at, session_type, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $durationMin = max(0, (int) ($data['duration_min'] ?? 0));
        $durationSec = max(0, (int) ($data['duration_sec'] ?? ($durationMin * 60)));
        if ($durationSec <= 0) {
            throw new InvalidArgumentException('La duree de la session doit etre superieure a zero.');
        }
        foreach (['formation_id', 'category_id', 'task_id'] as $field) {
            if (!empty($data[$field])) {
                $this->assertOwnedReference($field, (int) $data[$field], $userId);
            }
        }
        $startedAt = isset($data['started_at']) ? (int) $data['started_at'] : null;
        $endedAt = isset($data['ended_at']) ? (int) $data['ended_at'] : time();
        $stmt->execute([
            $userId,
            !empty($data['formation_id']) ? (int) $data['formation_id'] : null,
            !empty($data['category_id']) ? (int) $data['category_id'] : null,
            !empty($data['task_id']) ? (int) $data['task_id'] : null,
            $data['session_date'],
            intdiv($durationSec, 60),
            $durationSec,
            $startedAt,
            $endedAt,
            $data['session_type'] ?? 'cours',
            !empty($data['notes']) ? $data['notes'] : null,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        if ($startedAt !== null && $endedAt !== null) {
            $id = $this->mergeOverlappingIntervals($id, $userId);
        }

        $this->recalculateFormationHoursForSession($id, $userId);
        return $id;
    }

    public function startTask(int $taskId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->lockUser($userId);

            $taskStmt = $this->pdo->prepare("
                SELECT formation_id, category_id, status FROM tasks WHERE id = ? AND user_id = ?
            ");
            $taskStmt->execute([$taskId, $userId]);
            $task = $taskStmt->fetch();
            if (!$task) {
                throw new RuntimeException('Tache introuvable.');
            }
            if (in_array($task['status'], ['terminee', 'annulee'], true)) {
                throw new RuntimeException('Une tache terminee ou annulee ne peut pas etre chronometree.');
            }

            $active = $this->pdo->prepare("
                SELECT task_id FROM learning_sessions
                WHERE user_id = ? AND started_at IS NOT NULL AND ended_at IS NULL
                LIMIT 1
            ");
            $active->execute([$userId]);
            $activeTaskId = $active->fetchColumn();
            if ($activeTaskId !== false) {
                if ((int) $activeTaskId === $taskId) {
                    $updateStatus = $this->pdo->prepare("
                        UPDATE tasks SET status = 'en_cours' WHERE id = ? AND user_id = ?
                    ");
                    $updateStatus->execute([$taskId, $userId]);
                    $this->pdo->commit();
                    return;
                }
                throw new RuntimeException('Mettez en pause le chronometre de l’autre tache avant de demarrer celui-ci.');
            }

            $now = time();
            $stmt = $this->pdo->prepare("
                INSERT INTO learning_sessions
                    (user_id, formation_id, category_id, task_id, session_date, duration_min, duration_sec, started_at, session_type)
                VALUES (?, ?, ?, ?, CURDATE(), 0, 0, ?, 'cours')
            ");
            $stmt->execute([
                $userId,
                $task['formation_id'],
                $task['category_id'],
                $taskId,
                $now,
            ]);
            $updateStatus = $this->pdo->prepare("
                UPDATE tasks SET status = 'en_cours' WHERE id = ? AND user_id = ?
            ");
            $updateStatus->execute([$taskId, $userId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function pauseTask(int $taskId, int $userId): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if ($ownsTransaction) {
                $this->lockUser($userId);
            }

            $stmt = $this->pdo->prepare("
                SELECT id, started_at FROM learning_sessions
                WHERE user_id = ? AND task_id = ? AND started_at IS NOT NULL AND ended_at IS NULL
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$userId, $taskId]);
            $active = $stmt->fetch();
            if ($active) {
                $now = time();
                $seconds = max(0, $now - (int) $active['started_at']);
                $update = $this->pdo->prepare("
                    UPDATE learning_sessions
                    SET ended_at = ?, duration_sec = ?, duration_min = ?
                    WHERE id = ? AND user_id = ? AND ended_at IS NULL
                ");
                $update->execute([$now, $seconds, intdiv($seconds, 60), $active['id'], $userId]);
                $mergedId = $this->mergeOverlappingIntervals((int) $active['id'], $userId);
                $this->recalculateFormationHoursForSession($mergedId, $userId);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function activeTaskIds(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT task_id FROM learning_sessions
            WHERE user_id = ? AND task_id IS NOT NULL
              AND started_at IS NOT NULL AND ended_at IS NULL
        ");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function secondsForTask(int $taskId, int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(duration_sec), 0)
            FROM learning_sessions
            WHERE user_id = ? AND task_id = ? AND ended_at IS NOT NULL
        ");
        $stmt->execute([$userId, $taskId]);
        return (int) $stmt->fetchColumn();
    }

    public function createIntervals(int $userId, array $data, array $intervals): void
    {
        if (count($intervals) > 100) {
            throw new InvalidArgumentException('La session contient trop d’intervalles.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->lockUser($userId);
            foreach ($intervals as $interval) {
                if (!is_array($interval)) {
                    throw new InvalidArgumentException('Les horaires de la session sont invalides.');
                }
                $start = filter_var($interval['start'] ?? null, FILTER_VALIDATE_INT);
                $end = filter_var($interval['end'] ?? null, FILTER_VALIDATE_INT);
                if ($start === false || $end === false || $end <= $start || $end > time() + 5) {
                    throw new InvalidArgumentException('Les horaires de la session sont invalides.');
                }
                if ($end - $start > 86400) {
                    throw new InvalidArgumentException('Un intervalle Pomodoro ne peut pas depasser 24 heures.');
                }

                $resolved = $this->resolveIntervalTask(
                    $userId,
                    $start,
                    $end,
                    !empty($data['task_id']) ? (int) $data['task_id'] : null
                );
                $durationSec = $end - $start;
                $this->create($userId, [
                    'formation_id' => $resolved['formation_id'],
                    'category_id' => $resolved['category_id'],
                    'task_id' => $resolved['task_id'],
                    'session_date' => date('Y-m-d', $start),
                    'duration_sec' => $durationSec,
                    'session_type' => $data['session_type'] ?? 'pratique',
                    'notes' => $data['notes'] ?? null,
                    'started_at' => $start,
                    'ended_at' => $end,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function completeTask(int $taskId, int $userId, int $additionalMinutes): void
    {
        if ($additionalMinutes < 0 || $additionalMinutes > 1440) {
            throw new InvalidArgumentException('Le temps supplementaire doit etre compris entre 0 et 1440 minutes.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->lockUser($userId);
            $taskStmt = $this->pdo->prepare("
                SELECT status, formation_id, category_id FROM tasks WHERE id = ? AND user_id = ? FOR UPDATE
            ");
            $taskStmt->execute([$taskId, $userId]);
            $task = $taskStmt->fetch();
            if (!$task) {
                throw new InvalidArgumentException('Tache introuvable.');
            }
            if ($task['status'] === 'terminee') {
                $this->pdo->commit();
                return;
            }
            if ($task['status'] === 'annulee') {
                throw new InvalidArgumentException('Une tache annulee ne peut pas etre terminee.');
            }

            $this->pauseTask($taskId, $userId);

            if ($additionalMinutes > 0) {
                $this->create($userId, [
                    'formation_id' => $task['formation_id'],
                    'category_id' => $task['category_id'],
                    'task_id' => $taskId,
                    'session_date' => date('Y-m-d'),
                    'duration_min' => $additionalMinutes,
                    'session_type' => 'pratique',
                    'notes' => null,
                ]);
            }

            if ($this->secondsForTask($taskId, $userId) <= 0) {
                throw new InvalidArgumentException('Demarrez le chronometre ou saisissez le temps reel etudie avant de terminer cette tache.');
            }

            $update = $this->pdo->prepare("
                UPDATE tasks SET status = 'terminee' WHERE id = ? AND user_id = ?
            ");
            $update->execute([$taskId, $userId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(int $id, int $userId): void
    {
        $session = $this->find($id, $userId);

        $stmt = $this->pdo->prepare("DELETE FROM learning_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);

        if ($session && $session['formation_id']) {
            $this->recalculateFormationHours((int) $session['formation_id'], $userId);
        }
    }

    private function assertOwnedReference(string $field, int $id, int $userId): void
    {
        $tables = [
            'formation_id' => 'formations',
            'category_id' => 'categories',
            'task_id' => 'tasks',
        ];
        if (!isset($tables[$field])) {
            throw new LogicException('Type de reference invalide.');
        }
        $stmt = $this->pdo->prepare("SELECT id FROM {$tables[$field]} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('La formation, categorie ou tache selectionnee est invalide.');
        }
    }

    private function recalculateFormationHours(int $formationId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(duration_sec), 0) AS total_sec
            FROM learning_sessions
            WHERE formation_id = ? AND user_id = ? AND session_date <= CURDATE()
        ");
        $stmt->execute([$formationId, $userId]);
        $sessionSeconds = (int) $stmt->fetchColumn();
        $hours = intdiv($sessionSeconds, 36) / 100;

        $update = $this->pdo->prepare("UPDATE formations SET actual_hours = ? WHERE id = ? AND user_id = ?");
        $update->execute([$hours, $formationId, $userId]);
    }

    private function lockUser(int $userId): void
    {
        $lock = $this->pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $lock->execute([$userId]);
        if (!$lock->fetchColumn()) {
            throw new RuntimeException('Utilisateur introuvable.');
        }
    }

    private function recalculateFormationHoursForSession(int $sessionId, int $userId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT formation_id FROM learning_sessions WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);
        $formationId = (int) $stmt->fetchColumn();
        if ($formationId > 0) {
            $this->recalculateFormationHours($formationId, $userId);
        }
    }

    private function resolveIntervalTask(int $userId, int $start, int $end, ?int $requestedTaskId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT task_id FROM learning_sessions
            WHERE user_id = ? AND task_id IS NOT NULL
              AND started_at < ? AND (ended_at IS NULL OR ended_at > ?)
        ");
        $stmt->execute([$userId, $end, $start]);
        $overlappingTaskIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($requestedTaskId !== null) {
            foreach ($overlappingTaskIds as $overlappingTaskId) {
                if ($overlappingTaskId !== $requestedTaskId) {
                    throw new InvalidArgumentException('Cet intervalle chevauche le chronometre d’une autre tache. Mettez-le en pause ou associez le Pomodoro a cette tache.');
                }
            }
        } elseif (count($overlappingTaskIds) === 1) {
            $requestedTaskId = $overlappingTaskIds[0];
        } elseif (count($overlappingTaskIds) > 1) {
            throw new InvalidArgumentException('Cet intervalle chevauche plusieurs taches suivies; corrigez les chronometres avant de l’enregistrer.');
        }

        if ($requestedTaskId === null) {
            return ['task_id' => null, 'formation_id' => null, 'category_id' => null];
        }

        $taskStmt = $this->pdo->prepare("
            SELECT formation_id, category_id FROM tasks WHERE id = ? AND user_id = ?
        ");
        $taskStmt->execute([$requestedTaskId, $userId]);
        $task = $taskStmt->fetch();
        if (!$task) {
            throw new InvalidArgumentException('La tache selectionnee est invalide.');
        }

        return [
            'task_id' => $requestedTaskId,
            'formation_id' => $task['formation_id'],
            'category_id' => $task['category_id'],
        ];
    }

    private function mergeOverlappingIntervals(int $sessionId, int $userId): int
    {
        $find = $this->pdo->prepare("
            SELECT * FROM learning_sessions
            WHERE id = ? AND user_id = ? AND started_at IS NOT NULL AND ended_at IS NOT NULL
        ");
        $find->execute([$sessionId, $userId]);
        $session = $find->fetch();
        if (!$session) {
            return $sessionId;
        }

        $taskCondition = $session['task_id'] === null
            ? 'task_id IS NULL'
            : '(task_id = ? OR task_id IS NULL)';
        $stmt = $this->pdo->prepare("
            SELECT * FROM learning_sessions
            WHERE user_id = ? AND {$taskCondition}
              AND started_at IS NOT NULL AND ended_at IS NOT NULL
            ORDER BY id
        ");
        $params = [$userId];
        if ($session['task_id'] !== null) {
            $params[] = $session['task_id'];
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $start = (int) $session['started_at'];
        $end = (int) $session['ended_at'];
        $mergedIds = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($rows as $row) {
                $rowId = (int) $row['id'];
                if (isset($mergedIds[$rowId])) {
                    continue;
                }
                $rowStart = (int) $row['started_at'];
                $rowEnd = (int) $row['ended_at'];
                if ($rowStart <= $end && $rowEnd >= $start) {
                    $mergedIds[$rowId] = true;
                    $start = min($start, $rowStart);
                    $end = max($end, $rowEnd);
                    $changed = true;
                }
            }
        }

        if (count($mergedIds) < 2 && date('Y-m-d', $start) === date('Y-m-d', $end - 1)) {
            return $sessionId;
        }

        $ids = array_keys($mergedIds);
        $keeperId = min($ids);
        $notes = [];
        foreach ($rows as $row) {
            if (isset($mergedIds[(int) $row['id']]) && trim((string) $row['notes']) !== '') {
                $notes[] = trim((string) $row['notes']);
            }
        }
        $mergedNotes = $notes ? implode("\n", array_unique($notes)) : null;
        $pieces = [];
        for ($cursor = $start; $cursor < $end;) {
            $nextDay = strtotime('tomorrow', $cursor);
            $pieceEnd = min($end, $nextDay);
            $pieces[] = [
                'date' => date('Y-m-d', $cursor),
                'start' => $cursor,
                'end' => $pieceEnd,
            ];
            $cursor = $pieceEnd;
        }
        $firstPiece = $pieces[0];
        $seconds = $firstPiece['end'] - $firstPiece['start'];
        $update = $this->pdo->prepare("
            UPDATE learning_sessions
            SET task_id = COALESCE(task_id, ?), formation_id = COALESCE(formation_id, ?),
                category_id = COALESCE(category_id, ?), notes = ?, session_date = ?, started_at = ?,
                ended_at = ?, duration_sec = ?, duration_min = ?
            WHERE id = ? AND user_id = ?
        ");
        $update->execute([
            $session['task_id'],
            $session['formation_id'],
            $session['category_id'],
            $mergedNotes,
            $firstPiece['date'],
            $firstPiece['start'],
            $firstPiece['end'],
            $seconds,
            intdiv($seconds, 60),
            $keeperId,
            $userId,
        ]);

        $deleteIds = array_values(array_filter($ids, fn($id) => $id !== $keeperId));
        if ($deleteIds !== []) {
            $delete = $this->pdo->prepare("
                DELETE FROM learning_sessions WHERE id IN (" . implode(',', array_fill(0, count($deleteIds), '?')) . ")
                  AND user_id = ?
            ");
            $delete->execute([...$deleteIds, $userId]);
        }

        if (count($pieces) > 1) {
            $insert = $this->pdo->prepare("
                INSERT INTO learning_sessions
                    (user_id, formation_id, category_id, task_id, session_date, duration_min,
                     duration_sec, started_at, ended_at, session_type, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach (array_slice($pieces, 1) as $piece) {
                $pieceSeconds = $piece['end'] - $piece['start'];
                $insert->execute([
                    $userId,
                    $session['formation_id'],
                    $session['category_id'],
                    $session['task_id'],
                    $piece['date'],
                    intdiv($pieceSeconds, 60),
                    $pieceSeconds,
                    $piece['start'],
                    $piece['end'],
                    $session['session_type'],
                    $mergedNotes,
                ]);
            }
        }

        return $keeperId;
    }

    public function totalMinutes(int $userId, string $sinceDate, string $untilDate): int
    {
        $stmt = $this->pdo->prepare("
            SELECT FLOOR(COALESCE(SUM(duration_sec), 0) / 60)
            FROM learning_sessions
            WHERE user_id = ? AND session_date BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $sinceDate, $untilDate]);
        return (int) $stmt->fetchColumn();
    }

    public function totalMinutesAllTime(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT FLOOR(COALESCE(SUM(duration_sec), 0) / 60)
            FROM learning_sessions
            WHERE user_id = ? AND session_date <= CURDATE()
        ");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function minutesForDate(int $userId, string $date): int
    {
        $stmt = $this->pdo->prepare("
            SELECT FLOOR(COALESCE(SUM(duration_sec), 0) / 60)
            FROM learning_sessions
            WHERE user_id = ? AND session_date = ? AND session_date <= CURDATE()
        ");
        $stmt->execute([$userId, $date]);
        return (int) $stmt->fetchColumn();
    }

    public function totalByFormation(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.id, f.name, FLOOR(COALESCE(SUM(s.duration_sec), 0) / 60) AS total_min
            FROM formations f
            LEFT JOIN learning_sessions s
                ON s.formation_id = f.id AND s.user_id = f.user_id AND s.session_date <= CURDATE()
            WHERE f.user_id = ?
            GROUP BY f.id, f.name
            HAVING total_min > 0
            ORDER BY total_min DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function totalByCategory(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.name, c.color, FLOOR(COALESCE(SUM(s.duration_sec), 0) / 60) AS total_min
            FROM categories c
            LEFT JOIN learning_sessions s
                ON s.category_id = c.id AND s.user_id = c.user_id AND s.session_date <= CURDATE()
            WHERE c.user_id = ?
            GROUP BY c.id, c.name, c.color
            HAVING total_min > 0
            ORDER BY total_min DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function currentStreak(int $userId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT session_date FROM learning_sessions
            WHERE user_id = ? AND session_date <= CURDATE()
              AND ended_at IS NOT NULL AND duration_sec > 0
            ORDER BY session_date DESC
        ");
        $stmt->execute([$userId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $expected = new DateTime('today');
        $mostRecent = new DateTime($dates[0]);

        $diffDays = (int) $expected->diff($mostRecent)->format('%a');
        if ($diffDays > 1) {
            return 0;
        }

        $cursor = $mostRecent;
        $dateSet = array_flip($dates);

        while (isset($dateSet[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor->modify('-1 day');
        }

        return $streak;
    }

}
