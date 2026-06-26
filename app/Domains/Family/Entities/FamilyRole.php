<?php

namespace App\Domains\Family\Entities;

enum FamilyRole: string
{
    case PADRE   = 'padre';
    case MADRE   = 'madre';
    case TUTOR   = 'tutor';
    case HIJO    = 'hijo';
    case SOPORTE = 'soporte';
    case DOCENTE = 'docente';
    case ADMIN_ESCUELA = 'admin_escuela';

    public function label(): string
    {
        return match ($this) {
            self::PADRE   => 'Padre',
            self::MADRE   => 'Madre',
            self::TUTOR   => 'Tutor',
            self::HIJO    => 'Hijo/a',
            self::SOPORTE => 'Soporte',
            self::DOCENTE => 'Docente',
            self::ADMIN_ESCUELA => 'Admin escuela',
        };
    }

    public function bypassesFamilyTenant(): bool
    {
        return in_array($this, [self::SOPORTE, self::DOCENTE, self::ADMIN_ESCUELA], true);
    }

    public function canBroadcastSchoolTasks(): bool
    {
        return in_array($this, [self::DOCENTE, self::ADMIN_ESCUELA], true);
    }

    public function canManageSchool(): bool
    {
        return $this === self::ADMIN_ESCUELA;
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

    public function canManageSupportTickets(): bool
    {
        return $this === self::SOPORTE;
    }

    public function isSupport(): bool
    {
        return $this === self::SOPORTE;
    }
}
