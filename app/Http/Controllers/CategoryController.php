<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/categories — simple ordered list (Etapa C §5.6).
 */
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'color']);

        return response()->json([
            'data' => CategoryResource::collection($categories)->resolve(),
        ]);
    }
}
