<?php

namespace App\Domains\Family\Entities;

enum FamilyRole: string
{
    case PADRE  = 'padre';
    case MADRE  = 'madre';
    case TUTOR  = 'tutor';
    case HIJO   = 'hijo';

    public function label(): string
    {
        return match($this) {
            self::PADRE => 'Padre',
            self::MADRE => 'Madre',
            self::TUTOR => 'Tutor',
            self::HIJO  => 'Hijo/a',
        };
    }

    public function canManageTasks(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE, self::TUTOR]);
    }

    public function canManageFinances(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE]);
    }

    public function canInviteMembers(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE]);
    }
}
