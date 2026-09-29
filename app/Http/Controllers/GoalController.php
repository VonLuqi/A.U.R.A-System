<?php

namespace App\Http\Controllers;

use App\Http\Requests\Goals\IndexGoalsRequest;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Services\GoalService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;

/**
 * Goals CRUD API (PLAN_EXPANSAO §7.2).
 */
class GoalController extends Controller
{
    public function __construct(
        private readonly GoalService $goals,
        private readonly UsageLimitService $usageLimits,
    ) {}

    public function index(IndexGoalsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Goal::class);

        $paginator = $this->goals
            ->queryForUser($request->user(), $request->filters())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => GoalResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'goals_used' => $this->usageLimits->goalsUsed($request->user()),
                'goals_remaining' => $this->usageLimits->goalsRemaining($request->user()),
            ],
        ]);
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $this->authorize('create', Goal::class);

        $goal = $this->goals->create($request->user(), $request->payload());

        return response()->json([
            'data' => (new GoalResource($goal))->resolve(),
        ], 201);
    }

    public function show(Goal $goal): JsonResponse
    {
        $this->authorize('view', $goal);

        $goal->loadMissing('category');

        return response()->json([
            'data' => (new GoalResource($goal))->resolve(),
        ]);
    }

    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);

        $updated = $this->goals->update($request->user(), $goal, $request->payload());

        return response()->json([
            'data' => (new GoalResource($updated))->resolve(),
        ]);
    }

    public function destroy(Goal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);

        $this->goals->delete($goal);

        return response()->json([
            'message' => 'Meta removida.',
        ]);
    }

    public function recalculate(Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);

        $updated = $this->goals->recalculate($goal);

        return response()->json([
            'data' => (new GoalResource($updated))->resolve(),
        ]);
    }
}
