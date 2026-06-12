<?php

namespace App\Services;

use App\Models\QuickLink;

class QuickLinkService
{
        public static function store(array $data)
        {
            return QuickLink::create($data);
        }

        public static function update(QuickLink $quickLink, array $data):QuickLink
        {
            $quickLink->update($data);
            return $quickLink->fresh();
        }
}
