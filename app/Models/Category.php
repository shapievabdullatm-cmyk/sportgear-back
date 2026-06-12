<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Category extends Model
{
    use Searchable;
    protected $fillable = [
        'title',
        'slug',
        'image',
        'meta_title',
        'meta_description',
        'keywords',
        'parent_id',
        'position',
    ];

    // ─── Slug auto-generation ─────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = static::uniqueSlug($category->title);
            }
        });

        static::updating(function (Category $category) {
            // Пересчитываем slug только если изменился title и slug не задан явно
            if ($category->isDirty('title') && !$category->isDirty('slug')) {
                $category->slug = static::uniqueSlug($category->title, $category->id);
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
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

    // ─── Scout / Meilisearch ──────────────────────────────────────────────────

    public function toSearchableArray(): array
    {
        return [
            'id'       => $this->id,
            'title'    => (string) ($this->title ?? ''),
            'slug'     => $this->slug,
            'keywords' => (string) ($this->keywords ?? ''),
            'image'    => $this->image,
        ];
    }

    // ─── Client-side route binding: /categories/{id-or-slug} ─────────────────
    // Позволяет находить категорию как по id, так и по slug:
    //   /categories/42          → найдёт по id
    //   /categories/odezhda     → найдёт по slug

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return is_numeric($value)
            ? $this->where('id', $value)->firstOrFail()
            : $this->where('slug', $value)->firstOrFail();
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id', 'id')
            ->without('parent');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id')
            ->without('children');
    }

    public function params(): BelongsToMany
    {
        return $this->belongsToMany(Param::class, 'category_params')
            ->withPivot(['sort', 'is_required']);
    }
}
