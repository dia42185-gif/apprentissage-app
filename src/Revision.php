<?php
declare(strict_types=1);

class Revision
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, f.name AS formation_name, c.name AS category_name
            FROM revisions r
            LEFT JOIN formations f ON f.id = r.formation_id
            LEFT JOIN categories c ON c.id = r.category_id
            WHERE r.user_id = ?
            ORDER BY r.next_review_date IS NULL, r.next_review_date ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }


    public function dueForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, f.name AS formation_name, c.name AS category_name
            FROM revisions r
            LEFT JOIN formations f ON f.id = r.formation_id
            LEFT JOIN categories c ON c.id = r.category_id
            WHERE r.user_id = ? AND r.next_review_date <= CURDATE()
            ORDER BY r.next_review_date ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM revisions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $nextReview = $this->computeNextReviewDate($data['mastery_level'] ?? 'debutant', null);

        $stmt = $this->pdo->prepare("
            INSERT INTO revisions
                (user_id, formation_id, category_id, notion, learned_date, mastery_level,
                 next_review_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['formation_id'] ?: null,
            $data['category_id'] ?: null,
            $data['notion'],
            $data['learned_date'] ?: date('Y-m-d'),
            $data['mastery_level'] ?? 'debutant',
            $nextReview,
            $data['notes'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE revisions SET
                formation_id = ?, category_id = ?, notion = ?, learned_date = ?,
                mastery_level = ?, next_review_date = ?, notes = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['formation_id'] ?: null,
            $data['category_id'] ?: null,
            $data['notion'],
            $data['learned_date'] ?: null,
            $data['mastery_level'],
            $data['next_review_date'] ?: null,
            $data['notes'] ?: null,
            $id,
            $userId,
        ]);
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM revisions WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }

    public function markReviewed(int $id, int $userId, string $newMasteryLevel): void
    {
        $nextReview = $this->computeNextReviewDate($newMasteryLevel, date('Y-m-d'));

        $stmt = $this->pdo->prepare("
            UPDATE revisions SET
                mastery_level = ?, last_review_date = ?, next_review_date = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$newMasteryLevel, date('Y-m-d'), $nextReview, $id, $userId]);
    }

    
    private function computeNextReviewDate(string $masteryLevel, ?string $fromDate): string
    {
        $intervals = [
            'debutant' => 1,
            'moyen'    => 3,
            'bon'      => 7,
            'maitrise' => 21,
        ];

        $days = $intervals[$masteryLevel] ?? 1;
        $base = $fromDate ?? date('Y-m-d');

        return date('Y-m-d', strtotime($base . " +{$days} days"));
    }
}