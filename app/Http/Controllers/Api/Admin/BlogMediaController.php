<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BlogMediaController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $path = ImageService::uploadBlogContentImage($request->file('image'));

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
        ]);
    }

    public function uploadVideo(Request $request)
    {
        // Долгая конвертация FFmpeg — снимаем ограничения времени
        set_time_limit(0);
        ignore_user_abort(true);

        $request->validate([
            'video' => 'required|file|mimes:mp4,webm,ogg,mov,avi,mkv,flv,wmv|max:1048576',
        ]);

        try {
            $path = ImageService::uploadBlogVideo($request->file('video'));
        } catch (\Throwable $e) {
            Log::error('Blog video upload failed', [
                'error' => $e->getMessage(),
                'file_size' => $request->file('video')?->getSize(),
                'file_name' => $request->file('video')?->getClientOriginalName(),
            ]);
            return response()->json(['message' => 'Не удалось обработать видео: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
        ]);
    }
}