<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandOrigin;
use App\Models\Category;
use App\Models\ManufacturingCountry;
use App\Models\Param;
use App\Models\ParamOption;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductGroup;
use App\Models\ProductParamOptionValue;
use App\Models\ProductParamValue;
use App\Models\ProductStock;
use App\Models\SizeTable;
use App\Models\Warehouse;
use App\Services\ImageService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $paramCache = [];
    private array $optionCache = [];

    public function run(): void
    {
        $brands = $this->resolveBrands();
        $categories = $this->resolveCategories();
        $countries = $this->resolveCountries();
        $origins = $this->resolveOrigins();
        $groups = $this->resolveProductGroups();
        $sizeTables = $this->resolveSizeTables();
        $warehouses = Warehouse::where('is_active', true)->get();

        if ($warehouses->isEmpty()) {
            $this->command?->error('Нет активных складов — сначала запусти WarehouseSeeder');
            return;
        }

        $definitions = $this->productsDefinition();
        $createdBySlug = [];

        foreach ($definitions as $def) {
            $product = $this->createProduct($def, $brands, $categories, $countries, $origins, $groups, $sizeTables, $warehouses);
            if ($product) {
                $createdBySlug[$def['slug']] = $product;
            }
        }

        // Второй проход — «с этим товаром покупают»
        foreach ($definitions as $def) {
            $product = $createdBySlug[$def['slug']] ?? null;
            if (!$product || empty($def['bought_with'])) {
                continue;
            }

            $sync = [];
            foreach ($def['bought_with'] as $i => $slug) {
                $related = $createdBySlug[$slug] ?? null;
                if ($related) {
                    $sync[$related->id] = ['sort' => $i];
                }
            }
            $product->boughtTogetherProducts()->sync($sync);
        }

        echo "\n✅ Все товары созданы\n";
    }

    private function createProduct(
        array $def,
        array $brands,
        array $categories,
        array $countries,
        array $origins,
        array $groups,
        array $sizeTables,
        $warehouses
    ): ?Product {
        $slug = $def['slug'];
        $folder = database_path("seeders/products/{$slug}");

        if (!File::isDirectory($folder)) {
            $this->command?->warn("Папка не найдена: {$folder}");
            return null;
        }

        $existing = Product::where('slug', $slug)->first();
        if ($existing) {
            $this->purgeProduct($existing);
        }

        $sharedFields = [
            'article' => $def['article'],
            'description' => $def['description'],
            'price' => $def['price'],
            'old_price' => $def['old_price'] ?? null,
            'meta_title' => $def['title'] . ' — купить в Rage',
            'meta_description' => Str::limit(strip_tags($def['description']), 160),
            'meta_keywords' => $def['meta_keywords'],
            'weight' => $def['weight'],
            'is_active' => true,
            'category_id' => $categories[$def['category']]->id,
            'brand_id' => $brands[$def['brand']]->id,
            'brand_origin_id' => $origins[$def['origin']]->id ?? null,
            'manufacturing_country_id' => $countries[$def['country']]->id ?? null,
            'product_group_id' => $groups[$def['group']]->id ?? null,
            'size_table_id' => isset($def['size_table'], $sizeTables[$def['size_table']]) ? $sizeTables[$def['size_table']]->id : null,
        ];

        $product = new Product(array_merge($sharedFields, ['title' => $def['title']]));
        $product->slug = $slug;
        $product->save();

        $this->uploadImages($product, $folder);
        $this->attachParams($product, $def['params']);

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => $def['barcode'],
            'type' => 'EAN-13',
        ]);

        $this->createSizeVariants($product, $def, $sharedFields, $warehouses);

        echo "✅ {$def['title']} (+ " . count($def['sizes']) . " размеров)\n";

        return $product;
    }

    private function purgeProduct(Product $product): void
    {
        foreach ($product->children as $child) {
            $this->purgeProduct($child);
        }

        $product->images()->each(fn($img) => ImageService::delete($img->path));
        $product->images()->delete();
        $product->paramValues()->delete();
        $product->optionValues()->delete();
        $product->barcodes()->delete();
        $product->stocks()->delete();
        $product->boughtTogetherProducts()->detach();
        $product->delete();
    }

    private function createSizeVariants(Product $parent, array $def, array $sharedFields, $warehouses): void
    {
        $sizeParam = $this->getParam('size');
        if (!$sizeParam) return;

        foreach ($def['sizes'] as $size) {
            $sizeOption = $this->findOptionByValue('size', (string) $size);
            if (!$sizeOption) continue;

            $childSlug = $def['slug'] . '-' . Str::slug((string) $size, '-');
            $childTitle = $def['title'] . ' — размер ' . $size;
            $childArticle = $def['article'] . '-' . strtoupper((string) $size);

            $child = new Product(array_merge($sharedFields, [
                'title' => $childTitle,
                'article' => $childArticle,
                'parent_id' => $parent->id,
                'product_group_id' => null,
            ]));
            $child->slug = $childSlug;
            $child->save();

            ProductParamValue::create([
                'product_id' => $child->id,
                'param_id' => $sizeParam->id,
                'param_option_id' => $sizeOption->id,
            ]);

            foreach ($warehouses as $wh) {
                ProductStock::create([
                    'product_id' => $child->id,
                    'warehouse_id' => $wh->id,
                    'quantity' => rand(2, 12),
                    'reserved_quantity' => 0,
                ]);
            }
        }
    }

    private function uploadImages(Product $product, string $folder): void
    {
        $files = collect(File::files($folder))
            ->filter(fn($f) => in_array(strtolower($f->getExtension()), ['png', 'jpg', 'jpeg', 'webp']))
            ->sortBy(fn($f) => $f->getFilename())
            ->values();

        foreach ($files as $index => $fileInfo) {
            $uploaded = new UploadedFile(
                $fileInfo->getPathname(),
                $fileInfo->getFilename(),
                File::mimeType($fileInfo->getPathname()),
                null,
                true
            );

            $path = ImageService::uploadProductImage($uploaded, $product->id);

            $product->images()->create([
                'path' => $path,
                'sort_order' => $index,
                'is_main' => $index === 0,
            ]);
        }
    }

    private function attachParams(Product $product, array $params): void
    {
        foreach ($params as $slug => $value) {
            $param = $this->getParam($slug);
            if (!$param) continue;

            if ($param->filter_type === 6) {
                $option = $this->findColorOption($value);
                if ($option) {
                    ProductParamValue::create([
                        'product_id' => $product->id,
                        'param_id' => $param->id,
                        'param_option_id' => $option->id,
                    ]);
                }
                continue;
            }

            if ($param->filter_type === 8) {
                $values = is_array($value) ? $value : [$value];
                foreach ($values as $v) {
                    $option = $this->findOptionByValue($slug, $v);
                    if ($option) {
                        ProductParamOptionValue::firstOrCreate([
                            'product_id' => $product->id,
                            'param_id' => $param->id,
                            'param_option_id' => $option->id,
                        ]);
                    }
                }
                continue;
            }

            $data = ['product_id' => $product->id, 'param_id' => $param->id];
            match ($param->filter_type) {
                1 => $data['value_string'] = (string) $value,
                2 => $data['value_text'] = (string) $value,
                3 => $data['value_int'] = (int) $value,
                4 => $data['value_float'] = (float) $value,
                7 => $data['value_int'] = $value ? 1 : 0,
                default => null,
            };
            ProductParamValue::create($data);
        }
    }

    private function getParam(string $slug): ?Param
    {
        if (!isset($this->paramCache[$slug])) {
            $this->paramCache[$slug] = Param::where('slug', $slug)->first();
        }
        return $this->paramCache[$slug];
    }

    private function findOptionByValue(string $paramSlug, string $value): ?ParamOption
    {
        $key = "{$paramSlug}::{$value}";
        if (!array_key_exists($key, $this->optionCache)) {
            $param = $this->getParam($paramSlug);
            $this->optionCache[$key] = $param
                ? ParamOption::where('param_id', $param->id)->where('value', $value)->first()
                : null;
        }
        return $this->optionCache[$key];
    }

    private function findColorOption(string $colorName): ?ParamOption
    {
        $key = "color::{$colorName}";
        if (!array_key_exists($key, $this->optionCache)) {
            $param = $this->getParam('color');
            if (!$param) {
                return $this->optionCache[$key] = null;
            }

            // Цвета лежат как json_encode без JSON_UNESCAPED_UNICODE — кириллица
            // экранирована как Кр.... В SQL LIKE backslash — escape-символ,
            // поэтому переопределяем его на «|», чтобы \u не съедался.
            $needle = trim(json_encode(['name' => $colorName]), '{}');

            $this->optionCache[$key] = ParamOption::where('param_id', $param->id)
                ->whereRaw("value LIKE ? ESCAPE '|'", ['%' . $needle . '%'])
                ->first();
        }
        return $this->optionCache[$key];
    }

    private function resolveBrands(): array
    {
        $defs = [
            'Rudis' => 'Rudis',
            'Wreststore' => 'Wreststore',
            'Adidas' => 'Adidas',
        ];

        $brands = [];
        foreach ($defs as $key => $name) {
            $brands[$key] = Brand::firstOrCreate(
                ['name' => $name],
                ['slug' => Brand::generateUniqueSlug($name)]
            );
        }
        return $brands;
    }

    private function resolveCategories(): array
    {
        $titles = ['Борцовки', 'Шорты ММА', 'Трико'];
        $result = [];
        foreach ($titles as $title) {
            $cat = Category::where('title', $title)->first();
            if (!$cat) {
                throw new \RuntimeException("Категория «{$title}» не найдена — сначала запусти CategorySeeder");
            }
            $result[$title] = $cat;
        }
        return $result;
    }

    private function resolveCountries(): array
    {
        $names = ['Китай', 'США', 'Россия', 'Турция'];
        $result = [];
        foreach ($names as $n) {
            $c = ManufacturingCountry::where('name', $n)->first();
            if ($c) $result[$n] = $c;
        }
        return $result;
    }

    private function resolveOrigins(): array
    {
        $names = ['США', 'Германия', 'Россия'];
        $result = [];
        foreach ($names as $n) {
            $o = BrandOrigin::where('name', $n)->first();
            if ($o) $result[$n] = $o;
        }
        return $result;
    }

    private function resolveProductGroups(): array
    {
        $titles = ['Новинки', 'Хиты продаж', 'Акции', 'Распродажа', 'Эксклюзив'];
        $result = [];
        foreach ($titles as $t) {
            $g = ProductGroup::firstOrCreate(['title' => $t]);
            $result[$t] = $g;
        }
        return $result;
    }

    private function resolveSizeTables(): array
    {
        $names = ['Мужская одежда', 'Обувь мужская', 'Обувь детская'];
        $result = [];
        foreach ($names as $n) {
            $t = SizeTable::where('name', $n)->first();
            if ($t) $result[$n] = $t;
        }
        return $result;
    }

    private function productsDefinition(): array
    {
        return [
            // ── БОРЦОВКИ ─────────────────────────────────────────────────────
            [
                'slug' => 'borcovki-rudis-colt-3.0-teal',
                'size_table' => 'Обувь мужская',
                'title' => 'Борцовки Rudis Colt 3.0 Teal',
                'article' => 'RUDIS-COLT-3-TEAL',
                'description' => 'Профессиональные борцовки Rudis Colt 3.0 в бирюзовом исполнении. Лёгкая подошва, цепкое сцепление с матом, дышащий верх. Идеальный выбор для вольной и греко-римской борьбы.',
                'price' => 18990,
                'old_price' => 21990,
                'weight' => 480,
                'category' => 'Борцовки',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'Китай',
                'group' => 'Новинки',
                'sizes' => ['38', '40', '42', '44', '46'],
                'barcode' => '4607123450012',
                'meta_keywords' => 'борцовки, rudis, colt, бирюзовый, борьба, обувь',
                'bought_with' => [
                    'shorty-rudis-jb-south-beach',
                    'triko-wreststore',
                    'borcovki-rudis-jb-ultralite',
                    'shorty-chernye-wreststore-2.0',
                ],
                'params' => [
                    'color' => 'Бирюзовый',
                    'material' => 'Кожа искусственная',
                    'gender' => 'Унисекс',
                    'season' => 'Всесезонный',
                    'style' => 'Спортивный',
                    'pattern' => 'Однотонный',
                    'composition' => 'Верх: искусственная кожа + сетка. Подошва: вспененная резина.',
                ],
            ],
            [
                'slug' => 'borcovki-rudis-jb-ultralite',
                'size_table' => 'Обувь мужская',
                'title' => 'Борцовки Rudis JB Ultralite',
                'article' => 'RUDIS-JB-ULTRA',
                'description' => 'Сверхлёгкая модель JB Ultralite от Rudis. Минимальный вес, максимальная подвижность стопы. Усиленная пятка и эластичная шнуровка для плотной фиксации.',
                'price' => 21490,
                'old_price' => 24990,
                'weight' => 380,
                'category' => 'Борцовки',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'Китай',
                'group' => 'Хиты продаж',
                'sizes' => ['38', '40', '42', '44', '46'],
                'barcode' => '4607123450029',
                'meta_keywords' => 'борцовки, rudis, ultralite, лёгкие, борьба',
                'bought_with' => [
                    'borcovki-rudis-colt-3.0-teal',
                    'shorty-wreststore-dagestan-black',
                    'triko-wreststore-ambassador',
                    'shorty-adidas',
                ],
                'params' => [
                    'color' => 'Тёмно-серый',
                    'material' => 'Микрофибра',
                    'gender' => 'Унисекс',
                    'season' => 'Всесезонный',
                    'style' => 'Спортивный',
                    'pattern' => 'Однотонный',
                    'composition' => 'Верх: микрофибра + дышащая сетка. Подошва: каучук.',
                ],
            ],
            [
                'slug' => 'borcovki-rudis-jb1-south-beach',
                'size_table' => 'Обувь мужская',
                'title' => 'Борцовки Rudis JB1 South Beach',
                'article' => 'RUDIS-JB1-SB',
                'description' => 'Лимитированная коллаборация JB1 South Beach в яркой розово-бирюзовой расцветке. Профессиональная анатомическая колодка и литая подошва.',
                'price' => 23990,
                'weight' => 420,
                'category' => 'Борцовки',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'США',
                'group' => 'Эксклюзив',
                'sizes' => ['40', '42', '44', '46'],
                'barcode' => '4607123450036',
                'meta_keywords' => 'борцовки, rudis, jb1, south beach, лимитированная',
                'bought_with' => [
                    'shorty-rudis-jb-south-beach',
                    'triko-wreststore-ambassador',
                    'borcovki-rudis-colt-3.0-teal',
                ],
                'params' => [
                    'color' => 'Ярко-розовый',
                    'material' => 'Микрофибра',
                    'gender' => 'Унисекс',
                    'season' => 'Всесезонный',
                    'style' => 'Спортивный',
                    'pattern' => 'Колор-блок',
                    'composition' => 'Микрофибра премиум-класса, литая резиновая подошва.',
                ],
            ],
            [
                'slug' => 'borcovki-rudis-ks-power-infernal-seo-url-2025',
                'size_table' => 'Обувь мужская',
                'title' => 'Борцовки Rudis KS Power Infernal',
                'article' => 'RUDIS-KS-INF',
                'description' => 'KS Power Infernal — топовая модель Rudis в агрессивной красно-чёрной расцветке. Усиленная конструкция, профессиональная подошва, премиальные материалы.',
                'price' => 26990,
                'old_price' => 29990,
                'weight' => 510,
                'category' => 'Борцовки',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'США',
                'group' => 'Хиты продаж',
                'sizes' => ['40', '42', '44', '46'],
                'barcode' => '4607123450043',
                'meta_keywords' => 'борцовки, rudis, ks power, infernal, профессиональные',
                'bought_with' => [
                    'shorty-wreststore-dagestan-black',
                    'triko-wreststore-dagestan-siniy',
                    'borcovki-rudis-jb-ultralite',
                    'shorty-chernye-wreststore-2.0',
                ],
                'params' => [
                    'color' => 'Красный',
                    'material' => 'Кожа натуральная',
                    'gender' => 'Унисекс',
                    'season' => 'Всесезонный',
                    'style' => 'Спортивный',
                    'pattern' => 'Колор-блок',
                    'composition' => 'Натуральная кожа, технологичная подошва Power Grip.',
                ],
            ],
            [
                'slug' => 'borcovki-detskie-rudis-ks-power-youth-whitecamo-white',
                'size_table' => 'Обувь детская',
                'title' => 'Борцовки детские Rudis KS Power Youth White Camo',
                'article' => 'RUDIS-KS-YOUTH-WC',
                'description' => 'Детская версия KS Power Youth в белом камуфляже. Облегчённая колодка под детскую стопу, мягкая шнуровка, гипоаллергенные материалы.',
                'price' => 14990,
                'old_price' => 16990,
                'weight' => 290,
                'category' => 'Борцовки',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'Китай',
                'group' => 'Новинки',
                'sizes' => ['30', '32', '33', '34', '36'],
                'barcode' => '4607123450050',
                'meta_keywords' => 'борцовки, детские, rudis, ks power youth, camo',
                'bought_with' => [
                    'borcovki-rudis-colt-3.0-teal',
                    'borcovki-rudis-jb-ultralite',
                ],
                'params' => [
                    'color' => 'Белый',
                    'material' => 'Микрофибра',
                    'gender' => 'Для мальчиков',
                    'season' => 'Всесезонный',
                    'style' => 'Спортивный',
                    'pattern' => 'Камуфляж',
                    'composition' => 'Микрофибра, гипоаллергенная стелька, мягкая подкладка.',
                ],
            ],

            // ── ШОРТЫ ММА ────────────────────────────────────────────────────
            [
                'slug' => 'shorty-adidas',
                'size_table' => 'Мужская одежда',
                'title' => 'Шорты ММА Adidas Combat',
                'article' => 'ADI-MMA-COMB',
                'description' => 'Базовые тренировочные шорты Adidas для ММА. Эластичный пояс, прочные швы, лёгкий быстросохнущий материал. Свобода движений в партере и стойке.',
                'price' => 5990,
                'old_price' => 7490,
                'weight' => 220,
                'category' => 'Шорты ММА',
                'brand' => 'Adidas',
                'origin' => 'Германия',
                'country' => 'Китай',
                'group' => 'Хиты продаж',
                'sizes' => ['44', '46', '48', '50', '52'],
                'barcode' => '4607123450067',
                'meta_keywords' => 'шорты, adidas, мма, тренировочные',
                'bought_with' => [
                    'borcovki-rudis-colt-3.0-teal',
                    'borcovki-rudis-jb-ultralite',
                    'triko-wreststore',
                ],
                'params' => [
                    'color' => 'Чёрный',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Свободная',
                    'length' => 'До колена',
                    'fastener' => 'Резинка',
                    'style' => 'Спортивный',
                    'pattern' => 'Логотип',
                    'composition' => '92% полиэстер, 8% эластан.',
                ],
            ],
            [
                'slug' => 'shorty-chernye-wreststore-2.0',
                'size_table' => 'Мужская одежда',
                'title' => 'Шорты ММА Wreststore 2.0 Black',
                'article' => 'WS-MMA-2-BLK',
                'description' => 'Обновлённая модель шорт Wreststore 2.0 в классическом чёрном цвете. Усиленные швы, разрезы по бокам, прорезиненная вставка против сползания.',
                'price' => 4490,
                'weight' => 200,
                'category' => 'Шорты ММА',
                'brand' => 'Wreststore',
                'origin' => 'Россия',
                'country' => 'Россия',
                'group' => 'Новинки',
                'sizes' => ['44', '46', '48', '50', '52', '54'],
                'barcode' => '4607123450074',
                'meta_keywords' => 'шорты, wreststore, мма, чёрные',
                'bought_with' => [
                    'triko-wreststore',
                    'borcovki-rudis-colt-3.0-teal',
                    'shorty-wreststore-dagestan-black',
                ],
                'params' => [
                    'color' => 'Чёрный',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Свободная',
                    'length' => 'До колена',
                    'fastener' => 'Шнуровка',
                    'style' => 'Спортивный',
                    'pattern' => 'Однотонный',
                    'composition' => '100% полиэстер, прорезиненная вставка.',
                ],
            ],
            [
                'slug' => 'shorty-rudis-jb-south-beach',
                'size_table' => 'Мужская одежда',
                'title' => 'Шорты ММА Rudis JB South Beach',
                'article' => 'RUDIS-JB-SHRT-SB',
                'description' => 'Шорты Rudis JB в коллекции South Beach. Лёгкий стрейч-материал, яркий принт, удобная посадка. Идеально сочетаются с борцовками той же линейки.',
                'price' => 7990,
                'old_price' => 9490,
                'weight' => 180,
                'category' => 'Шорты ММА',
                'brand' => 'Rudis',
                'origin' => 'США',
                'country' => 'Китай',
                'group' => 'Эксклюзив',
                'sizes' => ['46', '48', '50', '52'],
                'barcode' => '4607123450081',
                'meta_keywords' => 'шорты, rudis, south beach, мма',
                'bought_with' => [
                    'borcovki-rudis-jb1-south-beach',
                    'borcovki-rudis-colt-3.0-teal',
                    'triko-wreststore-ambassador',
                ],
                'params' => [
                    'color' => 'Ярко-розовый',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Лето',
                    'fit-type' => 'Прямая',
                    'length' => 'До колена',
                    'fastener' => 'Шнуровка',
                    'style' => 'Спортивный',
                    'pattern' => 'Колор-блок',
                    'composition' => '88% полиэстер, 12% эластан.',
                ],
            ],
            [
                'slug' => 'shorty-wreststore-dagestan-black',
                'size_table' => 'Мужская одежда',
                'title' => 'Шорты ММА Wreststore Dagestan Black',
                'article' => 'WS-MMA-DAG-BLK',
                'description' => 'Лимитированная серия Dagestan в чёрном цвете с фирменным шевроном Wreststore. Премиальная отделка, усиленная промежность, держит форму после стирок.',
                'price' => 5990,
                'old_price' => 6990,
                'weight' => 210,
                'category' => 'Шорты ММА',
                'brand' => 'Wreststore',
                'origin' => 'Россия',
                'country' => 'Россия',
                'group' => 'Эксклюзив',
                'sizes' => ['46', '48', '50', '52'],
                'barcode' => '4607123450098',
                'meta_keywords' => 'шорты, wreststore, dagestan, лимитированная',
                'bought_with' => [
                    'triko-wreststore-dagestan-siniy',
                    'borcovki-rudis-ks-power-infernal-seo-url-2025',
                    'borcovki-rudis-jb-ultralite',
                    'triko-wreststore',
                ],
                'params' => [
                    'color' => 'Чёрный',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Прямая',
                    'length' => 'До колена',
                    'fastener' => 'Шнуровка',
                    'style' => 'Спортивный',
                    'pattern' => 'Логотип',
                    'composition' => '100% полиэстер с прорезиненными вставками.',
                ],
            ],

            // ── ТРИКО ────────────────────────────────────────────────────────
            [
                'slug' => 'triko-wreststore',
                'size_table' => 'Мужская одежда',
                'title' => 'Трико Wreststore Classic',
                'article' => 'WS-TRIKO-CLS',
                'description' => 'Классическое трико Wreststore из плотного эластичного материала. Анатомический крой, не сковывает движений в схватке. Базовая модель для тренировок и соревнований.',
                'price' => 7490,
                'weight' => 320,
                'category' => 'Трико',
                'brand' => 'Wreststore',
                'origin' => 'Россия',
                'country' => 'Россия',
                'group' => 'Хиты продаж',
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'barcode' => '4607123450104',
                'meta_keywords' => 'трико, wreststore, борьба, классическое',
                'bought_with' => [
                    'borcovki-rudis-colt-3.0-teal',
                    'shorty-chernye-wreststore-2.0',
                    'borcovki-rudis-jb-ultralite',
                ],
                'params' => [
                    'color' => 'Чёрный',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Облегающая',
                    'sleeve-type' => 'Без рукавов',
                    'neckline-type' => 'Круглый вырез',
                    'style' => 'Спортивный',
                    'pattern' => 'Однотонный',
                    'composition' => '82% полиэстер, 18% эластан.',
                ],
            ],
            [
                'slug' => 'triko-wreststore-ambassador',
                'size_table' => 'Мужская одежда',
                'title' => 'Трико Wreststore Ambassador',
                'article' => 'WS-TRIKO-AMB',
                'description' => 'Премиальная модель Ambassador — для амбассадоров бренда. Эластичная двухслойная ткань, цельнокроеная конструкция без боковых швов, держит форму и не сползает.',
                'price' => 11990,
                'old_price' => 13990,
                'weight' => 340,
                'category' => 'Трико',
                'brand' => 'Wreststore',
                'origin' => 'Россия',
                'country' => 'Россия',
                'group' => 'Эксклюзив',
                'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
                'barcode' => '4607123450111',
                'meta_keywords' => 'трико, wreststore, ambassador, премиум',
                'bought_with' => [
                    'borcovki-rudis-ks-power-infernal-seo-url-2025',
                    'borcovki-rudis-jb1-south-beach',
                    'shorty-rudis-jb-south-beach',
                    'shorty-wreststore-dagestan-black',
                ],
                'params' => [
                    'color' => 'Чёрный',
                    'material' => 'Эластан',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Облегающая',
                    'sleeve-type' => 'Без рукавов',
                    'neckline-type' => 'Круглый вырез',
                    'style' => 'Спортивный',
                    'pattern' => 'Логотип',
                    'composition' => '78% полиэстер, 22% эластан, двухслойная вязка.',
                ],
            ],
            [
                'slug' => 'triko-wreststore-dagestan-siniy',
                'size_table' => 'Мужская одежда',
                'title' => 'Трико Wreststore Dagestan Синее',
                'article' => 'WS-TRIKO-DAG-BLU',
                'description' => 'Трико из лимитированной серии Dagestan в глубоком синем цвете с фирменным шевроном. Лёгкая, прочная, отлично тянется и быстро сохнет.',
                'price' => 8990,
                'old_price' => 10490,
                'weight' => 320,
                'category' => 'Трико',
                'brand' => 'Wreststore',
                'origin' => 'Россия',
                'country' => 'Россия',
                'group' => 'Акции',
                'sizes' => ['S', 'M', 'L', 'XL'],
                'barcode' => '4607123450128',
                'meta_keywords' => 'трико, wreststore, dagestan, синее',
                'bought_with' => [
                    'shorty-wreststore-dagestan-black',
                    'borcovki-rudis-ks-power-infernal-seo-url-2025',
                    'shorty-chernye-wreststore-2.0',
                ],
                'params' => [
                    'color' => 'Тёмно-синий',
                    'material' => 'Полиэстер',
                    'gender' => 'Мужской',
                    'season' => 'Всесезонный',
                    'fit-type' => 'Облегающая',
                    'sleeve-type' => 'Без рукавов',
                    'neckline-type' => 'Круглый вырез',
                    'style' => 'Спортивный',
                    'pattern' => 'Логотип',
                    'composition' => '85% полиэстер, 15% эластан.',
                ],
            ],
        ];
    }
}