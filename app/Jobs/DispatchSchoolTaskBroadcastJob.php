<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ClassEnrollment;
use App\Models\SchoolTaskBroadcast;
use App\Models\Task;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class DispatchSchoolTaskBroadcastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $broadcastId) {}

    public function handle(FamilyNotificationService $notifications): void
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
            if ($student === null || $enrollment->family_id === null || $teacher === null) {
                continue;
            }

            $task = Task::query()->create([
                'id'                  => (string) Str::uuid(),
                'family_id'           => $enrollment->family_id,
                'source_broadcast_id' => $broadcast->id,
                'school_id'           => $broadcast->school_id,
                'created_by'          => $teacher->id,
                'created_by_role'     => $teacher->role,
                'assigned_to'         => $student->id,
                'title'               => $broadcast->title,
                'description'         => $broadcast->description,
                'priority'            => $broadcast->priority,
                'due_date'            => $broadcast->due_date,
                'is_school'           => true,
                'subject'             => $broadcast->subject,
                'status'              => 'pending',
            ]);

            $notifications->notifyFamilyById(
                $enrollment->family_id,
                (int) $teacher->id,
                'school_task_broadcast',
                'Tarea escolar',
                "{$teacher->name} envió «{$broadcast->title}» para {$student->name}",
                [
                    'entity_type' => 'task',
                    'entity_id'   => $task->id,
                    'broadcast'   => true,
                    'actor_id'    => $teacher->id,
                    'actor_name'  => $teacher->name,
                ],
            );

            // En cPanel (broadcast=log) no hay WS; FCM + soft refresh cubren sync.
            FamilyRealtime::taskChanged(
                familyId: (string) $enrollment->family_id,
                taskId: (string) $task->id,
                action: 'created',
                status: 'pending',
                title: $task->title,
                assigneeId: (int) $student->id,
                actorId: (int) $teacher->id,
            );

            $created++;
            if ($created % 25 === 0) {
                $broadcast->update(['tasks_created' => $created]);
            }
        }

        $broadcast->update([
            'status'        => 'completed',
            'tasks_created' => $created,
        ]);
    }
}
