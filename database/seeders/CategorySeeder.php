<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Param;
use App\Services\ImageService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class
CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Получаем параметры для привязки
        $params = $this->getParams();

        // Структура категорий из HTML файла
        $categories = [
            [
                'title' => 'Единоборства',
                'image' => 'edinoborstva.png',
                'params' => ['size', 'color', 'material', 'brand', 'gender', 'season'],
                'children' => [
                    [
                        'title' => 'Бокс',
                        'params' => ['weight'],
                        'children' => [
                            ['title' => 'Перчатки боксёрские', 'params' => ['size']],
                            ['title' => 'Шлемы', 'params' => ['size']],
                            ['title' => 'Боксёрки (обувь)', 'params' => ['size']],
                            ['title' => 'Бинты и быстрые бинты', 'params' => ['length']],
                            ['title' => 'Форма (шорты, майки)', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Дзюдо / Самбо',
                        'params' => [],
                        'children' => [
                            ['title' => 'Кимоно дзюдо', 'params' => ['size']],
                            ['title' => 'Кимоно самбо', 'params' => ['size']],
                            ['title' => 'Шорты самбо', 'params' => []],
                            ['title' => 'Пояса', 'params' => ['length']],
                        ],
                    ],
                    [
                        'title' => 'Карате',
                        'params' => [],
                        'children' => [
                            ['title' => 'Кимоно', 'params' => ['size']],
                            ['title' => 'Накладки на руки', 'params' => ['size']],
                            ['title' => 'Перчатки карате', 'params' => ['size']],
                            ['title' => 'Футы для ног', 'params' => ['size']],
                        ],
                    ],
                    [
                        'title' => 'ММА',
                        'params' => [],
                        'children' => [
                            ['title' => 'Перчатки ММА', 'params' => ['size']],
                            ['title' => 'Рашгарды', 'params' => ['fit-type']],
                            ['title' => 'Шорты ММА', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Борьба / Рукопашный бой',
                        'params' => [],
                        'children' => [
                            ['title' => 'Борцовки', 'params' => ['size']],
                            ['title' => 'Трико', 'params' => ['size']],
                            ['title' => 'Перчатки рукопашный бой', 'params' => ['size']],
                        ],
                    ],
                    [
                        'title' => 'Тайский бокс / Таеквандо',
                        'params' => [],
                        'children' => [
                            ['title' => 'Шорты тайский бокс', 'params' => []],
                            ['title' => 'Шлемы таеквандо', 'params' => ['size']],
                            ['title' => 'Ракетки таеквандо', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Снаряды',
                        'params' => ['weight', 'product-length', 'width', 'height'],
                        'children' => [
                            ['title' => 'Боксёрские мешки', 'params' => []],
                            ['title' => 'Груши', 'params' => []],
                            ['title' => 'Лапы и макивары', 'params' => []],
                            ['title' => 'Манекены', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Защита',
                        'params' => [],
                        'children' => [
                            ['title' => 'Капы', 'params' => ['size']],
                            ['title' => 'Защита голени и стопы', 'params' => ['size']],
                            ['title' => 'Защита паха', 'params' => ['size']],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Фитнес',
                'image' => 'fitnes.png',
                'params' => ['color', 'material', 'brand'],
                'children' => [
                    [
                        'title' => 'Свободные веса',
                        'params' => ['weight'],
                        'children' => [
                            ['title' => 'Гантели (виниловые, литые, неопрен, разборные)', 'params' => []],
                            ['title' => 'Гири', 'params' => []],
                            ['title' => 'Грифы для штанги', 'params' => ['product-length']],
                            ['title' => 'Блины для штанги и гантелей', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Силовые конструкции',
                        'params' => ['weight', 'product-length', 'width', 'height'],
                        'children' => [
                            ['title' => 'Турники и брусья', 'params' => []],
                            ['title' => 'Гимнастические кольца', 'params' => []],
                            ['title' => 'Скамьи силовые', 'params' => []],
                            ['title' => 'Упоры для отжиманий', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Функциональный тренинг',
                        'params' => [],
                        'children' => [
                            ['title' => 'Скакалки', 'params' => ['product-length']],
                            ['title' => 'Медболы', 'params' => ['weight']],
                            ['title' => 'Обручи', 'params' => []],
                            ['title' => 'Степ-платформы', 'params' => ['product-length', 'width', 'height']],
                            ['title' => 'Тренировочные маски', 'params' => ['size']],
                        ],
                    ],
                    [
                        'title' => 'Растяжка и восстановление',
                        'params' => [],
                        'children' => [
                            ['title' => 'Коврики', 'params' => ['product-length', 'width']],
                            ['title' => 'Роллы массажные', 'params' => ['product-length']],
                            ['title' => 'Массажные палки', 'params' => ['product-length']],
                            ['title' => 'Блоки для йоги', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Эспандеры и резинки',
                        'params' => [],
                        'children' => [
                            ['title' => 'Петли резиновые', 'params' => []],
                            ['title' => 'Силовые эспандеры', 'params' => []],
                            ['title' => 'Резинки и ленты', 'params' => []],
                            ['title' => 'Кистевые эспандеры', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Утяжелители и пояса',
                        'params' => ['weight', 'size'],
                        'children' => [
                            ['title' => 'Утяжелители для ног и рук', 'params' => []],
                            ['title' => 'Жилет-утяжелитель', 'params' => []],
                            ['title' => 'Пояса для тяжёлой атлетики', 'params' => []],
                            ['title' => 'Пояса для похудания', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Аксессуары для фитнеса',
                        'params' => [],
                        'children' => [
                            ['title' => 'Перчатки для фитнеса', 'params' => ['size']],
                            ['title' => 'Диски здоровья', 'params' => []],
                            ['title' => 'Весогонки', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Кардиотренажёры',
                'image' => 'kardiotrenazery.png',
                'params' => ['brand', 'weight', 'product-length', 'width', 'height'],
                'children' => [
                    [
                        'title' => 'Велотренажёры',
                        'params' => [],
                        'children' => [
                            ['title' => 'Магнитные велотренажёры', 'params' => []],
                            ['title' => 'Горизонтальные велотренажёры', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Спинбайки',
                        'params' => [],
                        'children' => [
                            ['title' => 'Спинбайки', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Игровые виды спорта',
                'image' => 'igrovye-vidy-sporta.png',
                'params' => ['brand', 'color', 'material'],
                'children' => [
                    [
                        'title' => 'Футбол',
                        'params' => ['size'],
                        'children' => [
                            ['title' => 'Мячи', 'params' => []],
                            ['title' => 'Перчатки вратарские', 'params' => []],
                            ['title' => 'Ворота и сетки', 'params' => ['product-length', 'width', 'height']],
                            ['title' => 'Манишки и шорты', 'params' => []],
                            ['title' => 'Фишки', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Баскетбол',
                        'params' => ['size'],
                        'children' => [
                            ['title' => 'Мячи', 'params' => []],
                            ['title' => 'Кольца', 'params' => []],
                            ['title' => 'Сетки', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Волейбол',
                        'params' => ['size'],
                        'children' => [
                            ['title' => 'Мячи', 'params' => []],
                            ['title' => 'Сетки', 'params' => ['product-length', 'height']],
                            ['title' => 'Наколенники', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Теннис',
                        'params' => [],
                        'children' => [
                            ['title' => 'Ракетки для настольного тенниса', 'params' => []],
                            ['title' => 'Мячи для настольного тенниса', 'params' => []],
                            ['title' => 'Столы теннисные', 'params' => ['product-length', 'width', 'height']],
                            ['title' => 'Мячи для большого тенниса', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Бадминтон / Гандбол',
                        'params' => [],
                        'children' => [
                            ['title' => 'Бадминтон', 'params' => []],
                            ['title' => 'Гандбол', 'params' => ['size']],
                        ],
                    ],
                    [
                        'title' => 'Инвентарь',
                        'params' => [],
                        'children' => [
                            ['title' => 'Насосы', 'params' => []],
                            ['title' => 'Свистки', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Плавание',
                'image' => 'plavanie.png',
                'params' => ['size', 'color', 'material', 'brand', 'gender'],
                'children' => [
                    [
                        'title' => 'Экипировка',
                        'params' => [],
                        'children' => [
                            ['title' => 'Очки для плавания', 'params' => []],
                            ['title' => 'Шапочки', 'params' => []],
                            ['title' => 'Ласты', 'params' => []],
                            ['title' => 'Доски для плавания', 'params' => ['product-length', 'width']],
                            ['title' => 'Плавки', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Активный отдых',
                'image' => 'aktivnyi-otdyx.png',
                'params' => ['brand', 'color'],
                'children' => [
                    [
                        'title' => 'Роликовые коньки',
                        'params' => ['size'],
                        'children' => [
                            ['title' => 'Роликовые коньки', 'params' => []],
                            ['title' => 'Комплекты с защитой и шлемом', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Самокаты',
                        'params' => ['weight', 'height'],
                        'children' => [
                            ['title' => 'Самокаты', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Скандинавская ходьба',
                        'params' => ['product-length'],
                        'children' => [
                            ['title' => 'Палки для скандинавской ходьбы', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Прочее',
                        'params' => ['weight', 'product-length', 'width'],
                        'children' => [
                            ['title' => 'Батуты', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Одежда и обувь',
                'image' => 'odezda-i-obuv.png',
                'params' => ['size', 'color', 'material', 'brand', 'gender', 'season', 'composition', 'country'],
                'children' => [
                    [
                        'title' => 'Одежда',
                        'params' => ['fit-type', 'style', 'pattern'],
                        'children' => [
                            ['title' => 'Футболки и майки', 'params' => ['sleeve-type', 'neckline-type']],
                            ['title' => 'Шорты', 'params' => ['length']],
                            ['title' => 'Брюки', 'params' => ['length']],
                            ['title' => 'Носки', 'params' => []],
                            ['title' => 'Термобельё и компрессия', 'params' => []],
                            ['title' => 'Кепки и бейсболки', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Обувь',
                        'params' => [],
                        'children' => [
                            ['title' => 'Спортивная обувь', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Сумки и рюкзаки',
                        'params' => ['product-length', 'width', 'height'],
                        'children' => [
                            ['title' => 'Сумки', 'params' => []],
                            ['title' => 'Рюкзаки', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Спортивная медицина',
                'image' => 'sportivnaia-medicina.png',
                'params' => ['size', 'color', 'material', 'brand'],
                'children' => [
                    [
                        'title' => 'Поддержка суставов',
                        'params' => [],
                        'children' => [
                            ['title' => 'Суппорты', 'params' => []],
                            ['title' => 'Корсеты', 'params' => []],
                            ['title' => 'Кинезиотейпы', 'params' => ['product-length', 'width']],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Детям',
                'image' => 'detiam.png',
                'params' => ['size', 'color', 'brand', 'gender'],
                'children' => [
                    [
                        'title' => 'Спортивные комплексы',
                        'params' => ['weight', 'product-length', 'width', 'height'],
                        'children' => [
                            ['title' => 'ДСК (детские спортивные комплексы)', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Художественная гимнастика',
                        'params' => [],
                        'children' => [
                            ['title' => 'Мячи', 'params' => []],
                            ['title' => 'Жгут гимнастический', 'params' => ['product-length']],
                        ],
                    ],
                    [
                        'title' => 'Дартс',
                        'params' => [],
                        'children' => [
                            ['title' => 'Наборы для дартса', 'params' => []],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Инвентарь',
                'image' => 'inventar-1.png',
                'params' => ['brand', 'color', 'material'],
                'children' => [
                    [
                        'title' => 'Покрытия',
                        'params' => ['product-length', 'width'],
                        'children' => [
                            ['title' => 'Будоматы', 'params' => []],
                            ['title' => 'Маты и напольные покрытия', 'params' => []],
                        ],
                    ],
                    [
                        'title' => 'Ёмкости и измерения',
                        'params' => [],
                        'children' => [
                            ['title' => 'Бутылки для воды', 'params' => []],
                            ['title' => 'Шейкеры', 'params' => []],
                            ['title' => 'Весы', 'params' => []],
                            ['title' => 'Секундомеры', 'params' => []],
                        ],
                    ],
                ],
            ],
        ];

        // Создаём категории рекурсивно
        foreach ($categories as $index => $categoryData) {
            $this->createCategory($categoryData, null, $index, $params);
        }
    }

    /**
     * Рекурсивное создание категорий с привязкой параметров
     */
    private function createCategory(array $data, ?int $parentId, int $position, array $params): void
    {
        // Проверяем, существует ли категория
        $category = Category::where('title', $data['title'])
            ->where('parent_id', $parentId)
            ->first();

        if (!$category) {
            // Создаём новую категорию с явной генерацией slug
            $category = new Category([
                'title' => $data['title'],
                'parent_id' => $parentId,
                'position' => $position,
            ]);

            // Генерируем slug вручную, если модель не сделала это автоматически
            if (empty($category->slug)) {
                $category->slug = Category::uniqueSlug($data['title']);
            }

            // Обрабатываем изображение, если оно указано (только для основных категорий)
            if (!empty($data['image']) && is_null($parentId)) {
                $localPath = database_path("seeders/сategories/{$data['image']}");

                if (File::exists($localPath)) {
                    $file = new UploadedFile(
                        $localPath,
                        $data['image'],
                        File::mimeType($localPath),
                        null,
                        true
                    );

                    $category->image = ImageService::uploadCategoryImage($file);
                    $this->command->info("Изображение '{$data['image']}' загружено для категории '{$data['title']}'");
                } else {
                    $this->command->warn("Файл {$data['image']} не найден для категории '{$data['title']}'");
                }
            }

            $category->save();
        } else {
            // Обновляем позицию и изображение
            $updateData = ['position' => $position];

            // Обрабатываем изображение для существующей категории
            if (!empty($data['image']) && is_null($parentId) && empty($category->image)) {
                $localPath = database_path("seeders/сategories/{$data['image']}");

                if (File::exists($localPath)) {
                    $file = new UploadedFile(
                        $localPath,
                        $data['image'],
                        File::mimeType($localPath),
                        null,
                        true
                    );

                    $updateData['image'] = ImageService::uploadCategoryImage($file);
                    $this->command->info("Изображение '{$data['image']}' загружено для категории '{$data['title']}'");
                }
            }

            $category->update($updateData);
        }

        // Привязываем параметры к категории
        if (!empty($data['params'])) {
            $attach = [];
            $sort = 0;
            foreach ($data['params'] as $paramSlug) {
                if (isset($params[$paramSlug])) {
                    $attach[$params[$paramSlug]->id] = [
                        'sort' => $sort++,
                        'is_required' => in_array($paramSlug, ['size', 'color', 'material', 'brand']), // Основные параметры обязательны
                    ];
                }
            }
            if (!empty($attach)) {
                $category->params()->syncWithoutDetaching($attach);
            }
        }

        // Создаём дочерние категории
        if (!empty($data['children'])) {
            foreach ($data['children'] as $childIndex => $childData) {
                $this->createCategory($childData, $category->id, $childIndex, $params);
            }
        }
    }

    /**
     * Получаем все параметры для привязки
     */
    private function getParams(): array
    {
        $slugs = [
            'size', 'color', 'material', 'brand', 'gender', 'season', 'composition', 'country',
            'fit-type', 'length', 'sleeve-type', 'neckline-type', 'fastener', 'pattern', 'style',
            'weight', 'product-length', 'width', 'height',
        ];

        $params = [];
        foreach ($slugs as $slug) {
            $param = Param::where('slug', $slug)->first();
            if ($param) {
                $params[$slug] = $param;
            }
        }

        return $params;
    }
}
