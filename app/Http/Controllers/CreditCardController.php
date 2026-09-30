<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditCards\IndexCreditCardsRequest;
use App\Http\Requests\CreditCards\LinkCreditCardTransactionsRequest;
use App\Http\Requests\CreditCards\StoreCreditCardRequest;
use App\Http\Requests\CreditCards\UpdateCreditCardRequest;
use App\Http\Resources\CreditCardResource;
use App\Models\CreditCard;
use App\Services\CreditCardService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;

/**
 * Credit cards CRUD API (PLAN_CARTOES_EMPRESTIMOS §3.1).
 */
class CreditCardController extends Controller
{
    public function __construct(
        private readonly CreditCardService $creditCards,
        private readonly UsageLimitService $usageLimits,
    ) {}

    public function index(IndexCreditCardsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', CreditCard::class);

        $paginator = $this->creditCards
            ->queryForUser($request->user(), $request->filters())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => CreditCardResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'credit_cards_used' => $this->usageLimits->creditCardsUsed($request->user()),
                'credit_cards_remaining' => $this->usageLimits->creditCardsRemaining($request->user()),
            ],
        ]);
    }

    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $this->authorize('create', CreditCard::class);

        $card = $this->creditCards->create($request->user(), $request->payload());

        return response()->json([
            'data' => (new CreditCardResource($card))->resolve(),
        ], 201);
    }

    public function show(CreditCard $creditCard): JsonResponse
    {
        $this->authorize('view', $creditCard);

        return response()->json([
            'data' => (new CreditCardResource($creditCard))->resolve(),
        ]);
    }

    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $updated = $this->creditCards->update($request->user(), $creditCard, $request->payload());

        return response()->json([
            'data' => (new CreditCardResource($updated))->resolve(),
        ]);
    }

    public function destroy(CreditCard $creditCard): JsonResponse
    {
        $this->authorize('delete', $creditCard);

        $this->creditCards->delete($creditCard);

        return response()->json([
            'message' => 'Cartão removido.',
        ]);
    }

    public function linkTransactions(
        LinkCreditCardTransactionsRequest $request,
        CreditCard $creditCard,
    ): JsonResponse {
        $this->authorize('update', $creditCard);

        $result = $this->creditCards->linkTransactions(
            $request->user(),
            $creditCard,
            $request->transactionIds(),
        );

        return response()->json([
            'message' => $result['linked'] === 1
                ? '1 saída vinculada ao cartão.'
                : "{$result['linked']} saídas vinculadas ao cartão.",
            'data' => $result,
        ]);
    }
}
