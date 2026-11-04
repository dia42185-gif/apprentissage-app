<?php
declare(strict_types=1);

class Forecast
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function forFormation(array $formation, int $userId): array
    {
        $estimatedHours = (float) $formation['estimated_hours'];
        $actualStmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(duration_sec), 0)
            FROM learning_sessions
            WHERE formation_id = ? AND user_id = ? AND session_date <= CURDATE()
        ");
        $actualStmt->execute([$formation['id'], $userId]);
        $actualHours = (int) $actualStmt->fetchColumn() / 3600;
        $remainingHours = max(0, $estimatedHours - $actualHours);

        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(duration_sec), 0) AS total_sec
            FROM learning_sessions
            WHERE formation_id = ? AND user_id = ?
              AND session_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND CURDATE()
        ");
        $stmt->execute([$formation['id'], $userId]);
        $recentSeconds = (int) $stmt->fetchColumn();
        $avgHoursPerDay = round(($recentSeconds / 3600) / 14, 2);

        $daysNeeded = null;
        if ($avgHoursPerDay > 0) {
            $daysNeeded = (int) ceil($remainingHours / $avgHoursPerDay);
        }

        $daysUntilDeadline = null;
        if (!empty($formation['planned_end_date'])) {
            $today = new DateTime('today');
            $deadline = new DateTime($formation['planned_end_date']);
            $daysUntilDeadline = (int) $today->diff($deadline)->format('%a');
            if ($deadline < $today) {
                $daysUntilDeadline = -$daysUntilDeadline;
            }
        }

        $status = 'ok';
        if ($remainingHours <= 0) {
            $status = 'complete';
        } elseif ($avgHoursPerDay == 0) {
            $status = 'stalled';
        } elseif ($daysUntilDeadline !== null && $daysNeeded !== null) {
            if ($daysUntilDeadline < 0) {
                $status = 'overdue';
            } elseif ($daysNeeded > $daysUntilDeadline) {
                $status = 'at_risk';
            }
        }

        return [
            'formation_id'         => (int) $formation['id'],
            'formation_name'       => $formation['name'],
            'remaining_hours'      => $remainingHours,
            'avg_hours_per_day'    => $avgHoursPerDay,
            'days_needed'          => $daysNeeded,
            'days_until_deadline'  => $daysUntilDeadline,
            'status'               => $status,
        ];
    }

    public function forAllActiveFormations(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM formations
            WHERE user_id = ? AND status = 'en_cours'
            ORDER BY FIELD(priority, 'urgent','important','normal','faible')
        ");
        $stmt->execute([$userId]);
        $formations = $stmt->fetchAll();

        $forecasts = [];
        foreach ($formations as $f) {
            $forecasts[] = $this->forFormation($f, $userId);
        }
        return $forecasts;
    }

    public function forVacation(array $vacation, int $userId, VacationPeriod $vacationModel): array
    {
        $remainingDays = $vacationModel->remainingLearningDays($vacation);
        $hoursAvailable = round($remainingDays * (float) $vacation['hours_per_day'], 1);

        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(GREATEST(
                f.estimated_hours - COALESCE((
                    SELECT SUM(s.duration_sec) / 3600
                    FROM learning_sessions s
                    WHERE s.formation_id = f.id
                      AND s.user_id = f.user_id
                      AND s.session_date <= CURDATE()
                ), 0),
                0
            )), 0) AS total_remaining
            FROM formations f
            WHERE f.user_id = ? AND f.status = 'en_cours'
        ");
        $stmt->execute([$userId]);
        $hoursNeeded = round((float) $stmt->fetchColumn(), 1);

        $balance = round($hoursAvailable - $hoursNeeded, 1);

        return [
            'remaining_days'  => $remainingDays,
            'hours_available' => $hoursAvailable,
            'hours_needed'    => $hoursNeeded,
            'balance'         => $balance,
            'status'          => $balance >= 0 ? 'ok' : 'at_risk',
        ];
    }

    public function tooManyActiveFormations(int $userId, int $threshold = 3): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, name, priority FROM formations
            WHERE user_id = ? AND status = 'en_cours'
            ORDER BY FIELD(priority, 'urgent','important','normal','faible')
        ");
        $stmt->execute([$userId]);
        $active = $stmt->fetchAll();

        if (count($active) <= $threshold) {
            return null;
        }

        return [
            'count'      => count($active),
            'threshold'  => $threshold,
            'priority_names' => array_map(fn($f) => $f['name'], array_slice($active, 0, 2)),
        ];
    }
}