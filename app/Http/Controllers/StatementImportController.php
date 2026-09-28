<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatementImportResource;
use App\Models\StatementImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/statements (+ show) — Etapa C §5.7 / §6.2 / §6.3.
 *
 * `{statementImport}` is owner-scoped via Route::bind; Policy is defense-in-depth.
 */
class StatementImportController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StatementImport::class);

        $paginator = $request->user()
            ->statementImports()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return response()->json([
            'data' => StatementImportResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(StatementImport $statementImport): JsonResponse
    {
        $this->authorize('view', $statementImport);

        return response()->json([
            'data' => (new StatementImportResource($statementImport))->resolve(),
        ]);
    }
}
