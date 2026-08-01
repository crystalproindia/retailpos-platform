<?php

namespace App\Enums\Tasks;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::InProgress => 'In progress',
            self::Waiting => 'Waiting',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Todo => in_array($next, [self::InProgress, self::Waiting, self::Completed, self::Cancelled], true),
            self::InProgress => in_array($next, [self::Todo, self::Waiting, self::Completed, self::Cancelled], true),
            self::Waiting => in_array($next, [self::Todo, self::InProgress, self::Completed, self::Cancelled], true),
            self::Completed => $next === self::Todo,
            self::Cancelled => $next === self::Todo,
        };
    }
}
