<?php

namespace App\Http\Controllers;

use App\Http\Requests\Debtors\IndexDebtorsRequest;
use App\Http\Requests\Debtors\LinkDebtorTransactionsRequest;
use App\Http\Requests\Debtors\StoreDebtorRequest;
use App\Http\Requests\Debtors\UpdateDebtorRequest;
use App\Http\Resources\DebtorResource;
use App\Models\Debtor;
use App\Models\Loan;
use App\Services\DebtorService;
use Illuminate\Http\JsonResponse;

/**
 * Pessoas / devedores CRUD (feature loans).
 */
class DebtorController extends Controller
{
    public function __construct(
        private readonly DebtorService $debtors,
    ) {}

    public function index(IndexDebtorsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Debtor::class);

        $paginator = $this->debtors
            ->queryForUser($request->user(), $request->filters())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => DebtorResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreDebtorRequest $request): JsonResponse
    {
        $this->authorize('create', Debtor::class);

        $debtor = $this->debtors->create($request->user(), $request->payload());

        return response()->json([
            'data' => (new DebtorResource($debtor))->resolve(),
        ], 201);
    }

    public function show(Debtor $debtor): JsonResponse
    {
        $this->authorize('view', $debtor);

        $debtor->loadCount([
            'loans as open_loans_count' => function ($q): void {
                $q->whereIn('status', ['open', 'partial']);
            },
        ]);
        $debtor->setAttribute(
            'open_remaining_total',
            Loan::query()
                ->where('debtor_id', $debtor->id)
                ->whereIn('status', ['open', 'partial'])
                ->selectRaw('COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0) as total')
                ->value('total') ?? 0,
        );

        return response()->json([
            'data' => (new DebtorResource($debtor))->resolve(),
        ]);
    }

    public function update(UpdateDebtorRequest $request, Debtor $debtor): JsonResponse
    {
        $this->authorize('update', $debtor);

        $updated = $this->debtors->update($request->user(), $debtor, $request->payload());

        return response()->json([
            'data' => (new DebtorResource($updated))->resolve(),
        ]);
    }

    public function destroy(Debtor $debtor): JsonResponse
    {
        $this->authorize('delete', $debtor);

        $this->debtors->delete(request()->user(), $debtor);

        return response()->json([
            'message' => 'Pessoa removida.',
        ]);
    }

    public function linkTransactions(
        LinkDebtorTransactionsRequest $request,
        Debtor $debtor,
    ): JsonResponse {
        $this->authorize('update', $debtor);

        $result = $this->debtors->linkTransactions(
            $request->user(),
            $debtor,
            $request->transactionIds(),
        );

        return response()->json([
            'message' => $result['linked'] === 1
                ? '1 saída vinculada à pessoa.'
                : "{$result['linked']} saídas vinculadas à pessoa.",
            'data' => $result,
        ]);
    }
}
