<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Budget;
use App\Models\Family;
use App\Models\Task;
use App\Models\Transaction;
use App\Policies\BudgetPolicy;
use App\Policies\FamilyPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Family::class, FamilyPolicy::class);
    }
}
