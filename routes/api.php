<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PayController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\RevenueController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\AdminController;

// Rating
use App\Http\Controllers\RatingController;
// TemplateFrame
use App\Http\Controllers\TemplateFrameController;

// Sticker
use App\Http\Controllers\StickerController;

// AI Topic
use App\Http\Controllers\AiTopicController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/event-client', [EventController::class, 'show']);
Route::get('/background', [DataController::class, 'getEventBackground']);
Route::get('/prices', [DataController::class, 'getPrices']);
// Lấy danh sách mã giảm giá
Route::get('/discounts', [DiscountController::class, 'index']);

// Kiểm tra mã (không tăng count)
Route::post('/discounts/check', [DiscountController::class, 'check']);

// Cập nhật count_quantity (sau khi thanh toán)
Route::post('/discounts/use', [DiscountController::class, 'use']);

// Lưu thanh toán
Route::post('/pays', [DiscountController::class, 'storePay']);

Route::get('/frames-client', [FrameController::class, 'index']);
// Lấy đầy đủ thông tin camera
Route::get('/camera', [CameraController::class, 'show']);
Route::post('/camera', [CameraController::class, 'update']);

// Lấy thông tin cơ bản 
Route::get('/camera/basic', [CameraController::class, 'basic']);

// Upload media
Route::post('/media', [MediaController::class, 'store']);

// Lấy media theo session_id
Route::get('/media/session', [MediaController::class, 'showBySession']);

Route::get('/download', [MediaController::class, 'showDownloadPage']);

Route::post('/chat', [ChatbotController::class, 'sendMessage']);

// API ADMIN
Route::get('/count', [ReportController::class, 'countUsers']);
Route::get('/sum-price', [ReportController::class, 'getSumPrice']);
Route::get('/price', [ReportController::class, 'getPrice']);

// Events
Route::get('/events-admin', [EventController::class, 'index']); // get_event.php
Route::get('/event-notes', [EventController::class, 'notes']); // get_note.php
Route::post('/events-admin', [EventController::class, 'store']); // add_event.php
Route::put('/events-admin/{id}', [EventController::class, 'update']); // update_event4.php
Route::delete('/events-admin/{id}', [EventController::class, 'destroy']); // delete_event.php

// Event updates (special fields)
Route::post('/events-admin/{id}/note', [EventController::class, 'updateNote']); // update_event3.php
Route::post('/events-admin/{id}/logo', [EventController::class, 'updateLogo']); // update_logo.php
Route::post('/events-admin/{id}/background', [EventController::class, 'updateBackground']); // update_background.php

// Users
Route::get('/users-admin', [UserController::class, 'index']); // get_user.php
Route::post('/users-admin/{id}', [UserController::class, 'update']); // update_user.php

Route::get('get-promotion', [DiscountController::class, 'index1']);       // GET với ?admin_id=...
Route::post('add-promotion', [DiscountController::class, 'store']);     // Tạo mới
Route::put('promotion/{id}', [DiscountController::class, 'update']); // Cập nhật theo id
Route::delete('de-promotion/{id}', [DiscountController::class, 'destroy']); // Xóa theo id

Route::get('get-new-id', [DiscountController::class, 'getNewId']); // Lấy id mới nhất
Route::put('update-pay', [DiscountController::class, 'updatePay']); // Cập nhật thanh toán

Route::get('get-orders', [PayController::class, 'getOrders']); // Lấy danh sách thanh toán

// Template
Route::get('/frame-image', [TemplateController::class, 'getFrameImage']);
Route::get('/top-frames', [TemplateController::class, 'getTopFramesByAdmin']);
Route::get('/qr-image', [MediaController::class, 'getQrBySession']);

// Revenue
Route::get('/summary', [RevenueController::class, 'summary']);
Route::get('/range', [RevenueController::class, 'byDateRange']);
Route::get('/month', [RevenueController::class, 'byMonth']);
Route::get('/quarter', [RevenueController::class, 'byQuarter']);
Route::get('/year', [RevenueController::class, 'byYear']);

// Size
Route::get('/size', [SizeController::class, 'show']);
Route::post('/size', [SizeController::class, 'update']);

// Lấy thông tin người dùng
Route::get('/users', [AdminController::class, 'index']);
Route::post('/users', [AdminController::class, 'store']); // Tạo người dùng mới
Route::put('/users/{id}', [AdminController::class, 'update']);
Route::delete('/users/{id}', [AdminController::class, 'destroy']);

// Lưu đánh giá khách hàng
// ĐÚNG: Dùng use + class name
Route::post('/ratings', [RatingController::class, 'store']);
Route::get('/ratings', [RatingController::class, 'index']);
Route::delete('/ratings/{id}', [RatingController::class, 'destroy']);

// khung ảnh 

// THAY BẰNG ĐÚNG CONTROLLER
Route::get('/frames', [TemplateFrameController::class, 'index']);
Route::post('/frames', [TemplateFrameController::class, 'store']);
Route::put('/frames/{id}', [TemplateFrameController::class, 'update']);
Route::delete('/frames/{id}', [TemplateFrameController::class, 'destroy']);

// SỬA ROUTE EVENT → ĐÚNG VỚI EventController
Route::get('/event', [EventController::class, 'show']);

// THÊM ROUTE LẤY DANH SÁCH EVENT CHO SELECT


// LẤY DANH SÁCH SỰ KIỆN CHO SELECT
// routes/api.php → ĐÃ ĐÚNG, CHỈ THÊM DÒNG NÀY
Route::get('/events', function (Request $request) {
    $request->validate(['id_admin' => 'required|integer']);
    
    $events = \App\Models\Event::where('id_admin', $request->id_admin)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

    return response()->json([
        'status' => 'success',
        'data' => $events
    ]);
});


//gửi ảnh qr
Route::post('/send-qr-email', [MediaController::class, 'sendQrEmail']);
Route::post('/send-original-images-email', [MediaController::class, 'sendOriginalImagesEmail']);

//ảnh frame
Route::get('/frame-image-client/{filename}', function (string $filename) {
    // Validate filename to prevent directory traversal
    if (preg_match('/^[a-zA-Z0-9._-]+\.png$/i', $filename) === 0) {
        abort(400, 'Invalid filename');
    }

    $path = 'frames/' . $filename;

    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }

    $file = Storage::disk('public')->get($path);
    $mimeType = Storage::disk('public')->mimeType($path);

    return Response::make($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type');
})->name('frame.image');


// ==================== QUẢN LÝ STICKER (HOÀN HẢO, KHÔNG LỖI) ====================
Route::prefix('stickers')->group(function () {
    Route::get('/', [StickerController::class, 'index']);                    // ?id_admin=&page=&limit=&search=&filter_type=
    Route::post('/', [StickerController::class, 'store']);                   // Thêm nhiều file
    Route::put('/{id}', [StickerController::class, 'update']);               // Update chuẩn REST
    Route::delete('/{id}', [StickerController::class, 'destroy']);
});

Route::prefix('ai-topics')->group(function () {
    Route::get('/', [AiTopicController::class, 'index']);
    Route::post('/', [AiTopicController::class, 'store']);
    Route::match(['put', 'patch'], '/{id}', [AiTopicController::class, 'update']);
    Route::delete('/{id}', [AiTopicController::class, 'destroy']);
});
