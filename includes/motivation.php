<?php
declare(strict_types=1);

function getMotivationalMessage(
    int $minutesToday,
    int $plannedMinutesToday,
    int $overdueCount,
    int $tasksDoneToday,
    int $tasksTotalToday
): array {
    if ($overdueCount > 0) {
        return [
            'type' => 'warning',
            'text' => "{$overdueCount} tache(s) en retard.",
        ];
    }

    if ($plannedMinutesToday > 0 && $minutesToday >= $plannedMinutesToday) {
        return [
            'type' => 'success',
            'text' => 'Temps prévu atteint : ' . formatDuration($minutesToday) . '.',
        ];
    }

    if ($tasksTotalToday > 0 && $tasksDoneToday === $tasksTotalToday) {
        return [
            'type' => 'success',
            'text' => "Taches terminees : {$tasksDoneToday}/{$tasksTotalToday}.",
        ];
    }

    if ($minutesToday > 0) {
        return [
            'type' => 'progress',
            'text' => 'Temps enregistré aujourd\'hui : ' . formatDuration($minutesToday) . '.',
        ];
    }

    return [
        'type' => 'start',
        'text' => 'Aucune session enregistrée aujourd\'hui.',
    ];
}

function motivationBadgeClass(string $type): string
{
    return match ($type) {
        'success'  => 'alert-success',
        'progress' => 'alert-primary',
        'warning'  => 'alert-warning',
        'start'    => 'alert-light border',
        default    => 'alert-light border',
    };
}