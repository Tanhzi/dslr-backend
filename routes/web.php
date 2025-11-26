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
    // 🔒 Bảo mật bằng token (bắt buộc)
    if (!hash_equals('k8d9#Lm2$vPq!xR5', request()->query('token'))) {
        abort(403);
    }

    Artisan::call('media:cleanup');
    return 'OK - Cleanup done at ' . now();
})->name('cleanup.trigger');