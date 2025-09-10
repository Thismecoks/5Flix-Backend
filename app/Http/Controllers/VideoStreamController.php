<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class VideoStreamController extends Controller
{
    /**
     * Inisialisasi S3Client (S3-compatible Backblaze B2).
     */
    private function s3(): S3Client
    {
        return new S3Client([
            'version'                 => 'latest',
            'region'                  => env('B2_REGION', 'us-east-005'),
            'endpoint'                => env('B2_ENDPOINT', 'https://s3.us-east-005.backblazeb2.com'),
            'use_path_style_endpoint' => filter_var(env('B2_USE_PATH_STYLE', true), FILTER_VALIDATE_BOOLEAN),
            'credentials' => [
                'key'    => env('B2_KEY_ID'),
                'secret' => env('B2_APPLICATION_KEY'),
            ],
        ]);
    }

    /**
     * Normalisasi nilai kolom DB menjadi S3 object key (path di dalam bucket).
     * Menerima:
     *   - "videos/abc.mp4" (disarankan)
     *   - URL S3 virtual-host: https://<bucket>.s3.us-east-005.backblazeb2.com/videos/abc.mp4
     *   - URL S3 path-style  : https://s3.us-east-005.backblazeb2.com/<bucket>/videos/abc.mp4
     * (Tidak mendukung friendly URL /file/... sesuai permintaanmu)
     */
    private function normalizeKey(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;

        // Jika sudah berupa key relatif
        if (!Str::startsWith($value, ['http://', 'https://'])) {
            return ltrim($value, '/');
        }

        // Jika berupa URL S3, ambil path
        $p = parse_url($value);
        if (!isset($p['path'])) return null;

        $path = ltrim($p['path'], '/');          // e.g. "5-flix/videos/abc.mp4" atau "videos/abc.mp4"
        $bucket = env('B2_BUCKET');

        // Jika path-style (berawal dengan "<bucket>/"), buang prefix bucket
        if ($bucket && Str::startsWith($path, $bucket . '/')) {
            return substr($path, strlen($bucket) + 1);
        }

        // Kalau virtual-host, path sudah langsung "videos/abc.mp4"
        return $path;
    }

    /**
     * Peta ekstensi → MIME (untuk ResponseContentType).
     */
    private function guessVideoMime(string $key): string
    {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/x-m4v',
            'mkv'  => 'video/x-matroska',
            'webm' => 'video/webm',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            '3gp'  => 'video/3gpp',
            'ts'   => 'video/mp2t',
            'flv'  => 'video/x-flv',
            default => 'application/octet-stream', // fallback aman
        };
    }

    private function guessImageMime(string $key): string
    {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };
    }

    /**
     * Buat Pre-Signed URL (SigV4) untuk objek di B2 S3.
     * $ttlSeconds: 60..3600
     * Bisa override ResponseContentType agar pemutar/browser tahu MIME.
     */
    private function presign(string $key, int $ttlSeconds = 600, ?string $responseContentType = null): string
    {
        $ttlSeconds = max(60, min($ttlSeconds, 3600));

        $bucket = env('B2_BUCKET');
        $s3 = $this->s3();

        $params = [
            'Bucket' => $bucket,
            'Key'    => $key,
        ];
        if ($responseContentType) {
            $params['ResponseContentType'] = $responseContentType;
        }

        $cmd = $s3->getCommand('GetObject', $params);
        $req = $s3->createPresignedRequest($cmd, '+' . $ttlSeconds . ' seconds');
        return (string) $req->getUri();
    }

    /**
     * HEAD untuk memastikan objek ada (opsional tapi bagus agar 404 cepat).
     */
    private function assertObjectExists(string $key): void
    {
        $bucket = env('B2_BUCKET');
        $this->s3()->headObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
    }

    /**
     * GET /api/videos/{id}/stream
     * Redirect 302 (atau JSON) ke Pre-Signed URL. Range ditangani langsung oleh B2.
     * Query:
     *   - ttl=600 (detik, 60..3600)
     *   - json=1  (kembalikan {url,expires_in} alih-alih redirect)
     */
    public function streamVideo(Request $request, $id)
    {
        try {
            $video = Cache::remember("video_stream_meta.$id", 1800, function () use ($id) {
                return Video::select(['id', 'video_url'])->findOrFail($id);
            });

            $key = $this->normalizeKey((string) $video->video_url);
            if (!$key) {
                return response()->json(['success' => false, 'message' => 'Invalid video path'], 400);
            }

            // Pastikan objek ada (supaya kalau salah, 404 cepat)
            $this->assertObjectExists($key);

            $ttl  = (int) $request->query('ttl', 600);
            $mime = $this->guessVideoMime($key);
            $url  = $this->presign($key, $ttl, $mime);

            if ($request->boolean('json')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'url'        => $url,
                        'expires_in' => max(60, min($ttl, 3600)),
                    ],
                ]);
            }

            return redirect()->away($url, 302);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Video not found'], 404);
        } catch (AwsException $e) {
            // bisa dilog: $e->getAwsErrorCode(), $e->getMessage()
            return response()->json(['success' => false, 'message' => 'S3 error'], 502);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * GET /api/videos/{id}/thumbnail
     * Redirect 302 (atau JSON) ke Pre-Signed URL thumbnail.
     */
    public function getThumbnail(Request $request, $id)
    {
        try {
            $video = Cache::remember("video_thumbnail_meta.$id", 3600, function () use ($id) {
                return Video::select(['id', 'thumbnail_url'])->findOrFail($id);
            });

            $key = $this->normalizeKey((string) $video->thumbnail_url);
            if (!$key) {
                return response()->json(['success' => false, 'message' => 'Invalid thumbnail path'], 400);
            }

            $this->assertObjectExists($key);

            $ttl  = (int) $request->query('ttl', 600);
            $mime = $this->guessImageMime($key);
            $url  = $this->presign($key, $ttl, $mime);

            if ($request->boolean('json')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'url'        => $url,
                        'expires_in' => max(60, min($ttl, 3600)),
                    ],
                ]);
            }

            return redirect()->away($url, 302);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Video not found'], 404);
        } catch (AwsException $e) {
            return response()->json(['success' => false, 'message' => 'S3 error'], 502);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * GET /api/videos/{id}/info
     * Kembalikan metadata + pre-signed URL (TTL singkat untuk preview).
     */
    public function getVideoInfo($id)
    {
        try {
            $video = Cache::remember("video_info.$id", 1800, function () use ($id) {
                return Video::findOrFail($id);
            });

            $videoKey = $video->video_url ? $this->normalizeKey((string) $video->video_url) : null;
            $thumbKey = $video->thumbnail_url ? $this->normalizeKey((string) $video->thumbnail_url) : null;

            $streamUrl    = $videoKey ? $this->presign($videoKey, 600, $this->guessVideoMime($videoKey)) : null;
            $thumbnailUrl = $thumbKey ? $this->presign($thumbKey, 600, $this->guessImageMime($thumbKey)) : null;

            $duration = (int) ($video->duration ?? 0);
            $minutes  = round($duration / 60, 1);

            return response()->json([
                'success' => true,
                'data' => [
                    'id'                 => $video->id,
                    'title'              => $video->title,
                    'genre'              => $video->genre,
                    'description'        => $video->description,
                    'duration'           => $duration,
                    'duration_minutes'   => $minutes,
                    'duration_formatted' => $this->formatDuration($duration),
                    'year'               => $video->year,
                    'is_featured'        => (bool) $video->is_featured,
                    'stream_url'         => $streamUrl,
                    'thumbnail_url'      => $thumbnailUrl,
                    'api_stream'         => route('video.stream', ['id' => $video->id]),
                    'api_thumbnail'      => route('video.thumbnail', ['id' => $video->id]),
                    'created_at'         => $video->created_at,
                    'updated_at'         => $video->updated_at,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Video not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    private function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }
}