<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoStreamController;
use App\Http\Controllers\AuthController;

// User info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $request->user()->id,
            'username' => $request->user()->username,
            'role' => $request->user()->role
        ]
    ]);
});

// Auth routes with strict rate limiting - using basic throttle first
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/refresh', [AuthController::class, 'refresh']); // Tambahan refresh token endpoint
});

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Public video routes with generous rate limiting
Route::middleware('throttle:100,1')->group(function () {
    Route::get('videos', [VideoController::class, 'index']);
    Route::get('videos/{id}', [VideoController::class, 'show']);

    // Public streaming endpoints - accessible to all users with high limit
    Route::middleware('throttle:200,1')->group(function () {
        Route::get('videos/{id}/info', [VideoStreamController::class, 'getVideoInfo'])->name('api.video.info');
        Route::get('videos/{id}/stream', [VideoStreamController::class, 'streamVideo'])->name('api.video.stream');
        Route::get('videos/{id}/thumbnail', [VideoStreamController::class, 'getThumbnail'])->name('api.video.thumbnail');
    });

});


// Protected routes with authentication and admin rate limiting
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // Admin-only CRUD operations
    Route::post('videos', [VideoController::class, 'store']);
    Route::post('videos/{id}/update', [VideoController::class, 'update']);
    Route::delete('videos/{id}', [VideoController::class, 'destroy']);

    // Download endpoint with special download rate limiting
    Route::get('videos/{id}/download', [VideoController::class, 'download'])
        ->middleware('throttle:10,1');

    // Featured videos endpoint
    Route::get('videos-featured', function () {
        try {
            $videos = \App\Services\CacheService::getFeaturedVideos();
            return response()->json([
                'success' => true,
                'data' => $videos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    });

});

// Direct upload routes (admin only)
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::post('videos/upload-urls', [VideoController::class, 'getUploadUrls']);
    Route::post('videos/confirm-upload', [VideoController::class, 'confirmUpload']);
});