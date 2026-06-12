<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brandsDir = __DIR__ . '/brands/';

        $brands = [
            'Nike'         => 'Logo_NIKE.svg',
            'Adidas'       => 'Logo_brand_Adidas.png',
            'Puma'         => 'Puma_logo.svg',
            'Reebok'       => 'Reebok_logo20.png',
            'Fila'         => 'Fila_logo.svg.png',
            'Under Armour' => 'Under_armour_logo.svg',
            'Calvin Klein' => 'CK_Calvin_Klein_logo.svg',
            'Hugo Boss'    => 'Hugo-Boss-Logo.svg.png',
            'Geox'         => 'Geox_-_logo_(Italy,_2003-).svg.png',
            'Premiata'     => 'premiata-brandshop.svg',
        ];

        foreach ($brands as $name => $logo) {
            $sourcePath = $brandsDir . $logo;
            if (!file_exists($sourcePath)) {
                $this->command?->warn("Brand logo not found: {$logo}");
                continue;
            }

            $ext = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'svg'         => 'image/svg+xml',
                'png'         => 'image/png',
                'webp'        => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                default       => 'application/octet-stream',
            };

            $destPath = 'brands/' . Str::uuid() . '.' . $ext;
            Storage::disk('s3')->put($destPath, file_get_contents($sourcePath), [
                'visibility'   => 'public',
                'ContentType'  => $mime,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            $existing = Brand::where('name', $name)->first();
            if ($existing) {
                if ($existing->image) {
                    ImageService::delete($existing->image);
                }
                $existing->update(['image' => $destPath]);
            } else {
                Brand::create([
                    'name'  => $name,
                    'slug'  => Brand::generateUniqueSlug($name),
                    'image' => $destPath,
                ]);
            }
        }
    }
}