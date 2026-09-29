<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Categories API — global catalog (PLAN_EXPANSAO §2.2).
 *
 * GET list + POST create (user-created, is_system=false).
 * Per-user categories remain tech debt for a later etapa.
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

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $slug = $this->uniqueSlug($name);

        $category = Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'type' => $validated['type'] ?? 'expense',
            'color' => $validated['color'] ?? '#DCCFFF',
            'is_system' => false,
        ]);

        return response()->json([
            'data' => (new CategoryResource($category))->resolve(),
        ], 201);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'categoria';
        }

        $slug = $base;
        $suffix = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
