<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\ClassEnrollment;
use App\Models\SchoolTaskBroadcast;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class DispatchSchoolTaskBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $broadcastId) {}

    public function handle(): void
    {
        $broadcast = SchoolTaskBroadcast::query()
            ->with(['school', 'schoolClass', 'creator'])
            ->find($this->broadcastId);

        if ($broadcast === null) {
            return;
        }

        $broadcast->update(['status' => 'processing']);

        $query = ClassEnrollment::query()
            ->with(['student', 'family'])
            ->where('status', 'active')
            ->whereHas('schoolClass', fn ($builder) => $builder->where('school_id', $broadcast->school_id));

        if ($broadcast->school_class_id !== null) {
            $query->where('school_class_id', $broadcast->school_class_id);
        }

        $enrollments = $query->get();
        $broadcast->update(['tasks_total' => $enrollments->count()]);

        $created = 0;
        $teacher = $broadcast->creator;

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if ($student === null || $enrollment->family_id === null) {
                continue;
            }

            $task = Task::query()->create([
                'id'                 => (string) Str::uuid(),
                'family_id'          => $enrollment->family_id,
                'source_broadcast_id'=> $broadcast->id,
                'school_id'          => $broadcast->school_id,
                'created_by'         => $teacher->id,
                'created_by_role'    => $teacher->role,
                'assigned_to'        => $student->id,
                'title'              => $broadcast->title,
                'description'        => $broadcast->description,
                'priority'           => $broadcast->priority,
                'due_date'           => $broadcast->due_date,
                'is_school'          => true,
                'subject'            => $broadcast->subject,
                'status'             => 'pending',
            ]);

            $this->notifyFamily($enrollment->family_id, $student, $teacher, $task->id, $broadcast->title);
            $created++;
        }

        $broadcast->update([
            'status'        => 'completed',
            'tasks_created' => $created,
        ]);
    }

    private function notifyFamily(
        string $familyId,
        User $student,
        User $teacher,
        string $taskId,
        string $title,
    ): void {
        $recipientIds = User::query()
            ->where('family_id', $familyId)
            ->whereIn('role', ['padre', 'madre', 'tutor', 'hijo'])
            ->pluck('id')
            ->all();

        foreach ($recipientIds as $userId) {
            AppNotification::query()->create([
                'id'        => (string) Str::uuid(),
                'family_id' => $familyId,
                'user_id'   => $userId,
                'type'      => 'school_task_broadcast',
                'title'     => 'Tarea escolar',
                'body'      => "{$teacher->name} envió «{$title}» para {$student->name}",
                'data'      => [
                    'entity_type' => 'task',
                    'entity_id'   => $taskId,
                    'broadcast'   => true,
                ],
            ]);
        }
    }
}
