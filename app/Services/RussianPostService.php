<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Тонкая обёртка для Почты России. Реальный API (Otpravka) требует
 * юр-аккаунт. Если креды не заданы — возвращаем мок-данные с
 * реалистичными отделениями нескольких крупных городов.
 */
class RussianPostService
{
    private string $baseUrl;
    private ?string $token;
    private ?string $login;
    private ?string $password;

    public function __construct()
    {
        $cfg = config('services.russian_post');
        $this->baseUrl  = rtrim($cfg['base_url'], '/');
        $this->token    = $cfg['token']    ?? null;
        $this->login    = $cfg['login']    ?? null;
        $this->password = $cfg['password'] ?? null;
    }

    /**
     * Поиск городов с отделениями Почты. Возвращает [{code, city, region, full}, ...].
     * code — здесь это произвольный slug; при моке = lowercase city.
     */
    public function searchCities(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'rp:cities:' . mb_strtolower($query);
        return Cache::remember($cacheKey, 60 * 60, function () use ($query) {
            if (!$this->isConfigured()) {
                return $this->mockCities($query);
            }
            try {
                // Otpravka не имеет публичного метода поиска городов с отделениями —
                // лучше отдать мок-данные, чем дёргать частный API.
                return $this->mockCities($query);
            } catch (\Throwable $e) {
                report($e);
                return $this->mockCities($query);
            }
        });
    }

    /**
     * Отделения Почты России в городе.
     */
    public function pickupPoints(string $cityCode): array
    {
        $cityCode = mb_strtolower(trim($cityCode));
        if ($cityCode === '') {
            return [];
        }

        $cacheKey = "rp:offices:{$cityCode}";
        return Cache::remember($cacheKey, 60 * 60 * 6, function () use ($cityCode) {
            return $this->mockPickupPoints($cityCode);
        });
    }

    private function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->login) && !empty($this->password);
    }

    // ───────────── Mock fallback ─────────────

    private function mockCities(string $query): array
    {
        $all = [
            ['code' => 'moskva',           'city' => 'Москва',          'region' => 'г. Москва'],
            ['code' => 'sankt-peterburg',  'city' => 'Санкт-Петербург', 'region' => 'г. Санкт-Петербург'],
            ['code' => 'mahachkala',       'city' => 'Махачкала',       'region' => 'Дагестан'],
            ['code' => 'kaspiysk',         'city' => 'Каспийск',        'region' => 'Дагестан'],
            ['code' => 'derbent',          'city' => 'Дербент',         'region' => 'Дагестан'],
            ['code' => 'novosibirsk',      'city' => 'Новосибирск',     'region' => 'Новосибирская обл.'],
            ['code' => 'krasnodar',        'city' => 'Краснодар',       'region' => 'Краснодарский край'],
            ['code' => 'kazan',            'city' => 'Казань',          'region' => 'Татарстан'],
            ['code' => 'nizhniy-novgorod', 'city' => 'Нижний Новгород', 'region' => 'Нижегородская обл.'],
            ['code' => 'ekaterinburg',     'city' => 'Екатеринбург',    'region' => 'Свердловская обл.'],
            ['code' => 'samara',           'city' => 'Самара',          'region' => 'Самарская обл.'],
        ];

        $q = mb_strtolower($query);
        return collect($all)
            ->filter(fn ($c) => str_contains(mb_strtolower($c['city']), $q))
            ->map(fn ($c) => array_merge($c, [
                'full' => $c['city'] . ', ' . $c['region'],
            ]))
            ->values()
            ->all();
    }

    private function mockPickupPoints(string $cityCode): array
    {
        $points = [
            'moskva' => [
                ['index' => '101000', 'name' => 'Москва 101000',  'address' => 'Мясницкая ул., 26', 'lat' => 55.7616, 'lon' => 37.6371],
                ['index' => '109012', 'name' => 'Москва 109012',  'address' => 'ул. Никольская, 10', 'lat' => 55.7575, 'lon' => 37.6238],
                ['index' => '117420', 'name' => 'Москва 117420',  'address' => 'ул. Профсоюзная, 56', 'lat' => 55.6783, 'lon' => 37.5419],
            ],
            'sankt-peterburg' => [
                ['index' => '190000', 'name' => 'Санкт-Петербург 190000', 'address' => 'Почтамтская ул., 9', 'lat' => 59.9332, 'lon' => 30.3066],
                ['index' => '191040', 'name' => 'Санкт-Петербург 191040', 'address' => 'Лиговский пр-кт, 50', 'lat' => 59.9263, 'lon' => 30.3613],
            ],
            'mahachkala' => [
                ['index' => '367000', 'name' => 'Махачкала 367000', 'address' => 'пр. Имама Шамиля, 14', 'lat' => 42.9826, 'lon' => 47.5047],
                ['index' => '367010', 'name' => 'Махачкала 367010', 'address' => 'ул. Гагарина, 5', 'lat' => 42.9892, 'lon' => 47.5165],
                ['index' => '367015', 'name' => 'Махачкала 367015', 'address' => 'ул. Ярагского, 71', 'lat' => 42.9748, 'lon' => 47.4872],
            ],
            'kaspiysk' => [
                ['index' => '368300', 'name' => 'Каспийск 368300', 'address' => 'ул. Ленина, 12', 'lat' => 42.8795, 'lon' => 47.6252],
            ],
            'derbent' => [
                ['index' => '368600', 'name' => 'Дербент 368600', 'address' => 'ул. Гагарина, 32', 'lat' => 42.0582, 'lon' => 48.2901],
            ],
        ];

        $list = $points[$cityCode] ?? [];

        return array_map(fn ($p) => array_merge($p, [
            'address_full' => $p['address'],
            'work_time'    => 'Пн-Пт 09:00–20:00, Сб 09:00–17:00, Вс выходной',
            'phone'        => '8-800-2005-888',
            'note'         => 'Демо-данные. Реальный API Почты России требует юр-аккаунт.',
        ]), $list);
    }
}