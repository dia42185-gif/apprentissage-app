<?php
declare(strict_types=1);

class VacationPeriod
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM vacation_periods WHERE user_id = ? ORDER BY start_date DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vacation_periods WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function currentOrNext(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM vacation_periods
            WHERE user_id = ? AND end_date >= CURDATE()
            ORDER BY start_date ASC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO vacation_periods
                (user_id, start_date, end_date, hours_per_day, learning_days, goals_description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['start_date'],
            $data['end_date'],
            $data['hours_per_day'] ?: 2,
            $data['learning_days'] ?? '1,2,3,4,5,6,7',
            $data['goals_description'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE vacation_periods SET
                start_date = ?, end_date = ?, hours_per_day = ?, learning_days = ?, goals_description = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['start_date'],
            $data['end_date'],
            $data['hours_per_day'] ?: 2,
            $data['learning_days'] ?? '1,2,3,4,5,6,7',
            $data['goals_description'] ?: null,
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM vacation_periods WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public function remainingLearningDays(array $vacation): int
    {
        $today = new DateTime('today');
        $start = new DateTime(max($vacation['start_date'], $today->format('Y-m-d')));
        $end = new DateTime($vacation['end_date']);

        if ($start > $end) {
            return 0;
        }

        $allowedDays = array_map('intval', explode(',', $vacation['learning_days']));
        $count = 0;

        $cursor = clone $start;
        while ($cursor <= $end) {
            $dayOfWeek = (int) $cursor->format('N');
            if (in_array($dayOfWeek, $allowedDays, true)) {
                $count++;
            }
            $cursor->modify('+1 day');
        }

        return $count;
    }

    public function totalDays(array $vacation): int
    {
        $start = new DateTime($vacation['start_date']);
        $end = new DateTime($vacation['end_date']);
        return (int) $start->diff($end)->format('%a') + 1;
    }
}