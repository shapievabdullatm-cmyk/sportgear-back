<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocoderController extends Controller
{
    private function http()
    {
        return Http::withHeaders([
            'Referer' => env('YANDEX_REFERER', 'http://localhost:3000'),
        ])->timeout(5);
    }

    private function geocoderRequest(string $query, int $results = 1): array
    {
        $response = $this->http()->get('https://geocode-maps.yandex.ru/1.x/', [
            'apikey'  => env('YANDEX_GEOCODER_KEY'),
            'geocode' => $query,
            'format'  => 'json',
            'results' => $results,
            'lang'    => 'ru_RU',
        ]);

        if (!$response->successful()) return [];

        return $response->json('response.GeoObjectCollection.featureMember', []);
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);

        $members = $this->geocoderRequest($q, 7);

        return response()->json(
            collect($members)->map(function ($m) {
                $meta = $m['GeoObject']['metaDataProperty']['GeocoderMetaData'];
                $text = $meta['text'];

                // Разбиваем на отображаемое название и подзаголовок
                $parts   = explode(', ', $text);
                $display = implode(', ', array_slice($parts, -3)); // последние 3 части
                $sub     = implode(', ', array_slice($parts, 0, -3));
                $sub     = preg_replace('/^Россия,\s*/i', '', $sub);

                return [
                    'display'  => $display,
                    'subtitle' => $sub,
                    'value'    => $text,
                ];
            })->values()
        );
    }

    public function geocode(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));
        if (!$q) return response()->json(null, 422);

        $members = $this->geocoderRequest($q, 1);
        if (empty($members)) return response()->json(null, 404);

        $geo   = $members[0]['GeoObject'];
        $meta  = $geo['metaDataProperty']['GeocoderMetaData'];
        $pos   = explode(' ', $geo['Point']['pos']); // lon lat
        $comps = collect($meta['Address']['Components'] ?? []);

        return response()->json([
            'full_address' => $meta['text'],
            'lat'          => (float) $pos[1],
            'lon'          => (float) $pos[0],
            'city'         => $comps->firstWhere('kind', 'locality')['name'] ?? null,
            'street'       => $comps->firstWhere('kind', 'street')['name'] ?? null,
            'house'        => $comps->firstWhere('kind', 'house')['name'] ?? null,
        ]);
    }
}