<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CaptchaService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Генерирует капчу с случайным числом
     *
     * @return array ['key' => string, 'image' => base64]
     */
    public function generate(): array
    {
        // Генерируем простое 4-значное число
        $code = rand(1000, 9999);

        // Генерируем уникальный ключ
        $key = Str::random(32);

        // Сохраняем ответ в кэше на 5 минут
        Cache::put("captcha:{$key}", $code, now()->addMinutes(5));

        // Создаем изображение
        $image = $this->createImage((string) $code);

        return [
            'key' => $key,
            'image' => $image,
        ];
    }

    /**
     * Проверяет ответ капчи
     *
     * @param string $key
     * @param string $answer
     * @return bool
     */
    public function verify(string $key, string $answer): bool
    {
        $cached = Cache::get("captcha:{$key}");

        if ($cached === null) {
            return false;
        }

        // Удаляем использованную капчу
        Cache::forget("captcha:{$key}");

        return (string) $cached === (string) $answer;
    }

    /**
     * Создает изображение капчи
     *
     * @param string $text
     * @return string base64
     */
    protected function createImage(string $text): string
    {
        $width = 200;
        $height = 80;

        // Создаем изображение через GD
        $img = imagecreatetruecolor($width, $height);

        // Цвета
        $bgColor = imagecolorallocate($img, 255, 255, 255); // белый
        $textColor = imagecolorallocate($img, 17, 17, 17); // почти черный
        $noiseColor = imagecolorallocate($img, 229, 231, 235); // светло-серый

        // Заливаем фон
        imagefilledrectangle($img, 0, 0, $width, $height, $bgColor);

        // Добавляем легкий шум (линии)
        for ($i = 0; $i < 3; $i++) {
            imageline($img, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $noiseColor);
        }

        // Рисуем каждую цифру крупно
        $chars = str_split($text);
        $charWidth = 40;
        $startX = ($width - (count($chars) * $charWidth)) / 2;

        foreach ($chars as $i => $char) {
            $x = $startX + ($i * $charWidth) + rand(-2, 2); // небольшое смещение
            $y = 28 + rand(-2, 2);

            // Рисуем символ 2 раза для умеренной толщины
            for ($dx = 0; $dx < 2; $dx++) {
                for ($dy = 0; $dy < 2; $dy++) {
                    imagestring($img, 5, $x + $dx, $y + $dy, $char, $textColor);
                }
            }
        }

        // Добавляем точки (шум)
        for ($i = 0; $i < 40; $i++) {
            imagesetpixel($img, rand(0, $width), rand(0, $height), $noiseColor);
        }

        // Конвертируем в PNG
        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
