<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Task;

use App\Application\Task\Commands\CreateTaskCommand;
use PHPUnit\Framework\TestCase;

class CreateTaskCommandTest extends TestCase
{
    private function makeCommand(array $overrides = []): CreateTaskCommand
    {
        $data = array_merge([
            'familyId'    => 'family-uuid-123',
            'title'       => 'Tarea de Matemáticas',
            'description' => 'Ejercicios del capítulo 5',
            'createdBy'   => 'user-uuid-456',
            'assignedTo'  => 'user-uuid-789',
            'priority'    => 'alta',
            'dueDate'     => '2026-07-01',
            'isSchool'    => true,
            'subject'     => 'Matemáticas',
        ], $overrides);

        return new CreateTaskCommand(
            familyId:    $data['familyId'],
            title:       $data['title'],
            description: $data['description'],
            createdBy:   $data['createdBy'],
            assignedTo:  $data['assignedTo'],
            priority:    $data['priority'],
            dueDate:     $data['dueDate'],
            isSchool:    $data['isSchool'],
            subject:     $data['subject'],
        );
    }

    // ── Construction ──────────────────────────────────────
    public function test_command_stores_family_id(): void
    {
        $cmd = $this->makeCommand(['familyId' => 'abc-123']);
        $this->assertSame('abc-123', $cmd->familyId);
    }

    public function test_command_stores_title(): void
    {
        $cmd = $this->makeCommand(['title' => 'Redacción de español']);
        $this->assertSame('Redacción de español', $cmd->title);
    }

    public function test_command_stores_description(): void
    {
        $cmd = $this->makeCommand(['description' => 'Escribir 500 palabras']);
        $this->assertSame('Escribir 500 palabras', $cmd->description);
    }

    public function test_command_allows_null_description(): void
    {
        $cmd = $this->makeCommand(['description' => null]);
        $this->assertNull($cmd->description);
    }

    public function test_command_stores_created_by(): void
    {
        $cmd = $this->makeCommand(['createdBy' => 'padre-uuid']);
        $this->assertSame('padre-uuid', $cmd->createdBy);
    }

    public function test_command_allows_null_assigned_to(): void
    {
        $cmd = $this->makeCommand(['assignedTo' => null]);
        $this->assertNull($cmd->assignedTo);
    }

    public function test_command_stores_priority(): void
    {
        foreach (['baja', 'media', 'alta', 'urgente'] as $priority) {
            $cmd = $this->makeCommand(['priority' => $priority]);
            $this->assertSame($priority, $cmd->priority);
        }
    }

    public function test_command_stores_due_date(): void
    {
        $cmd = $this->makeCommand(['dueDate' => '2026-08-15']);
        $this->assertSame('2026-08-15', $cmd->dueDate);
    }

    public function test_command_allows_null_due_date(): void
    {
        $cmd = $this->makeCommand(['dueDate' => null]);
        $this->assertNull($cmd->dueDate);
    }

    public function test_school_task_has_is_school_true(): void
    {
        $cmd = $this->makeCommand(['isSchool' => true, 'subject' => 'Física']);
        $this->assertTrue($cmd->isSchool);
        $this->assertSame('Física', $cmd->subject);
    }

    public function test_non_school_task_defaults(): void
    {
        $cmd = new CreateTaskCommand(
            familyId:    'fam-1',
            title:       'Comprar víveres',
            description: null,
            createdBy:   'user-1',
            assignedTo:  null,
            priority:    'media',
            dueDate:     null,
        );
        $this->assertFalse($cmd->isSchool);
        $this->assertNull($cmd->subject);
    }

    // ── Readonly ──────────────────────────────────────────
    public function test_command_is_readonly(): void
    {
        $cmd = $this->makeCommand();
        $reflection = new \ReflectionClass($cmd);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_command_properties_are_readonly(): void
    {
        $cmd = $this->makeCommand();
        $reflection = new \ReflectionClass($cmd);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(),
                "Property {$property->getName()} should be readonly");
        }
    }
}
