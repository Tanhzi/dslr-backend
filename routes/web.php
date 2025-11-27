<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// Thêm route này để hiển thị trang download
Route::get('/download', [MediaController::class, 'showDownloadPage']);

Route::get('/cleanup-trigger', function () {
    // ✅ DÙNG TOKEN CHỈ CÓ CHỮ + SỐ + GẠCH DƯỚI
    if (!hash_equals('cleanup_token_2025_xyz_789abc', request()->query('token'))) {
        abort(403);
    }

    Artisan::call('media:cleanup');
    return 'OK - Cleanup done at ' . now();
})->name('cleanup.trigger');