<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'image',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($brand) {
            if (empty($brand->slug)) {
                $brand->slug = static::generateUniqueSlug($brand->name);
            }
        });

        static::updating(function ($brand) {
            // Если изменилось имя И slug не был явно изменен пользователем
            if ($brand->isDirty('name') && !$brand->isDirty('slug')) {
                $brand->slug = static::generateUniqueSlug($brand->name, $brand->id);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'brand';
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
