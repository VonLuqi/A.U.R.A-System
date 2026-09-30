<?php

namespace App\Http\Controllers;

use App\Http\Requests\Loans\IndexLoansRequest;
use App\Http\Requests\Loans\MarkLoanPaidRequest;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Http\Requests\Loans\UpdateLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Loans / cobranças CRUD API (PLAN_CARTOES_EMPRESTIMOS §3.2).
 */
class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loans,
        private readonly UsageLimitService $usageLimits,
    ) {}

    public function index(IndexLoansRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Loan::class);

        $paginator = $this->loans
            ->queryForUser($request->user(), $request->filters())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => LoanResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'loans_used' => $this->usageLimits->loansUsed($request->user()),
                'loans_remaining' => $this->usageLimits->loansRemaining($request->user()),
            ],
        ]);
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $this->authorize('create', Loan::class);

        $loan = $this->loans->create($request->user(), $request->payload());

        return response()->json([
            'data' => (new LoanResource($loan))->resolve(),
        ], 201);
    }

    public function show(Loan $loan): JsonResponse
    {
        $this->authorize('view', $loan);

        $loan->loadMissing('creditCard:id,name');

        return response()->json([
            'data' => (new LoanResource($loan))->resolve(),
        ]);
    }

    public function update(UpdateLoanRequest $request, Loan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $updated = $this->loans->update($request->user(), $loan, $request->payload());

        return response()->json([
            'data' => (new LoanResource($updated))->resolve(),
        ]);
    }

    public function destroy(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('delete', $loan);

        $this->loans->delete($request->user(), $loan);

        return response()->json([
            'message' => 'Cobrança removida.',
        ]);
    }

    public function markPaid(MarkLoanPaidRequest $request, Loan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $updated = $this->loans->markPaid($request->user(), $loan, $request->paidAmount());

        return response()->json([
            'data' => (new LoanResource($updated))->resolve(),
        ]);
    }

    public function cancel(Request $request, Loan $loan): JsonResponse
    {
        $this->authorize('update', $loan);

        $updated = $this->loans->cancel($request->user(), $loan);

        return response()->json([
            'data' => (new LoanResource($updated))->resolve(),
        ]);
    }
}
