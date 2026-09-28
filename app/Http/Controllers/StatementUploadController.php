<?php

namespace App\Http\Controllers;

use App\Http\Requests\Statements\UploadStatementRequest;
use App\Services\StatementUploadService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/statements/upload (Etapa C §5.3).
 */
class StatementUploadController extends Controller
{
    public function store(
        UploadStatementRequest $request,
        StatementUploadService $service,
    ): JsonResponse {
        $summary = $service->handle(
            $request->user(),
            $request->file('file'),
            $request->source(),
        );

        return response()->json([
            'data' => $summary->toArray(),
        ], 201);
    }
}
