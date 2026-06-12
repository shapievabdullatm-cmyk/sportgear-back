<?php

namespace App\Services;

use App\Models\Blog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BlogService
{
    public static function store(array $data): Blog
    {
        return DB::transaction(function () use ($data) {
            if (isset($data['banner_image']) && $data['banner_image'] instanceof UploadedFile) {
                $data['banner_image'] = ImageService::uploadBlogBanner($data['banner_image']);
            }

            if (!empty($data['is_published']) && empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            return Blog::create($data);
        });
    }

    public static function update(Blog $blog, array $data): Blog
    {
        return DB::transaction(function () use ($blog, $data) {
            if (!empty($data['remove_banner']) && $blog->banner_image) {
                ImageService::delete($blog->banner_image);
                $data['banner_image'] = null;
            }

            if (isset($data['banner_image']) && $data['banner_image'] instanceof UploadedFile) {
                if ($blog->banner_image) {
                    ImageService::delete($blog->banner_image);
                }
                $data['banner_image'] = ImageService::uploadBlogBanner($data['banner_image']);
            }

            if (!empty($data['is_published']) && !$blog->is_published && empty($data['published_at'])) {
                $data['published_at'] = now();
            }

            if (isset($data['is_published']) && !$data['is_published']) {
                $data['published_at'] = null;
            }

            unset($data['remove_banner']);
            $blog->update($data);

            return $blog->fresh();
        });
    }

    public static function destroy(Blog $blog): void
    {
        DB::transaction(function () use ($blog) {
            if ($blog->banner_image) {
                ImageService::delete($blog->banner_image);
            }

            $blog->delete();
        });
    }
}