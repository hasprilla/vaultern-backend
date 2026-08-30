<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\CreateFamilyEventExpenseAction;
use App\Application\Family\Actions\DeleteFamilyEventExpenseAction;
use App\Application\Family\Actions\UpdateFamilyEventExpenseAction;
use App\Http\Controllers\Concerns\FindsFamilyEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Family\FamilyEventExpenseResource;
use App\Models\FamilyEventExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyEventExpenseController extends Controller
{
    use FindsFamilyEvent;

    public function index(Request $request, string $event): JsonResponse
    {
        $model = $this->findForFamily($request, $event);
        $items = $model->expenses()->orderByDesc('created_at')->get();

        return FamilyEventExpenseResource::collection($items)->response();
    }

    public function store(
        Request $request,
        string $event,
        CreateFamilyEventExpenseAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'category' => ['nullable', 'string', 'max:40'],
            'paid' => ['nullable', 'boolean'],
        ]);
        $row = $action->execute($request->user(), $this->findForFamily($request, $event), $validated);

        return response()->json(['data' => new FamilyEventExpenseResource($row)], 201);
    }

    public function update(
        Request $request,
        string $event,
        string $expense,
        UpdateFamilyEventExpenseAction $action,
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'category' => ['nullable', 'string', 'max:40'],
            'paid' => ['nullable', 'boolean'],
        ]);
        $model = $this->findForFamily($request, $event);
        $row = FamilyEventExpense::query()->findOrFail($expense);
        $updated = $action->execute($request->user(), $model, $row, $validated);

        return response()->json(['data' => new FamilyEventExpenseResource($updated)]);
    }

    public function destroy(
        Request $request,
        string $event,
        string $expense,
        DeleteFamilyEventExpenseAction $action,
    ): JsonResponse {
        $model = $this->findForFamily($request, $event);
        $row = FamilyEventExpense::query()->findOrFail($expense);
        $action->execute($request->user(), $model, $row);

        return response()->json(['message' => 'ok']);
    }
}
