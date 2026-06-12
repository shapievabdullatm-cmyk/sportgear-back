<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cdek' => [
        // Если env не задан — используем publicly-known credentials песочницы CDEK.
        // На проде задайте CDEK_ACCOUNT и CDEK_SECRET.
        'account'  => env('CDEK_ACCOUNT',  'EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI'),
        'secret'   => env('CDEK_SECRET',   'PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG'),
        'base_url' => env('CDEK_BASE_URL', 'https://api.edu.cdek.ru/v2'),
    ],

    'russian_post' => [
        // Otpravka API требует юрлицо. Без токена сервис вернёт мок-данные.
        'token'    => env('RUSSIAN_POST_TOKEN'),
        'login'    => env('RUSSIAN_POST_LOGIN'),
        'password' => env('RUSSIAN_POST_PASSWORD'),
        'base_url' => env('RUSSIAN_POST_BASE_URL', 'https://otpravka-api.pochta.ru'),
    ],

    'yandex' => [
        'geocoder_key' => env('YANDEX_GEOCODER_KEY'),
        'referer'      => env('YANDEX_REFERER', 'http://localhost:3000'),
    ],

];
