<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManufacturingCountry extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'flag_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($country) {
            if (empty($country->slug)) {
                $country->slug = static::generateUniqueSlug($country->name);
            }
        });

        static::updating(function ($country) {
            // Если изменилось имя И slug не был явно изменен пользователем
            if ($country->isDirty('name') && !$country->isDirty('slug')) {
                $country->slug = static::generateUniqueSlug($country->name, $country->id);
            }
        });
    }

    public static function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);

        if (empty($slug)) {
            $slug = 'country';
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
        return $this->hasMany(Product::class, 'manufacturing_country_id');
    }
}
