<?php

namespace App\Http\Controllers;

use App\Http\Requests\Statements\UploadStatementRequest;
use App\Models\StatementImport;
use App\Services\StatementUploadService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/statements/upload (Etapa C §5.3 / PLAN_EXPANSAO §9.2).
 */
class StatementUploadController extends Controller
{
    public function store(
        UploadStatementRequest $request,
        StatementUploadService $service,
    ): JsonResponse {
        $this->authorize('create', StatementImport::class);

        $summary = $service->handle(
            $request->user(),
            $request->file('file'),
            $request->source(),
            $request->statementKind(),
            $request->creditCardId(),
        );

        return response()->json([
            'data' => $summary->toArray(),
        ], 201);
    }
}
