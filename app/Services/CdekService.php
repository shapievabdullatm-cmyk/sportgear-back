<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CdekService
{
    private string $baseUrl;
    private string $account;
    private string $secret;

    public function __construct()
    {
        $cfg = config('services.cdek');
        $this->baseUrl = rtrim($cfg['base_url'], '/');
        $this->account = (string) $cfg['account'];
        $this->secret  = (string) $cfg['secret'];
    }

    /**
     * Поиск городов CDEK по подстроке. Возвращает [{code, city, region, country_code, full}, ...].
     */
    public function searchCities(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $cacheKey = 'cdek:cities:' . mb_strtolower($query);
        return Cache::remember($cacheKey, 60 * 60, function () use ($query) {
            try {
                $resp = Http::withToken($this->token())
                    ->acceptJson()
                    ->timeout(10)
                    ->get($this->baseUrl . '/location/cities', [
                        'country_codes' => 'RU',
                        'size'          => 10,
                        'city'          => $query,
                    ]);

                if (!$resp->ok()) {
                    return $this->mockCities($query);
                }

                $items = collect($resp->json())->map(fn ($c) => [
                    'code'         => (int) ($c['code'] ?? 0),
                    'city'         => (string) ($c['city'] ?? ''),
                    'region'       => (string) ($c['region'] ?? ''),
                    'country_code' => (string) ($c['country_code'] ?? ''),
                    'full'         => trim(($c['city'] ?? '') . ', ' . ($c['region'] ?? '')),
                ])->filter(fn ($c) => $c['code'] > 0)->values()->all();

                return $items ?: $this->mockCities($query);
            } catch (\Throwable $e) {
                report($e);
                return $this->mockCities($query);
            }
        });
    }

    /**
     * Список ПВЗ в городе.
     */
    public function pickupPoints(int $cityCode): array
    {
        $cacheKey = "cdek:pvz:{$cityCode}";
        return Cache::remember($cacheKey, 60 * 60 * 6, function () use ($cityCode) {
            try {
                $resp = Http::withToken($this->token())
                    ->acceptJson()
                    ->timeout(15)
                    ->get($this->baseUrl . '/deliverypoints', [
                        'city_code'    => $cityCode,
                        'country_code' => 'RU',
                        'type'         => 'PVZ',
                    ]);

                if (!$resp->ok()) {
                    return $this->mockPickupPoints($cityCode);
                }

                $items = collect($resp->json())->map(function ($p) {
                    $loc = $p['location'] ?? [];
                    return [
                        'code'        => (string) ($p['code'] ?? ''),
                        'name'        => (string) ($p['name'] ?? ''),
                        'address'     => trim((string) ($loc['address'] ?? '')),
                        'address_full'=> trim((string) ($loc['address_full'] ?? ($loc['address'] ?? ''))),
                        'city'        => (string) ($loc['city'] ?? ''),
                        'lat'         => (float) ($loc['latitude'] ?? 0),
                        'lon'         => (float) ($loc['longitude'] ?? 0),
                        'work_time'   => (string) ($p['work_time'] ?? ''),
                        'phones'      => array_map(fn ($ph) => (string) ($ph['number'] ?? ''), $p['phones'] ?? []),
                        'note'        => (string) ($p['note'] ?? ''),
                        'type'        => (string) ($p['type'] ?? ''),
                        'is_dressing_room' => (bool) ($p['is_dressing_room'] ?? false),
                        'have_cash'        => (bool) ($p['have_cash'] ?? false),
                        'have_cashless'    => (bool) ($p['have_cashless'] ?? false),
                    ];
                })->filter(fn ($p) => $p['code'] !== '')->values()->all();

                return $items ?: $this->mockPickupPoints($cityCode);
            } catch (\Throwable $e) {
                report($e);
                return $this->mockPickupPoints($cityCode);
            }
        });
    }

    private function token(): string
    {
        return Cache::remember('cdek:token', 50 * 60, function () {
            $resp = Http::asForm()
                ->timeout(10)
                ->post($this->baseUrl . '/oauth/token?parameters', [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->account,
                    'client_secret' => $this->secret,
                ]);

            if (!$resp->ok()) {
                throw new \RuntimeException('CDEK auth failed: ' . $resp->status() . ' ' . $resp->body());
            }
            return (string) $resp->json('access_token');
        });
    }

    // ───────────── Mock fallback ─────────────
    // Когда нет валидных CDEK creds (например, в dev) — отдаём демо-данные,
    // чтобы UI работал. В .env задайте CDEK_ACCOUNT и CDEK_SECRET для прода.

    private function mockCities(string $query): array
    {
        $all = [
            ['code' => 137, 'city' => 'Москва',          'region' => 'г. Москва'],
            ['code' => 44,  'city' => 'Санкт-Петербург', 'region' => 'г. Санкт-Петербург'],
            ['code' => 414, 'city' => 'Махачкала',       'region' => 'Дагестан'],
            ['code' => 247, 'city' => 'Каспийск',        'region' => 'Дагестан'],
            ['code' => 270, 'city' => 'Новосибирск',     'region' => 'Новосибирская обл.'],
            ['code' => 438, 'city' => 'Краснодар',       'region' => 'Краснодарский край'],
            ['code' => 49,  'city' => 'Казань',          'region' => 'Татарстан'],
            ['code' => 50,  'city' => 'Нижний Новгород', 'region' => 'Нижегородская обл.'],
            ['code' => 250, 'city' => 'Екатеринбург',    'region' => 'Свердловская обл.'],
            ['code' => 51,  'city' => 'Самара',          'region' => 'Самарская обл.'],
        ];

        $q = mb_strtolower($query);
        return collect($all)
            ->filter(fn ($c) => str_contains(mb_strtolower($c['city']), $q))
            ->map(fn ($c) => array_merge($c, [
                'country_code' => 'RU',
                'full'         => $c['city'] . ', ' . $c['region'],
            ]))
            ->values()
            ->all();
    }

    private function mockPickupPoints(int $cityCode): array
    {
        $points = [
            137 => [
                ['code' => 'MSK1', 'name' => 'ПВЗ Москва — Тверская',  'address' => 'ул. Тверская, 13', 'lat' => 55.7611, 'lon' => 37.6058],
                ['code' => 'MSK2', 'name' => 'ПВЗ Москва — Арбат',     'address' => 'ул. Старый Арбат, 25', 'lat' => 55.7497, 'lon' => 37.5916],
                ['code' => 'MSK3', 'name' => 'ПВЗ Москва — Курская',   'address' => 'ул. Земляной Вал, 33', 'lat' => 55.7575, 'lon' => 37.6589],
            ],
            44 => [
                ['code' => 'SPB1', 'name' => 'ПВЗ СПб — Невский',     'address' => 'Невский пр-кт, 35',  'lat' => 59.9325, 'lon' => 30.3454],
                ['code' => 'SPB2', 'name' => 'ПВЗ СПб — Васильевский', 'address' => '6-я линия В.О., 19', 'lat' => 59.9430, 'lon' => 30.2820],
            ],
            414 => [
                ['code' => 'MKL1', 'name' => 'ПВЗ Махачкала — Центр', 'address' => 'пр. Имама Шамиля, 13', 'lat' => 42.9845, 'lon' => 47.5047],
                ['code' => 'MKL2', 'name' => 'ПВЗ Махачкала — ТРЦ "Этажи"', 'address' => 'ул. Ярагского, 71', 'lat' => 42.9748, 'lon' => 47.4872],
            ],
            247 => [
                ['code' => 'KSP1', 'name' => 'ПВЗ Каспийск', 'address' => 'ул. Ленина, 53', 'lat' => 42.8795, 'lon' => 47.6252],
            ],
        ];

        $list = $points[$cityCode] ?? [];

        return array_map(fn ($p) => array_merge($p, [
            'address_full'     => $p['address'],
            'city'             => '',
            'work_time'        => 'Пн-Сб 10:00–20:00, Вс 11:00–18:00',
            'phones'           => [],
            'note'             => 'Демо-данные. Настройте CDEK_ACCOUNT и CDEK_SECRET для реальных ПВЗ.',
            'type'             => 'PVZ',
            'is_dressing_room' => false,
            'have_cash'        => true,
            'have_cashless'    => true,
        ]), $list);
    }
}