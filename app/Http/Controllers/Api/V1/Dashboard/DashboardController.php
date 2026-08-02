<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Application\Dashboard\Actions\GetDashboardAnalyticsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardAnalyticsAction $getDashboardAnalytics,
    ) {}

    public function analytics(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'weekly');
        $payload = $this->getDashboardAnalytics->execute($request->user(), $period);

        return response()->json(['data' => $payload]);
    }
}
