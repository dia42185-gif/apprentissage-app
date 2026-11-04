<?php
declare(strict_types=1);

class Formation
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId, ?string $statusFilter = null): array
    {
        $sql = "
            SELECT f.*, c.name AS category_name, c.color AS category_color,
                   (SELECT COUNT(*) FROM formation_modules m WHERE m.formation_id = f.id) AS modules_count
            FROM formations f
            LEFT JOIN categories c ON c.id = f.category_id
            WHERE f.user_id = ?
        ";
        $params = [$userId];

        if ($statusFilter) {
            $sql .= " AND f.status = ?";
            $params[] = $statusFilter;
        }

        $sql .= " ORDER BY FIELD(f.priority, 'urgent','important','normal','faible'), f.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.name AS category_name
            FROM formations f
            LEFT JOIN categories c ON c.id = f.category_id
            WHERE f.id = ? AND f.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO formations
                (user_id, category_id, name, description, platform, url, level,
                 status, priority, registration_date, start_date, planned_end_date,
                 estimated_hours, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['category_id'] ?: null,
            $data['name'],
            $data['description'] ?: null,
            $data['platform'] ?: null,
            $data['url'] ?: null,
            $data['level'] ?: null,
            $data['status'],
            $data['priority'],
            $data['registration_date'] ?: null,
            $data['start_date'] ?: null,
            $data['planned_end_date'] ?: null,
            $data['estimated_hours'] ?: 0,
            $data['notes'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE formations SET
                category_id = ?, name = ?, description = ?, platform = ?, url = ?,
                level = ?, status = ?, priority = ?, registration_date = ?,
                start_date = ?, planned_end_date = ?, estimated_hours = ?, notes = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['category_id'] ?: null,
            $data['name'],
            $data['description'] ?: null,
            $data['platform'] ?: null,
            $data['url'] ?: null,
            $data['level'] ?: null,
            $data['status'],
            $data['priority'],
            $data['registration_date'] ?: null,
            $data['start_date'] ?: null,
            $data['planned_end_date'] ?: null,
            $data['estimated_hours'] ?: 0,
            $data['notes'] ?: null,
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM formations WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public function recalculateProgress(int $formationId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'termine' THEN 1 ELSE 0 END) AS done,
                (
                    SELECT COALESCE(SUM(s.duration_sec), 0)
                    FROM learning_sessions s
                    INNER JOIN formations f ON f.id = s.formation_id
                    WHERE f.id = ? AND s.user_id = f.user_id AND s.session_date <= CURDATE()
                ) AS session_seconds
            FROM formation_modules
            WHERE formation_id = ?
        ");
        $stmt->execute([$formationId, $formationId]);
        $row = $stmt->fetch();

        $total = (int) $row['total'];
        $done  = (int) $row['done'];
        $progress = $total > 0 ? round(($done / $total) * 100, 2) : 0;
        $actualHours = intdiv((int) $row['session_seconds'], 36) / 100;

        $update = $this->pdo->prepare("
            UPDATE formations SET progress_percent = ?, actual_hours = ? WHERE id = ?
        ");
        $update->execute([$progress, $actualHours, $formationId]);
    }

    public function inProgressForUser(int $userId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM formations
            WHERE user_id = ? AND status = 'en_cours'
            ORDER BY FIELD(priority, 'urgent','important','normal','faible')
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}