<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Artisan;

// API service — không dùng view Vite (tránh 500 khi chưa npm build trên server)
Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]);
});

// Thêm route này để hiển thị trang download
Route::get('/download', [MediaController::class, 'showDownloadPage']);
