<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\AddEventGuestAction;
use App\Application\Family\Actions\RsvpEventGuestAction;
use App\Application\Family\Actions\SyncEventGuestsAction;
use App\Http\Controllers\Concerns\FindsFamilyEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\StoreEventGuestRequest;
use App\Http\Requests\Api\V1\Family\SyncEventGuestsRequest;
use App\Http\Resources\Api\V1\Family\FamilyEventGuestResource;
use App\Http\Resources\Api\V1\Family\FamilyEventResource;
use App\Models\FamilyEventGuest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyEventGuestController extends Controller
{
    use FindsFamilyEvent;

    public function __construct(
        private readonly AddEventGuestAction $addGuest,
        private readonly SyncEventGuestsAction $syncGuests,
        private readonly RsvpEventGuestAction $rsvpGuest,
    ) {}

    public function index(Request $request, string $event): JsonResponse
    {
        return FamilyEventGuestResource::collection(
            $this->findForFamily($request, $event)->guests,
        )->response();
    }

    public function store(StoreEventGuestRequest $request, string $event): JsonResponse
    {
        $guest = $this->addGuest->execute(
            $request->user(),
            $this->findForFamily($request, $event),
            $request->validated(),
        );

        return response()->json(['data' => new FamilyEventGuestResource($guest)], 201);
    }

    public function sync(SyncEventGuestsRequest $request, string $event): JsonResponse
    {
        $model = $this->syncGuests->execute(
            $request->user(),
            $this->findForFamily($request, $event),
            $request->validated('guests'),
        );

        return response()->json(['data' => new FamilyEventResource($model)]);
    }

    public function rsvp(Request $request, string $event, string $guest): JsonResponse
    {
        $guestModel = FamilyEventGuest::query()
            ->where('event_id', $event)->with('event')->findOrFail($guest);
        $data = $request->validate([
            'status' => ['required', 'in:pending,attending,declined,maybe'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json([
            'data' => new FamilyEventGuestResource(
                $this->rsvpGuest->execute(
                    $request->user(),
                    $guestModel,
                    $data['status'],
                    $data['note'] ?? null,
                ),
            ),
        ]);
    }
}
