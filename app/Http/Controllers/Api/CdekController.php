<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CdekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdekController extends Controller
{
    public function __construct(
        private CdekService $cdek
    ) {}

    public function cities(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        return response()->json($this->cdek->searchCities($q));
    }

    public function pickupPoints(Request $request): JsonResponse
    {
        $code = (int) $request->query('city_code', 0);
        if ($code <= 0) {
            return response()->json([]);
        }
        return response()->json($this->cdek->pickupPoints($code));
    }
}