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
    /** Admin de plataforma: ve y opera todas las funcionalidades. */
    case ADMIN = 'admin';

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
            self::ADMIN => 'Administrador',
        };
    }

    public function isPlatformAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function bypassesFamilyTenant(): bool
    {
        return in_array($this, [self::SOPORTE, self::DOCENTE, self::ADMIN_ESCUELA, self::ADMIN], true);
    }

    public function canBroadcastSchoolTasks(): bool
    {
        return in_array($this, [self::DOCENTE, self::ADMIN_ESCUELA, self::ADMIN], true);
    }

    public function canManageSchool(): bool
    {
        return in_array($this, [self::ADMIN_ESCUELA, self::ADMIN], true);
    }

    public function canManageTasks(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE, self::TUTOR, self::ADMIN], true);
    }

    public function canManageFinances(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE, self::ADMIN], true);
    }

    public function canInviteMembers(): bool
    {
        return in_array($this, [self::PADRE, self::MADRE, self::ADMIN], true);
    }

    public function canManageSupportTickets(): bool
    {
        return in_array($this, [self::SOPORTE, self::ADMIN], true);
    }

    public function isSupport(): bool
    {
        return $this === self::SOPORTE || $this === self::ADMIN;
    }
}
