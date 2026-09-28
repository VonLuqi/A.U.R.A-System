<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed system categories (idempotent by slug).
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Receitas', 'slug' => 'receitas', 'type' => 'income', 'color' => '#A8E6C3'],
            ['name' => 'Alimentação', 'slug' => 'alimentacao', 'type' => 'expense', 'color' => '#DCCFFF'],
            ['name' => 'Transporte', 'slug' => 'transporte', 'type' => 'expense', 'color' => '#9A9C9B'],
            ['name' => 'Moradia', 'slug' => 'moradia', 'type' => 'expense', 'color' => '#6E706F'],
            ['name' => 'Saúde', 'slug' => 'saude', 'type' => 'expense', 'color' => '#FCFDFC'],
            ['name' => 'Lazer', 'slug' => 'lazer', 'type' => 'expense', 'color' => '#DCCFFF'],
            ['name' => 'Educação', 'slug' => 'educacao', 'type' => 'expense', 'color' => '#9A9C9B'],
            ['name' => 'Transferências', 'slug' => 'transferencias', 'type' => 'transfer', 'color' => '#3A3C3B'],
            ['name' => 'Outros', 'slug' => 'outros', 'type' => 'expense', 'color' => '#525554'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'color' => $category['color'],
                    'is_system' => true,
                ]
            );
        }
    }
}
