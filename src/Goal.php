<?php
declare(strict_types=1);

class Goal
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId, ?string $typeFilter = null): array
    {
        $sql = "SELECT * FROM goals WHERE user_id = ?";
        $params = [$userId];

        if ($typeFilter) {
            $sql .= " AND type = ?";
            $params[] = $typeFilter;
        }

        $sql .= " ORDER BY FIELD(priority, 'urgent','important','normal','faible'), deadline ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM goals WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO goals
                (user_id, title, description, type, start_date, deadline, priority, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description'] ?: null,
            $data['type'],
            $data['start_date'] ?: null,
            $data['deadline'] ?: null,
            $data['priority'],
            $data['status'] ?? 'a_faire',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE goals SET
                title = ?, description = ?, type = ?, start_date = ?, deadline = ?,
                priority = ?, status = ?, progress_percent = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['type'],
            $data['start_date'] ?: null,
            $data['deadline'] ?: null,
            $data['priority'],
            $data['status'],
            $data['progress_percent'] ?: 0,
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM goals WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public function activeForUser(int $userId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM goals
            WHERE user_id = ? AND status IN ('a_faire', 'en_cours')
            ORDER BY FIELD(priority, 'urgent','important','normal','faible'), deadline ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}