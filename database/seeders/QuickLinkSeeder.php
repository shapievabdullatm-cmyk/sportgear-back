<?php

namespace Database\Seeders;

use App\Models\QuickLink;
use Illuminate\Database\Seeder;

class QuickLinkSeeder extends Seeder
{
    public function run(): void
    {
        $quickLinks = [
            ['title' => 'О компании', 'url' => '/'],
            ['title' => 'Доставка',   'url' => '/catalog'],
            ['title' => 'Контакты',   'url' => '/cart'],
            ['title' => 'Оплата',     'url' => '/profile'],
            ['title' => 'Оптовикам',  'url' => '/profile'],
            ['title' => 'Акции',      'url' => '/profile'],
        ];

        foreach ($quickLinks as $link) {
            QuickLink::firstOrCreate(
                ['title' => $link['title'], 'url' => $link['url']],
                $link
            );
        }
    }
}