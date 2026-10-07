<?php

namespace App\Http\Controllers;

use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Categories API — global catalog (PLAN_EXPANSAO §2.2).
 *
 * CRUD: list/create/update/destroy. System rows cannot be deleted; type locked on update.
 */
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->withCount(['transactions', 'goals', 'transactionAliases'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type', 'color', 'is_system']);

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

        $category->loadCount(['transactions', 'goals', 'transactionAliases']);

        return response()->json([
            'data' => (new CategoryResource($category))->resolve(),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();
        $name = trim((string) $validated['name']);

        $payload = [
            'name' => $name,
        ];

        if (array_key_exists('color', $validated)) {
            $payload['color'] = $validated['color'];
        }

        if (! $category->is_system && array_key_exists('type', $validated)) {
            $payload['type'] = $validated['type'];
        }

        if ($name !== (string) $category->name) {
            $payload['slug'] = $this->uniqueSlug($name, (int) $category->id);
        }

        $category->fill($payload);
        $category->save();
        $category->loadCount(['transactions', 'goals', 'transactionAliases']);

        return response()->json([
            'data' => (new CategoryResource($category))->resolve(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->is_system) {
            throw ValidationException::withMessages([
                'category' => ['Categorias do sistema não podem ser excluídas.'],
            ]);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoria excluída.',
        ]);
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'categoria';
        }

        $slug = $base;
        $suffix = 1;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
