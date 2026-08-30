<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\CancelFamilyEventAction;
use App\Application\Family\Actions\CreateFamilyEventAction;
use App\Application\Family\Actions\UpdateFamilyEventAction;
use App\Application\Family\Queries\ListFamilyEventsQuery;
use App\Http\Controllers\Concerns\FindsFamilyEvent;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\StoreFamilyEventRequest;
use App\Http\Requests\Api\V1\Family\UpdateFamilyEventRequest;
use App\Http\Resources\Api\V1\Family\FamilyEventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyEventController extends Controller
{
    use FindsFamilyEvent;
    use ResolvesPagination;

    public function __construct(
        private readonly ListFamilyEventsQuery $listEvents,
        private readonly CreateFamilyEventAction $createEvent,
        private readonly UpdateFamilyEventAction $updateEvent,
        private readonly CancelFamilyEventAction $cancelEvent,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $kind = $request->query('kind');
        $kindFilter = is_string($kind) && $kind !== '' ? $kind : null;

        return FamilyEventResource::collection(
            $this->listEvents->execute(
                $request->user(),
                $this->perPage($request, 50),
                $kindFilter,
            ),
        )->response();
    }

    public function store(StoreFamilyEventRequest $request): JsonResponse
    {
        $event = $this->createEvent->execute($request->user(), $request->validated());

        return response()->json(['data' => new FamilyEventResource($event)], 201);
    }

    public function show(Request $request, string $event): JsonResponse
    {
        return response()->json([
            'data' => new FamilyEventResource($this->findForFamily($request, $event)),
        ]);
    }

    public function update(UpdateFamilyEventRequest $request, string $event): JsonResponse
    {
        $model = $this->updateEvent->execute(
            $request->user(),
            $this->findForFamily($request, $event),
            $request->validated(),
        );

        return response()->json(['data' => new FamilyEventResource($model)]);
    }

    public function cancel(Request $request, string $event): JsonResponse
    {
        $model = $this->cancelEvent->execute(
            $request->user(),
            $this->findForFamily($request, $event),
        );

        return response()->json(['data' => new FamilyEventResource($model)]);
    }
}
