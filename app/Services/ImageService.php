<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

/**
 * Сервис для работы с изображениями в S3.
 */
class ImageService
{
    // Разрешенные типы (для дополнительной проверки)
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Основной метод загрузки.
     * Именно здесь формируется путь $folder/uuid.webp
     */
    protected static function upload(UploadedFile $file, string $folder, int $width, int $height): string
    {
        // 1. Инициализируем менеджер (Intervention Image v3)
        $manager = new ImageManager(new Driver());

        // 2. Читаем файл, обрезаем под размеры и конвертируем в WebP
        $webpData = $manager
            ->read($file->getRealPath())
            ->cover($width, $height) // Обрезка по центру
            ->toWebp(85)             // Качество 85%
            ->toString();

        // 3. Формируем путь. Если $folder = 'sliders', путь будет 'sliders/имя.webp'
        $path = $folder . '/' . Str::uuid() . '.webp';

        // 4. Кладём в S3 бакет
        Storage::disk('s3')->put($path, $webpData, [
            'visibility'   => 'public',
            'ContentType'  => 'image/webp',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $path;
    }

    /**
     * Метод специально для слайдеров (1000x500 в папку sliders)
     */
    public static function uploadSliderImage(UploadedFile $file): string
    {
        return self::upload($file, 'sliders', 1000, 500);
    }

    /**
     * Мобильное изображение слайдера (500x500 в папку sliders/mobile)
     */
    public static function uploadSliderMobileImage(UploadedFile $file): string
    {
        return self::upload($file, 'sliders/mobile', 500, 500);
    }

    /**
     * Метод для категорий (600x900 в папку categories)
     */
    public static function uploadCategoryImage(UploadedFile $file): string
    {
        return self::upload($file, 'categories', 600, 900);
    }

    /**
     * Метод для брендов (400x200 в папку brands)
     */
    public static function uploadBrandImage(UploadedFile $file): string
    {
        return self::upload($file, 'brands', 400, 200);
    }

    /**
     * Удаление файла из S3
     */
    public static function delete(?string $path): void
    {
        if ($path && Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }

    /**
     * Получение полного URL из S3
     */
    public static function url(?string $path): ?string
    {
        if (!$path) return null;
        return Storage::disk('s3')->url($path);
    }

    public static function uploadProductImage(UploadedFile $file, int $productId): string
    {
        return self::upload($file, 'products/' . $productId, 600, 900);
    }

    /**
     * Метод для баннеров блогов (1200x600 в папку blogs)
     */
    public static function uploadBlogBanner(UploadedFile $file): string
    {
        return self::upload($file, 'blogs', 1200, 600);
    }

    /**
     * Метод для изображений в контенте блога — без обрезки, сохраняет пропорции.
     * Уменьшает только если ширина > 1600px, маленькие оставляет как есть.
     */
    public static function uploadBlogContentImage(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());

        $webpData = $manager
            ->read($file->getRealPath())
            ->scaleDown(width: 1600)
            ->toWebp(85)
            ->toString();

        $path = 'blogs/content/' . Str::uuid() . '.webp';

        Storage::disk('s3')->put($path, $webpData, [
            'visibility'   => 'public',
            'ContentType'  => 'image/webp',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        return $path;
    }

    /**
     * Загрузка и конвертация видео для блога.
     * H.264 + AAC + MP4 + faststart — нативно играется во всех браузерах
     * и конвертируется в 5-10× быстрее, чем VP9/WebM.
     */
    public static function uploadBlogVideo(UploadedFile $file): string
    {
        $tempInput = $file->getRealPath();
        $tempOutput = sys_get_temp_dir() . '/' . Str::uuid() . '.mp4';

        try {
            $command = sprintf(
                'ffmpeg -i %s -c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 128k -movflags +faststart -y %s 2>&1',
                escapeshellarg($tempInput),
                escapeshellarg($tempOutput)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($tempOutput)) {
                throw new \Exception('FFmpeg conversion failed: ' . implode("\n", array_slice($output, -20)));
            }

            $path = 'blogs/videos/' . Str::uuid() . '.mp4';

            Storage::disk('s3')->put($path, file_get_contents($tempOutput), [
                'visibility'   => 'public',
                'ContentType'  => 'video/mp4',
                'ContentDisposition' => 'inline',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            return $path;
        } finally {
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }
    }

    /**
     * Копирование изображения товара из одного товара в другой
     */
    public static function copyProductImage(string $sourcePath, int $targetProductId): ?string
    {
        try {
            // Проверяем, существует ли исходный файл
            if (!Storage::disk('s3')->exists($sourcePath)) {
                return null;
            }

            // Получаем содержимое файла
            $fileContent = Storage::disk('s3')->get($sourcePath);

            // Формируем новый путь для целевого товара
            $newPath = 'products/' . $targetProductId . '/' . Str::uuid() . '.webp';

            // Копируем файл в новое место
            Storage::disk('s3')->put($newPath, $fileContent, [
                'visibility'   => 'public',
                'ContentType'  => 'image/webp',
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);

            return $newPath;
        } catch (\Exception $e) {
            \Log::error('Failed to copy product image: ' . $e->getMessage());
            return null;
        }
    }
}
