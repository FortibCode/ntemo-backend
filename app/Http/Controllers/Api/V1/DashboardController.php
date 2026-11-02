<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardAnalyticsService $dashboardService
    ) {}

    /**
     * Récupère les indicateurs clés de performance (KPIs) pour le tableau de bord RH.
     */
    public function kpis(Request $request): JsonResponse
    {
        $organization = $request->user()->organization;

        $kpis = $this->dashboardService->getExecutiveKpis($organization);

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }
}

