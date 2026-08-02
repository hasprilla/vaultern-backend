<?php

declare(strict_types=1);

namespace App\Application\Task\Queries;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListTasksQuery
{
    /**
     * @param  array{assigned_to?: string|null, status?: string|null}  $filters
     */
    public function execute(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Task::query()->with(['creator', 'assignee']);

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        $status = $filters['status'] ?? null;

        if ($status === 'overdue') {
            $query->where('status', '!=', 'done')
                ->where(function ($builder) {
                    $builder->where('status', 'overdue')
                        ->orWhere(function ($nested) {
                            $nested->whereNotNull('due_date')
                                ->whereDate('due_date', '<', now());
                        });
                });
        } elseif ($status === 'pending') {
            $query->where('status', 'pending')
                ->where(function ($builder) {
                    $builder->whereNull('due_date')
                        ->orWhereDate('due_date', '>=', now()->toDateString());
                });
        } elseif (! empty($status)) {
            $query->where('status', $status);
        }

        if ($status === 'overdue') {
            return $query->orderBy('due_date')->paginate($perPage);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }
}
