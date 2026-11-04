<?php
declare(strict_types=1);

class Task
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    
    public function forDay(int $userId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name AS category_name, c.color AS category_color,
                   f.name AS formation_name, g.title AS goal_title
            FROM tasks t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN formations f ON f.id = t.formation_id
            LEFT JOIN goals g ON g.id = t.goal_id
            WHERE t.user_id = ? AND t.task_date = ?
            ORDER BY t.start_time IS NULL, t.start_time ASC
        ");
        $stmt->execute([$userId, $date]);
        return $stmt->fetchAll();
    }

    public function forRange(int $userId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.name AS category_name, c.color AS category_color,
                   f.name AS formation_name, g.title AS goal_title
            FROM tasks t
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN formations f ON f.id = t.formation_id
            LEFT JOIN goals g ON g.id = t.goal_id
            WHERE t.user_id = ? AND t.task_date BETWEEN ? AND ?
            ORDER BY t.task_date ASC, t.start_time IS NULL, t.start_time ASC
        ");
        $stmt->execute([$userId, $startDate, $endDate]);
        $rows = $stmt->fetchAll();

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['task_date']][] = $row;
        }
        return $byDate;
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $duration = $this->computeDuration($data['start_time'] ?? null, $data['end_time'] ?? null);

        $stmt = $this->pdo->prepare("
            INSERT INTO tasks
                (user_id, title, description, task_date, start_time, end_time, duration_min,
                 category_id, formation_id, goal_id, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description'] ?: null,
            $data['task_date'],
            $data['start_time'] ?: null,
            $data['end_time'] ?: null,
            $duration,
            $data['category_id'] ?: null,
            $data['formation_id'] ?: null,
            $data['goal_id'] ?: null,
            $data['priority'] ?? 'normal',
            $data['status'] ?? 'a_faire',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $duration = $this->computeDuration($data['start_time'] ?? null, $data['end_time'] ?? null);

        $stmt = $this->pdo->prepare("
            UPDATE tasks SET
                title = ?, description = ?, task_date = ?, start_time = ?, end_time = ?,
                duration_min = ?, category_id = ?, formation_id = ?, goal_id = ?,
                priority = ?, status = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['task_date'],
            $data['start_time'] ?: null,
            $data['end_time'] ?: null,
            $duration,
            $data['category_id'] ?: null,
            $data['formation_id'] ?: null,
            $data['goal_id'] ?: null,
            $data['priority'],
            $data['status'],
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public function updateStatus(int $id, int $userId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
    }

    public function overdueForUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, f.name AS formation_name
            FROM tasks t
            LEFT JOIN formations f ON f.id = t.formation_id
            WHERE t.user_id = ? AND t.task_date < CURDATE()
              AND t.status NOT IN ('terminee', 'annulee')
            ORDER BY t.task_date ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function computeDuration(?string $start, ?string $end): ?int
    {
        if (!$start || !$end) {
            return null;
        }
        $startTs = strtotime($start);
        $endTs = strtotime($end);

        if ($startTs === false || $endTs === false || $endTs <= $startTs) {
            return null;
        }

        return (int) round(($endTs - $startTs) / 60);
    }

}