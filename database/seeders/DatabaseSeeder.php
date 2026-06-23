<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(QuickLinkSeeder::class);

        // Параметры и категории
        $this->call(ParamSeeder::class);
        $this->call(ParamOptionSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(PopularCategorySeeder::class);
        $this->call(ProductGroupSeeder::class);
        $this->call(SliderSeeder::class);
        $this->call(BlogSeeder::class);
        $this->call(BrandOriginSeeder::class);
        $this->call(ManufacturingCountrySeeder::class);
        $this->call(SizeTableSeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(WarehouseSeeder::class);
        $this->call(ShopSeeder::class);
        $this->call(AddressSeeder::class);

        // Продукты и коллекции
        $this->call(ProductSeeder::class);
        $this->call(ProductCollectionSeeder::class);
    }
}