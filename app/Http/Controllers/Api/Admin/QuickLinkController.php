<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\QuickLink\StoreRequest;
use App\Http\Requests\Api\Admin\QuickLink\UpdateRequest;
use App\Http\Resources\QuickLink\QuickLinkResource;
use App\Models\QuickLink;
use App\Services\QuickLinkService;
use Illuminate\Http\Request;

class QuickLinkController extends Controller
{
    public function index() {
        return QuickLinkResource::collection(QuickLink::all());
    }

    public function store(StoreRequest $request) {
        $data = $request->validated();
        $quickLink = QuickLinkService::store($data);
        return QuickLinkResource::make($quickLink);
    }

    public function update(UpdateRequest $request, QuickLink $quickLink)
    {
        $data = $request->validated();
        $quickLink = QuickLinkService::update($quickLink, $data);
        return QuickLinkResource::make($quickLink);
    }

    public function destroy(QuickLink $quickLink) {
        $quickLink->delete();
        return response()->json([
            'message' => 'Успешно удалено'
        ], 200);
    }
}
