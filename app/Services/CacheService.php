<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    // Lama cache untuk list (detik)
    private const TTL_INDEX   = 120;
    private const TTL_FEATURE = 300;
    private const KEY_INDEX   = 'videos.raw';
    private const KEY_FEATURE = 'videos.featured';
    private const KEY_ITEM    = 'video.'; // + {id}

    /**
     * Ambil list video mentah untuk index (tanpa pre-signed URL).
     * Disarankan select kolom yang memang dibutuhkan.
     */
    public static function getVideos()
    {
        return Cache::remember(self::KEY_INDEX, self::TTL_INDEX, function () {
            return Video::query()
                ->select([
                    'id', 'title', 'genre', 'description',
                    'duration', 'year', 'is_featured',
                    'thumbnail_url', 'video_url',
                    'created_at', 'updated_at',
                ])
                ->orderByDesc('created_at')
                ->get();
        });
    }

    /**
     * Ambil satu video (untuk show/detail).
     */
    public static function getVideo(int $id): Video
    {
        return Cache::remember(self::KEY_ITEM.$id, self::TTL_INDEX, function () use ($id) {
            return Video::findOrFail($id);
        });
    }

    /**
     * Ambil daftar featured.
     */
    public static function getFeaturedVideos()
    {
        return Cache::remember(self::KEY_FEATURE, self::TTL_FEATURE, function () {
            return Video::query()
                ->where('is_featured', true)
                ->select([
                    'id', 'title', 'genre', 'description',
                    'duration', 'year', 'is_featured',
                    'thumbnail_url', 'video_url',
                    'created_at', 'updated_at',
                ])
                ->orderByDesc('created_at')
                ->get();
        });
    }

    /**
     * Hapus cache yang berkaitan dengan sebuah video.
     */
    public static function clearVideoCache(int $id): void
    {
        Cache::forget(self::KEY_ITEM.$id);
        Cache::forget(self::KEY_INDEX);
        Cache::forget(self::KEY_FEATURE);
    }

    /**
     * Opsi: hapus semua cache list (tanpa id spesifik).
     */
    public static function clearAll(): void
    {
        Cache::forget(self::KEY_INDEX);
        Cache::forget(self::KEY_FEATURE);
    }
}