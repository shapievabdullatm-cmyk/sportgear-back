<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RussianPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RussianPostController extends Controller
{
    public function __construct(
        private RussianPostService $rp
    ) {}

    public function cities(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        return response()->json($this->rp->searchCities($q));
    }

    public function pickupPoints(Request $request): JsonResponse
    {
        $city = (string) $request->query('city_code', '');
        return response()->json($this->rp->pickupPoints($city));
    }
}