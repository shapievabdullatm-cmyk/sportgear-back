<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LinkPreviewController extends Controller
{
    /**
     * Получение метаданных ссылки (Open Graph)
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->input('url');

        try {
            // Получаем HTML страницы
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LinkPreviewBot/1.0)',
                ])
                ->get($url);

            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Не удалось загрузить страницу'
                ], 400);
            }

            $html = $response->body();

            // Парсим метаданные
            $metadata = $this->parseMetadata($html, $url);

            return response()->json($metadata);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ошибка при загрузке ссылки: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Парсинг метаданных из HTML
     */
    private function parseMetadata(string $html, string $url): array
    {
        $metadata = [
            'url' => $url,
            'title' => null,
            'description' => null,
            'image' => null,
            'domain' => parse_url($url, PHP_URL_HOST),
        ];

        // Извлекаем Open Graph теги
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['image'] = $this->normalizeUrl($matches[1], $url);
        }

        // Если OG тегов нет, пробуем обычные meta теги
        if (!$metadata['title']) {
            if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
                $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if (!$metadata['description']) {
            if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
                $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Обрезаем длинные строки
        if ($metadata['title']) {
            $metadata['title'] = mb_substr($metadata['title'], 0, 200);
        }

        if ($metadata['description']) {
            $metadata['description'] = mb_substr($metadata['description'], 0, 300);
        }

        return $metadata;
    }

    /**
     * Нормализация относительных URL
     */
    private function normalizeUrl(string $imageUrl, string $baseUrl): string
    {
        // Если URL уже абсолютный
        if (preg_match('/^https?:\/\//i', $imageUrl)) {
            return $imageUrl;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        // Если URL начинается с //
        if (str_starts_with($imageUrl, '//')) {
            return $scheme . ':' . $imageUrl;
        }

        // Если URL начинается с /
        if (str_starts_with($imageUrl, '/')) {
            return $scheme . '://' . $host . $imageUrl;
        }

        // Относительный URL
        $path = $parsedBase['path'] ?? '/';
        $path = dirname($path);
        return $scheme . '://' . $host . $path . '/' . $imageUrl;
    }
}