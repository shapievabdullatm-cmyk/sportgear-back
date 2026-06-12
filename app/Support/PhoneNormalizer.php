<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Нормализует российский номер телефона к формату +7XXXXXXXXXX.
     *
     * Принимает форматы:
     *   +79998889988, 79998889988, 89998889988, 9998889988,
     *   +7 (999) 888-99-88, 8 999 888 99 88, и т.п.
     *
     * Возвращает null если на вход пришло пусто/невалидно.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        if (strlen($digits) !== 11) {
            return null;
        }

        return '+' . $digits;
    }
}