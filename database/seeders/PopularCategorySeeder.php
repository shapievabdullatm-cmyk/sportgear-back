<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PopularCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PopularCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Названия основных категорий из CategorySeeder
        $popularCategoryTitles = [
            'Единоборства',
            'Фитнес',
            'Кардиотренажёры',
            'Игровые виды спорта',
            'Плавание',
            'Активный отдых',
            'Одежда и обувь',
            'Спортивная медицина',
        ];

        $position = 1;

        foreach ($popularCategoryTitles as $title) {
            // Находим категорию по названию
            $category = Category::where('title', $title)->first();

            if ($category) {
                // Создаём или обновляем популярную категорию
                PopularCategory::updateOrCreate(
                    ['category_id' => $category->id],
                    ['position' => $position]
                );

                $position++;
            }
        }
    }
}