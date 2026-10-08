<?php

namespace App\Http\Controllers;

use App\Http\Requests\Analytics\DashboardAnalyticsRequest;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/analytics/dashboard — aggregates (Etapa C §5.5.3).
 */
class AnalyticsController extends Controller
{
    public function dashboard(
        DashboardAnalyticsRequest $request,
        AnalyticsService $analytics,
    ): JsonResponse {
        $filters = $request->filters();
        $payload = $analytics->dashboard($request->user(), $filters);

        return response()->json([
            'data' => [
                'filters' => [
                    'from' => $filters['from'],
                    'to' => $filters['to'],
                    'type' => $filters['type'],
                    'category_id' => $filters['category_id'],
                    'credit_card_id' => $filters['credit_card_id'],
                    'credit_card_ids' => $filters['credit_card_ids'],
                    'include_uncarded' => $filters['include_uncarded'],
                    'debtor_id' => $filters['debtor_id'],
                    'q' => $filters['q'],
                    'group_by' => $filters['group_by'],
                    'preset' => $filters['preset'],
                ],
                'cards' => $payload['cards'],
                'hub' => $payload['hub'],
                'series' => $payload['series'],
                'by_category' => $payload['by_category'],
                'by_alias' => $payload['by_alias'],
                'goals' => $payload['goals'],
            ],
        ]);
    }
}
