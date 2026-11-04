<?php
declare(strict_types=1);

class JournalEntry
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM journal_entries
            WHERE user_id = ?
            ORDER BY entry_date DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM journal_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByDate(int $userId, string $date): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? AND entry_date = ?");
        $stmt->execute([$userId, $date]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function save(int $userId, array $data): int
    {
        $existing = $this->findByDate($userId, $data['entry_date']);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE journal_entries SET
                    what_learned = ?, what_was_hard = ?, what_to_review = ?, next_step = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $data['what_learned'] ?: null,
                $data['what_was_hard'] ?: null,
                $data['what_to_review'] ?: null,
                $data['next_step'] ?: null,
                $existing['id'],
                $userId,
            ]);
            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO journal_entries
                (user_id, entry_date, what_learned, what_was_hard, what_to_review, next_step)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['entry_date'],
            $data['what_learned'] ?: null,
            $data['what_was_hard'] ?: null,
            $data['what_to_review'] ?: null,
            $data['next_step'] ?: null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM journal_entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
    }
}