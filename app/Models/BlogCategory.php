<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogCategory $category) {
            if (empty($category->slug)) {
                $category->slug = static::uniqueSlug($category->name);
            }
        });

        static::updating(function (BlogCategory $category) {
            if ($category->isDirty('name') && !$category->isDirty('slug')) {
                $category->slug = static::uniqueSlug($category->name, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (
            static::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug ?: 'category-' . time();
    }

    public function blogs()
    {
        return $this->hasMany(Blog::class);
    }
}