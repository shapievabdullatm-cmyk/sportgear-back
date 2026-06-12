<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    protected $fillable = [
        'blog_category_id',
        'title',
        'slug',
        'banner_image',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Blog $blog) {
            if (empty($blog->slug)) {
                $blog->slug = static::uniqueSlug($blog->title);
            }
        });

        static::updating(function (Blog $blog) {
            // Пересчитываем slug только если изменился title и slug не задан явно
            if ($blog->isDirty('title') && !$blog->isDirty('slug')) {
                $blog->slug = static::uniqueSlug($blog->title, $blog->id);
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

        return $slug ?: 'blog-' . time();
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    // ── Route model binding ──────────────────────────────────────────────────

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return is_numeric($value)
            ? $this->where('id', $value)->firstOrFail()
            : $this->where('slug', $value)->firstOrFail();
    }
}