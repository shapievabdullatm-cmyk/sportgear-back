<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BrandOrigin extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'flag_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($origin) {
            if (empty($origin->slug)) {
                $origin->slug = static::generateUniqueSlug($origin->name);
            }
        });

        static::updating(function ($origin) {
            // Если изменилось имя И slug не был явно изменен пользователем
            if ($origin->isDirty('name') && !$origin->isDirty('slug')) {
                $origin->slug = static::generateUniqueSlug($origin->name, $origin->id);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'origin';
        }

        $originalSlug = $slug;
        $i = 1;

        while (
            static::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $i++;
        }

        return $slug;
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_origin_id');
    }
}
