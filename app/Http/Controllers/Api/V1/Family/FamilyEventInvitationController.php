<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Queries\ListMyEventInvitationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Family\FamilyEventGuestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyEventInvitationController extends Controller
{
    public function __construct(
        private readonly ListMyEventInvitationsQuery $listInvitations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return FamilyEventGuestResource::collection(
            $this->listInvitations->execute($request->user()),
        )->response();
    }
}
