<?php
declare(strict_types=1);

class FormationModule
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForFormation(int $formationId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM formation_modules
            WHERE formation_id = ?
            ORDER BY order_index ASC, id ASC
        ");
        $stmt->execute([$formationId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM formation_modules WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function nextOrderIndex(int $formationId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(order_index), 0) + 1 AS next_index
            FROM formation_modules WHERE formation_id = ?
        ");
        $stmt->execute([$formationId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $formationId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO formation_modules
                (formation_id, title, order_index, status, estimated_duration_min,
                 planned_date, difficulty, notes, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $formationId,
            $data['title'],
            $data['order_index'] ?? $this->nextOrderIndex($formationId),
            $data['status'] ?? 'a_faire',
            $data['estimated_duration_min'] ?: 0,
            $data['planned_date'] ?: null,
            $data['difficulty'] ?? 'moyen',
            $data['notes'] ?: null,
            $data['url'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE formation_modules SET
                title = ?, status = ?, estimated_duration_min = ?, actual_duration_min = ?,
                planned_date = ?, completion_date = ?, difficulty = ?, notes = ?, url = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['status'],
            $data['estimated_duration_min'] ?: 0,
            $data['actual_duration_min'] ?: 0,
            $data['planned_date'] ?: null,
            $data['completion_date'] ?: null,
            $data['difficulty'],
            $data['notes'] ?: null,
            $data['url'] ?: null,
            $id,
        ]);
    }

    public function toggleStatus(int $id, string $newStatus): void
    {
        $completionDate = $newStatus === 'termine' ? date('Y-m-d') : null;

        $stmt = $this->pdo->prepare("
            UPDATE formation_modules SET status = ?, completion_date = ? WHERE id = ?
        ");
        $stmt->execute([$newStatus, $completionDate, $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM formation_modules WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function belongsToUser(int $moduleId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT f.user_id FROM formation_modules m
            JOIN formations f ON f.id = m.formation_id
            WHERE m.id = ?
        ");
        $stmt->execute([$moduleId]);
        $ownerId = $stmt->fetchColumn();
        return $ownerId !== false && (int) $ownerId === $userId;
    }
}