<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Jobs\DispatchSchoolTaskBroadcastJob;
use App\Models\SchoolClass;
use App\Models\SchoolTaskBroadcast;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @phpstan-type CreateBroadcastSuccess array{ok: true, broadcast: SchoolTaskBroadcast}
 * @phpstan-type CreateBroadcastFailure array{ok: false, status: int, message: string}
 */
final class CreateSchoolBroadcastAction
{
    /**
     * @param  array{
     *   school_id: string,
     *   school_class_id?: string|null,
     *   title: string,
     *   description?: string|null,
     *   subject?: string|null,
     *   priority?: string|null,
     *   due_date?: string|null
     * }  $validated
     * @return CreateBroadcastSuccess|CreateBroadcastFailure
     */
    public function execute(User $actor, array $validated, bool $belongsToSchool): array
    {
        if (! $actor->canBroadcastSchoolTasks()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        if (! $belongsToSchool) {
            return ['ok' => false, 'status' => 403, 'message' => 'No perteneces a este colegio'];
        }

        if (! empty($validated['school_class_id'])) {
            $class = SchoolClass::query()->findOrFail($validated['school_class_id']);
            if ((string) $class->school_id !== (string) $validated['school_id']) {
                return ['ok' => false, 'status' => 422, 'message' => 'Clase inválida para el colegio'];
            }
        }

        $broadcast = SchoolTaskBroadcast::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $validated['school_id'],
            'school_class_id' => $validated['school_class_id'] ?? null,
            'created_by' => $actor->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'priority' => $validated['priority'] ?? 'media',
            'due_date' => $validated['due_date'] ?? null,
            'status' => 'pending',
        ]);

        DispatchSchoolTaskBroadcastJob::dispatch($broadcast->id);

        return [
            'ok' => true,
            'broadcast' => $broadcast->fresh(['schoolClass', 'creator:id,name']),
        ];
    }
}
