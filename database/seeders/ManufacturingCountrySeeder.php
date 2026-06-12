<?php

namespace Database\Seeders;

use App\Models\ManufacturingCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManufacturingCountrySeeder extends Seeder
{
    public function run(): void
    {
        $flagsDir = __DIR__ . '/flags/';

        $flags = [
            'Россия'         => 'images (1).png',
            'США'            => '1280px-Flag_of_the_United_States_(Pantone).svg.png',
            'Германия'       => 'Flag_of_Germany.svg',
            'Италия'         => 'Flag_of_Italy.svg',
            'Великобритания' => 'Flag_of_the_United_Kingdom_(3-5).svg.png',
            'Испания'        => 'Flag_of_Spain.svg',
            'Китай'          => "Flag_of_the_People's_Republic_of_China.svg",
            'Турция'         => 'Flag_of_Turkey.svg',
            'Бразилия'       => 'Flag_of_Brazil.svg',
            'Бангладеш'      => 'images.png',
            'Вьетнам'        => 'Flag_of_Vietnam.svg.webp',
            'Индия'          => 'Flag_of_India.svg.png',
            'Индонезия'      => 'Flag_of_Indonesia.svg',
            'Египет'         => 'Flag_of_Egypt.svg.png',
            'Болгария'       => 'Flag_of_Bulgaria.svg.webp',
            'Венгрия'        => 'Flag_of_Hungary.svg.webp',
            'Беларусь'       => 'Flag_of_Belarus.svg',
            'Казахстан'      => 'Flag_of_Kazakhstan.svg',
            'Армения'        => 'Flag_of_Armenia (1).svg',
            'Грузия'         => 'Flag_of_Georgia.svg.webp',
            'Палестина'      => 'Flag_of_Palestine.svg',
            'Судан'          => 'Flag_of_Sudan.svg',
        ];

        foreach ($flags as $name => $file) {
            $sourcePath = $flagsDir . $file;
            if (!file_exists($sourcePath)) {
                $this->command?->warn("Manufacturing country flag not found: {$file}");
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $destPath = 'flags/manufacturing-countries/' . Str::random(40) . '.' . $ext;
            Storage::disk('public')->put($destPath, file_get_contents($sourcePath));

            $existing = ManufacturingCountry::where('name', $name)->first();
            if ($existing) {
                if ($existing->flag_url) {
                    Storage::disk('public')->delete($existing->flag_url);
                }
                $existing->update(['flag_url' => $destPath]);
            } else {
                ManufacturingCountry::create([
                    'name'     => $name,
                    'slug'     => ManufacturingCountry::generateUniqueSlug($name),
                    'flag_url' => $destPath,
                ]);
            }
        }
    }
}