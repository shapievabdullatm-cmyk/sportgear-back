<?php

namespace Database\Seeders;

use App\Services\SliderService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class SliderSeeder extends Seeder
{
    public function run(): void
    {
        // Массив с данными для каждого слайда
        $slides = [
            [
                'file'  => 'slider-1.png',
                'title' => 'Новая коллекция ветровок',
                'link'   => '/catalog/windbreakers',
            ],
            [
                'file'  => 'slider-2.png',
                'title' => 'Лимитированная серия Rage',
                'link'   => '/collections/rage',
            ],
            [
                'file'  => 'slider-3.png',
                'title' => 'Летняя распродажа -30%',
                'link'   => '/sale',
            ],
        ];

        foreach ($slides as $index => $slide) {
            $localPath = database_path("seeders/sliders/{$slide['file']}");

            if (!File::exists($localPath)) {
                $this->command->warn("Файл {$slide['file']} не найден, пропускаем.");
                continue;
            }

            // Создаем объект файла для обработки ImageService
            $file = new UploadedFile(
                $localPath,
                $slide['file'],
                File::mimeType($localPath),
                null,
                true
            );

            // Формируем полный набор данных для SliderService
            $data = [
                'title'    => $slide['title'],
                'link'      => $slide['link'],
                'position' => $index, // Автоматическая позиция
                'image'    => $file,
            ];

            // Вызываем ваш сервис. Он обработает фото, загрузит в S3 и создаст запись в БД
            SliderService::store($data);

            $this->command->info("Слайд '{$slide['title']}' успешно создан.");
        }
    }
}
