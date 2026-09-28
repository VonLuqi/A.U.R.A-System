<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transactions\IndexTransactionsRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionQueryService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/transactions — filtered listing (Etapa C §5.4).
 */
class TransactionController extends Controller
{
    public function index(
        IndexTransactionsRequest $request,
        TransactionQueryService $queries,
    ): JsonResponse {
        $filters = $request->filters();

        $paginator = $queries
            ->forUser($request->user(), $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return response()->json([
            'data' => TransactionResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
