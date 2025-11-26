<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use App\Console\Commands\AutoCleanupMedia;
use Illuminate\Support\Facades\Artisan;
Route::get('/', function () {
    return view('welcome');
});

// Thêm route này để hiển thị trang download
Route::get('/download', [MediaController::class, 'showDownloadPage']);
Route::get('/run-cleanup', function () {
    // 🔒 BẢO MẬT: Chỉ cho phép từ IP của Render hoặc có token
    if (!app()->isLocal() && !hash_equals('secret123xyz!@#', request()->query('token'))) {
        abort(403);
    }

    Artisan::call('media:cleanup');
    return response()->json([
        'message' => 'Cleanup completed at ' . now(),
        'output' => Artisan::output()
    ]);
})->name('cleanup');