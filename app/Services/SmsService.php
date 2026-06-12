<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        if (config('sms.debug')) {
            Log::info("SMS DEBUG → {$phone}: {$message}");
            return true;
        }

        $response = Http::get('https://smsc.ru/sys/send.php', [
            'login'   => config('sms.smsc_login'),
            'psw'     => config('sms.smsc_password'),
            'phones'  => $phone,
            'mes'     => $message,
            'fmt'     => 3,
            'charset' => 'utf-8',
        ]);

        if ($response->failed() || isset($response->json()['error'])) {
            Log::error('SMSC: ' . $response->body());
            return false;
        }

        return true;
    }
}
