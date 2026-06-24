<?php

namespace App\Application\Task\Commands;

final readonly class CreateTaskCommand
{
    public function __construct(
        public string  $familyId,
        public string  $title,
        public ?string $description,
        public string  $createdBy,
        public ?string $assignedTo,
        public string  $priority,
        public ?string $dueDate,
        public bool    $isSchool = false,
        public ?string $subject  = null,
    ) {}
}
