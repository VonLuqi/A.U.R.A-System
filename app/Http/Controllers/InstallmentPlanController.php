<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallmentPlans\IndexInstallmentPlansRequest;
use App\Http\Requests\InstallmentPlans\StoreInstallmentPlanRequest;
use App\Http\Requests\InstallmentPlans\UpdateInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlanResource;
use App\Models\InstallmentPlan;
use App\Services\InstallmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Parcelamentos API (cartão + cobranças).
 */
class InstallmentPlanController extends Controller
{
    public function __construct(
        private readonly InstallmentPlanService $plans,
    ) {}

    public function index(IndexInstallmentPlansRequest $request): JsonResponse
    {
        $this->authorize('viewAny', InstallmentPlan::class);

        if ($request->shouldBackfill()) {
            $this->plans->backfillForUser($request->user());
        }

        $paginator = $this->plans
            ->queryForUser($request->user(), $request->filters())
            ->paginate($request->perPage())
            ->withQueryString();

        return response()->json([
            'data' => InstallmentPlanResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $this->authorize('create', InstallmentPlan::class);

        $plan = $this->plans->createManual($request->user(), $request->payload());

        return response()->json([
            'data' => (new InstallmentPlanResource($plan))->resolve(),
        ], 201);
    }

    public function show(InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('view', $installmentPlan);

        $installmentPlan->load(['items', 'creditCard:id,name', 'debtor:id,name']);

        return response()->json([
            'data' => (new InstallmentPlanResource($installmentPlan))->resolve(),
        ]);
    }

    public function update(UpdateInstallmentPlanRequest $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('update', $installmentPlan);

        $plan = $this->plans->update($request->user(), $installmentPlan, $request->payload());

        return response()->json([
            'data' => (new InstallmentPlanResource($plan))->resolve(),
        ]);
    }

    public function cancel(Request $request, InstallmentPlan $installmentPlan): JsonResponse
    {
        $this->authorize('update', $installmentPlan);

        $plan = $this->plans->cancel($request->user(), $installmentPlan);

        return response()->json([
            'data' => (new InstallmentPlanResource($plan))->resolve(),
        ]);
    }

    public function markItemPaid(
        Request $request,
        InstallmentPlan $installmentPlan,
        int $number,
    ): JsonResponse {
        $this->authorize('update', $installmentPlan);

        $plan = $this->plans->markItemPaid($request->user(), $installmentPlan, $number);

        return response()->json([
            'data' => (new InstallmentPlanResource($plan))->resolve(),
        ]);
    }

    public function markItemOpen(
        Request $request,
        InstallmentPlan $installmentPlan,
        int $number,
    ): JsonResponse {
        $this->authorize('update', $installmentPlan);

        $plan = $this->plans->markItemOpen($request->user(), $installmentPlan, $number);

        return response()->json([
            'data' => (new InstallmentPlanResource($plan))->resolve(),
        ]);
    }
}
