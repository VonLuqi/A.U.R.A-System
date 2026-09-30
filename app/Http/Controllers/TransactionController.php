<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transactions\IndexTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\CategoryBulkApplyService;
use App\Services\ManualTransactionService;
use App\Services\TransactionQueryService;
use Illuminate\Http\JsonResponse;

/**
 * Transactions API — list + manual CRUD (Etapa C §5.4 / PLAN_EXPANSAO §3.1).
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

    public function store(
        StoreTransactionRequest $request,
        ManualTransactionService $manuals,
    ): JsonResponse {
        $this->authorize('create', Transaction::class);

        $transaction = $manuals->create($request->user(), $request->payload());

        return response()->json([
            'data' => (new TransactionResource($transaction))->resolve(),
        ], 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $transaction->loadMissing(['category', 'creditCard:id,name', 'loan:id,debtor_name,status']);

        return response()->json([
            'data' => (new TransactionResource($transaction))->resolve(),
        ]);
    }

    public function update(
        UpdateTransactionRequest $request,
        Transaction $transaction,
        ManualTransactionService $manuals,
        CategoryBulkApplyService $categoryBulk,
    ): JsonResponse {
        $this->authorize('update', $transaction);

        $payload = $request->payload();
        $updated = $manuals->update($request->user(), $transaction, $payload);

        $response = [
            'data' => (new TransactionResource($updated))->resolve(),
        ];

        if ($request->applyCategoryToMatching() && array_key_exists('category_id', $payload)) {
            $response['retroactive'] = $categoryBulk->applyToMatchingDescription(
                $request->user(),
                $updated,
                $payload['category_id'],
            );
        }

        return response()->json($response);
    }

    /**
     * Hard delete. Imported rows: statement_imports counters are not rewritten.
     */
    public function destroy(
        Transaction $transaction,
        ManualTransactionService $manuals,
    ): JsonResponse {
        $this->authorize('delete', $transaction);

        $manuals->delete($transaction);

        return response()->json([
            'message' => 'Lançamento excluído.',
        ]);
    }
}
