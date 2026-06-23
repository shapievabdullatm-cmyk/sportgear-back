<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductCollectionItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCollectionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $collections = [
            [
                'name' => 'Экипировка для борьбы',
                'position' => 10,
                'product_slugs' => [
                    'borcovki-rudis-ks-power-infernal-seo-url-2025',
                    'borcovki-rudis-jb-ultralite',
                    'borcovki-rudis-colt-3.0-teal',
                    'triko-wreststore-ambassador',
                    'triko-wreststore',
                    'triko-wreststore-dagestan-siniy',
                ],
            ],
            [
                'name' => 'Bestseller ММА',
                'position' => 20,
                'product_slugs' => [
                    'shorty-adidas',
                    'shorty-chernye-wreststore-2.0',
                    'shorty-wreststore-dagestan-black',
                    'shorty-rudis-jb-south-beach',
                    'borcovki-rudis-jb-ultralite',
                ],
            ],
            [
                'name' => 'Лимитированная серия',
                'position' => 30,
                'product_slugs' => [
                    'borcovki-rudis-jb1-south-beach',
                    'shorty-rudis-jb-south-beach',
                    'shorty-wreststore-dagestan-black',
                    'triko-wreststore-ambassador',
                    'triko-wreststore-dagestan-siniy',
                ],
            ],
        ];

        foreach ($collections as $def) {
            $collection = ProductCollection::updateOrCreate(
                ['name' => $def['name']],
                [
                    'is_active' => true,
                    'position' => $def['position'],
                ]
            );

            ProductCollectionItem::where('collection_id', $collection->id)->delete();

            foreach ($def['product_slugs'] as $i => $slug) {
                $product = Product::where('slug', $slug)->first();
                if (!$product) {
                    $this->command?->warn("Товар не найден: {$slug}");
                    continue;
                }

                ProductCollectionItem::create([
                    'collection_id' => $collection->id,
                    'product_id' => $product->id,
                    'position' => $i,
                ]);
            }

            echo "✅ Коллекция «{$collection->name}» создана\n";
        }
    }
}