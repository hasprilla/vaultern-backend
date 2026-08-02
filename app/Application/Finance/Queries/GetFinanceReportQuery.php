<?php

declare(strict_types=1);

namespace App\Application\Finance\Queries;

use App\Domains\Finance\Entities\FinanceReportPeriod;
use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Models\User;
use App\Services\ChildGuardianService;

final class GetFinanceReportQuery
{
    use ScopesByChildGuardianship;

    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    /**
     * @return array{
     *   period: string,
     *   label: string,
     *   income: float,
     *   expense: float,
     *   balance: float,
     *   from: string,
     *   to: string,
     *   child_id: string|null
     * }
     */
    public function execute(User $viewer, FinanceReportPeriod $period, ?int $childId): array
    {
        $from = now()->subDays($period->days())->startOfDay();
        $base = $this->transactionsForGuardian($viewer, $this->guardians)
            ->where('transaction_date', '>=', $from);

        if ($childId !== null) {
            $this->assertCanAccessChild($viewer, $this->guardians, $childId);
            $base->where('child_id', $childId);
        }

        $income = (clone $base)->where('type', 'income')->sum('amount');
        $expense = (clone $base)->where('type', 'expense')->sum('amount');

        return [
            'period' => $period->value,
            'label' => $period->label(),
            'income' => (float) $income,
            'expense' => (float) $expense,
            'balance' => (float) $income - (float) $expense,
            'from' => $from->toDateString(),
            'to' => now()->toDateString(),
            'child_id' => $childId !== null ? (string) $childId : null,
        ];
    }
}
