<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\IndexUsersRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;

/**
 * Admin user management API (PLAN_EXPANSAO §2.3).
 *
 * Soft-block via DELETE (is_active=false). Admin accounts are immutable via API.
 */
class UserController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly UsageLimitService $usageLimits,
    ) {}

    public function index(IndexUsersRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = (int) ($request->validated('per_page') ?? self::PER_PAGE);

        $query = User::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('role')) {
            $query->where('role', $request->validated('role'));
        }

        if ($request->has('is_active') && $request->validated('is_active') !== null) {
            $query->where('is_active', (bool) $request->validated('is_active'));
        }

        if ($request->filled('q')) {
            $q = '%'.addcslashes((string) $request->validated('q'), '%_\\').'%';
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', $q)
                    ->orWhere('email', 'like', $q);
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => UserResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
            'uploads_used' => 0,
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        return response()->json([
            'data' => (new UserResource($user->fresh()))->resolve(),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => (new UserResource($user))->resolve(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        unset($data['reset_usage']);

        if ($data !== []) {
            $user->fill($data)->save();
        }

        if ($request->boolean('reset_usage')) {
            $this->usageLimits->resetCounters($user);
        }

        return response()->json([
            'data' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    /**
     * Soft-block: set is_active=false (preferred over hard delete).
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->forceFill(['is_active' => false])->save();

        return response()->json([
            'data' => (new UserResource($user->fresh()))->resolve(),
            'message' => 'Usuário desativado (soft-block).',
        ]);
    }
}
