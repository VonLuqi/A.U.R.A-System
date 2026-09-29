<?php

namespace App\Http\Controllers;

use App\Http\Requests\Aliases\PreviewAliasRequest;
use App\Http\Requests\Aliases\RememberAliasRequest;
use App\Http\Requests\Aliases\StoreAliasRequest;
use App\Http\Requests\Aliases\UpdateAliasRequest;
use App\Http\Resources\TransactionAliasResource;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Services\AliasResolutionService;
use App\Services\AliasRetroactiveApplyService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Alias / categorization rules API (PLAN_EXPANSAO §4.2 / §4.3).
 */
class TransactionAliasController extends Controller
{
    public const PER_PAGE = 50;

    public function __construct(
        private readonly AliasResolutionService $aliases,
        private readonly UsageLimitService $usageLimits,
        private readonly AliasRetroactiveApplyService $retroactive,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TransactionAlias::class);

        $perPage = min(100, max(1, (int) $request->integer('per_page', self::PER_PAGE)));

        $query = TransactionAlias::query()
            ->forUser($request->user())
            ->with('category')
            ->orderBy('priority')
            ->orderBy('id');

        if ($request->filled('q')) {
            $q = '%'.addcslashes((string) $request->string('q'), '%_\\').'%';
            $query->where(function ($builder) use ($q): void {
                $builder->where('match_pattern', 'like', $q)
                    ->orWhere('display_name', 'like', $q);
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== null && $request->input('is_active') !== '') {
            $active = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $query->where('is_active', $active);
            }
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => TransactionAliasResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'aliases_used' => $this->usageLimits->aliasesUsed($request->user()),
                'aliases_remaining' => $this->usageLimits->aliasesRemaining($request->user()),
            ],
        ]);
    }

    public function store(StoreAliasRequest $request): JsonResponse
    {
        $this->authorize('create', TransactionAlias::class);
        $this->usageLimits->assertCanCreateAlias($request->user());

        $alias = TransactionAlias::query()->create([
            ...$request->payload(),
            'user_id' => $request->user()->id,
        ]);

        $this->aliases->forget($request->user());

        $payload = [
            'data' => (new TransactionAliasResource($alias->load('category')))->resolve(),
        ];

        if ($request->applyToExisting()) {
            $payload['retroactive'] = $this->retroactive->apply($alias);
        }

        return response()->json($payload, 201);
    }

    public function show(TransactionAlias $alias): JsonResponse
    {
        $this->authorize('view', $alias);

        return response()->json([
            'data' => (new TransactionAliasResource($alias->loadMissing('category')))->resolve(),
        ]);
    }

    public function update(UpdateAliasRequest $request, TransactionAlias $alias): JsonResponse
    {
        $this->authorize('update', $alias);

        $alias->fill($request->payload())->save();
        $this->aliases->forget($request->user());

        return response()->json([
            'data' => (new TransactionAliasResource($alias->fresh()->load('category')))->resolve(),
        ]);
    }

    public function destroy(TransactionAlias $alias): JsonResponse
    {
        $this->authorize('delete', $alias);

        $user = request()->user();
        $alias->delete();
        $this->aliases->forget($user);

        return response()->json([
            'message' => 'Apelido/regra removido.',
        ]);
    }

    public function preview(PreviewAliasRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TransactionAlias::class);

        $match = $this->aliases->resolve($request->user(), (string) $request->validated('description'));

        return response()->json([
            'data' => $match === null ? null : [
                'alias_id' => $match->aliasId,
                'display_name' => $match->displayName,
                'category_id' => $match->categoryId,
            ],
        ]);
    }

    public function remember(
        RememberAliasRequest $request,
        Transaction $transaction,
    ): JsonResponse {
        $this->authorize('view', $transaction);
        $this->authorize('create', TransactionAlias::class);
        $this->usageLimits->assertCanCreateAlias($request->user());

        $payload = $request->payload($transaction);

        $exists = TransactionAlias::query()
            ->forUser($request->user())
            ->where('match_type', $payload['match_type'])
            ->where('match_pattern', $payload['match_pattern'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'match_pattern' => 'Já existe uma regra com este padrão e tipo.',
            ]);
        }

        $alias = TransactionAlias::query()->create([
            ...$payload,
            'user_id' => $request->user()->id,
        ]);

        $this->aliases->forget($request->user());

        $response = [
            'data' => (new TransactionAliasResource($alias->load('category')))->resolve(),
        ];

        if ($request->applyToExisting()) {
            $response['retroactive'] = $this->retroactive->apply($alias);
        }

        return response()->json($response, 201);
    }
}
