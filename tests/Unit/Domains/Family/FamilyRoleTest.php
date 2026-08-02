<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Family;

use App\Domains\Family\Entities\FamilyRole;
use PHPUnit\Framework\TestCase;

class FamilyRoleTest extends TestCase
{
    // ── Label tests ───────────────────────────────────────
    public function test_padre_has_correct_label(): void
    {
        $this->assertSame('Padre', FamilyRole::PADRE->label());
    }

    public function test_madre_has_correct_label(): void
    {
        $this->assertSame('Madre', FamilyRole::MADRE->label());
    }

    public function test_tutor_has_correct_label(): void
    {
        $this->assertSame('Tutor', FamilyRole::TUTOR->label());
    }

    public function test_hijo_has_correct_label(): void
    {
        $this->assertSame('Hijo/a', FamilyRole::HIJO->label());
    }

    public function test_soporte_has_correct_label(): void
    {
        $this->assertSame('Soporte', FamilyRole::SOPORTE->label());
    }

    // ── canManageTasks ────────────────────────────────────
    public function test_padre_can_manage_tasks(): void
    {
        $this->assertTrue(FamilyRole::PADRE->canManageTasks());
    }

    public function test_madre_can_manage_tasks(): void
    {
        $this->assertTrue(FamilyRole::MADRE->canManageTasks());
    }

    public function test_tutor_can_manage_tasks(): void
    {
        $this->assertTrue(FamilyRole::TUTOR->canManageTasks());
    }

    public function test_hijo_cannot_manage_tasks(): void
    {
        $this->assertFalse(FamilyRole::HIJO->canManageTasks());
    }

    public function test_soporte_cannot_manage_tasks(): void
    {
        $this->assertFalse(FamilyRole::SOPORTE->canManageTasks());
    }

    public function test_soporte_can_manage_support_tickets(): void
    {
        $this->assertTrue(FamilyRole::SOPORTE->canManageSupportTickets());
    }

    // ── canManageFinances ─────────────────────────────────
    public function test_padre_can_manage_finances(): void
    {
        $this->assertTrue(FamilyRole::PADRE->canManageFinances());
    }

    public function test_madre_can_manage_finances(): void
    {
        $this->assertTrue(FamilyRole::MADRE->canManageFinances());
    }

    public function test_tutor_cannot_manage_finances(): void
    {
        $this->assertFalse(FamilyRole::TUTOR->canManageFinances());
    }

    public function test_hijo_cannot_manage_finances(): void
    {
        $this->assertFalse(FamilyRole::HIJO->canManageFinances());
    }

    // ── canInviteMembers ──────────────────────────────────
    public function test_padre_can_invite_members(): void
    {
        $this->assertTrue(FamilyRole::PADRE->canInviteMembers());
    }

    public function test_madre_can_invite_members(): void
    {
        $this->assertTrue(FamilyRole::MADRE->canInviteMembers());
    }

    public function test_tutor_cannot_invite_members(): void
    {
        $this->assertFalse(FamilyRole::TUTOR->canInviteMembers());
    }

    public function test_hijo_cannot_invite_members(): void
    {
        $this->assertFalse(FamilyRole::HIJO->canInviteMembers());
    }

    // ── Enum values ───────────────────────────────────────
    public function test_enum_values_are_correct_strings(): void
    {
        $this->assertSame('padre',  FamilyRole::PADRE->value);
        $this->assertSame('madre',  FamilyRole::MADRE->value);
        $this->assertSame('tutor',  FamilyRole::TUTOR->value);
        $this->assertSame('hijo',   FamilyRole::HIJO->value);
        $this->assertSame('soporte', FamilyRole::SOPORTE->value);
    }

    public function test_can_create_from_string_value(): void
    {
        $this->assertSame(FamilyRole::PADRE, FamilyRole::from('padre'));
        $this->assertSame(FamilyRole::MADRE, FamilyRole::from('madre'));
        $this->assertSame(FamilyRole::TUTOR, FamilyRole::from('tutor'));
        $this->assertSame(FamilyRole::HIJO,  FamilyRole::from('hijo'));
        $this->assertSame(FamilyRole::SOPORTE, FamilyRole::from('soporte'));
        $this->assertSame(FamilyRole::DOCENTE, FamilyRole::from('docente'));
        $this->assertSame(FamilyRole::ADMIN_ESCUELA, FamilyRole::from('admin_escuela'));
    }

    public function test_invalid_value_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        FamilyRole::from('admin');
    }

    public function test_all_roles_count_is_seven(): void
    {
        $this->assertCount(7, FamilyRole::cases());
    }
}
