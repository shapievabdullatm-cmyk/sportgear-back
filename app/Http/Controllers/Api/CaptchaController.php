<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CaptchaService;

class CaptchaController extends Controller
{
    public function __construct(protected CaptchaService $captcha) {}

    public function generate()
    {
        return response()->json($this->captcha->generate());
    }
}