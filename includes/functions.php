<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDuration(?int $minutes): string
{
    if (!$minutes) {
        return '0min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    if ($h > 0 && $m > 0) return "{$h}h{$m}";
    if ($h > 0) return "{$h}h";
    return "{$m}min";
}

function formatDurationSeconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;
    $parts = [];

    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}min";
    if ($remainingSeconds > 0 || $parts === []) $parts[] = "{$remainingSeconds}s";

    return implode(' ', $parts);
}

function formatDateFr(?string $date): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '';
}

function formationStatusLabel(string $status): string
{
    return match ($status) {
        'a_commencer' => 'À commencer',
        'en_cours'    => 'En cours',
        'en_pause'    => 'En pause',
        'terminee'    => 'Terminée',
        'abandonnee'  => 'Abandonnée',
        default       => $status,
    };
}

function formationStatusBadgeClass(string $status): string
{
    return match ($status) {
        'a_commencer' => 'bg-secondary',
        'en_cours'    => 'bg-primary',
        'en_pause'    => 'bg-warning text-dark',
        'terminee'    => 'bg-success',
        'abandonnee'  => 'bg-danger',
        default       => 'bg-secondary',
    };
}

function priorityLabel(string $priority): string
{
    return match ($priority) {
        'urgent'    => 'Urgent',
        'important' => 'Important',
        'normal'    => 'Normal',
        'faible'    => 'Faible',
        default     => $priority,
    };
}

function moduleStatusLabel(string $status): string
{
    return match ($status) {
        'a_faire'  => 'À faire',
        'en_cours' => 'En cours',
        'termine'  => 'Terminé',
        default    => $status,
    };
}

function goalTypeLabel(string $type): string
{
    return match ($type) {
        'quotidien'    => 'Quotidien',
        'hebdomadaire' => 'Hebdomadaire',
        'long_terme'   => 'Long terme',
        default        => $type,
    };
}

function goalStatusLabel(string $status): string
{
    return match ($status) {
        'a_faire'    => 'A faire',
        'en_cours'   => 'En cours',
        'terminee'   => 'Terminee',
        'abandonnee' => 'Abandonnee',
        default      => $status,
    };
}

function taskStatusLabel(string $status): string
{
    return match ($status) {
        'a_faire'  => 'A faire',
        'en_cours' => 'En cours',
        'terminee' => 'Terminee',
        'reportee' => 'Reportee',
        'annulee'  => 'Annulee',
        default    => $status,
    };
}

function taskStatusBadgeClass(string $status): string
{
    return match ($status) {
        'a_faire'  => 'bg-secondary',
        'en_cours' => 'bg-primary',
        'terminee' => 'bg-success',
        'reportee' => 'bg-warning text-dark',
        'annulee'  => 'bg-danger',
        default    => 'bg-secondary',
    };
}

function weekDatesFor(string $date): array
{
    $ts = strtotime($date);
    $dayOfWeek = (int) date('N', $ts);
    $monday = strtotime('-' . ($dayOfWeek - 1) . ' days', $ts);

    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = date('Y-m-d', strtotime("+{$i} days", $monday));
    }
    return $days;
}

function dayLabelFr(string $date): string
{
    $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $dayOfWeek = (int) date('N', strtotime($date));
    return $days[$dayOfWeek - 1];
}

function sessionTypeLabel(string $type): string
{
    return match ($type) {
        'cours'    => 'Cours',
        'pratique' => 'Pratique',
        'revision' => 'Revision',
        'lecture'  => 'Lecture',
        'projet'   => 'Projet',
        default    => $type,
    };
}

function forecastStatusLabel(string $status): string
{
    return match ($status) {
        'ok'       => 'Dans les temps',
        'at_risk'  => 'Risque de retard',
        'overdue'  => 'Echeance depassee',
        'stalled'  => 'Aucune activite recente',
        'complete' => 'Terminee',
        default    => $status,
    };
}

function forecastStatusBadgeClass(string $status): string
{
    return match ($status) {
        'ok'       => 'bg-success',
        'at_risk'  => 'bg-warning text-dark',
        'overdue'  => 'bg-danger',
        'stalled'  => 'bg-secondary',
        'complete' => 'bg-primary',
        default    => 'bg-secondary',
    };
}

function masteryLevelLabel(string $level): string
{
    return match ($level) {
        'debutant' => 'Debutant',
        'moyen'    => 'Moyen',
        'bon'      => 'Bon',
        'maitrise' => 'Maitrise',
        default    => $level,
    };
}

function masteryLevelBadgeClass(string $level): string
{
    return match ($level) {
        'debutant' => 'bg-danger',
        'moyen'    => 'bg-warning text-dark',
        'bon'      => 'bg-primary',
        'maitrise' => 'bg-success',
        default    => 'bg-secondary',
    };
}