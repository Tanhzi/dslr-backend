<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;

Route::get('/', function () {
    return view('welcome');
});

// Thêm route này để hiển thị trang download
Route::get('/download', [MediaController::class, 'showDownloadPage']);