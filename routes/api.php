<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\FrameController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\MediaController;
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

// contentchat
use App\Http\Controllers\ContentController;

// Chatbot OpenRouter
use App\Http\Controllers\ChatController;


// === PUBLIC ROUTES (không cần token) ===
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Gia hạn JWT (cookie httpOnly / sliding expiration) & đăng xuất (xóa cookie)
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/event-client', [EventController::class, 'show']);
Route::get('/background', [DataController::class, 'getEventBackground']);
Route::get('/prices', [DataController::class, 'getPrices']);
Route::get('/discounts', [DiscountController::class, 'index']);
Route::post('/discounts/check', [DiscountController::class, 'check']);
// Các endpoint GHI dữ liệu: bắt buộc đăng nhập (kiosk đã login) + giới hạn tần suất
Route::post('/discounts/use', [DiscountController::class, 'use'])->middleware(['jwt', 'throttle:30,1']);
Route::post('/pays', [DiscountController::class, 'storePay'])->middleware(['jwt', 'throttle:30,1']);

Route::get('/camera', [CameraController::class, 'show']);
Route::get('/camera/basic', [CameraController::class, 'basic']);

Route::post('/media', [MediaController::class, 'store'])->middleware(['jwt', 'throttle:30,1']);
Route::get('/media/session', [MediaController::class, 'showBySession']);
Route::get('/download', [MediaController::class, 'showDownloadPage']);

Route::get('/frames-client', [TemplateController::class, 'getFramesByFilters']);
Route::get('/qr-image', [MediaController::class, 'getQrBySession']);
Route::get('get-composite-by-session', [MediaController::class, 'getCompositeBySession']);

Route::post('/send-qr-email', [MediaController::class, 'sendQrEmail'])->middleware(['jwt', 'throttle:10,1']);
Route::post('/send-original-images-email', [MediaController::class, 'sendOriginalImagesEmail'])->middleware(['jwt', 'throttle:10,1']);

// Chatbot: lấy dữ liệu content_chat (admin cấu hình) + trả lời qua OpenRouter
Route::post('/chat', [ChatController::class, 'chat'])->middleware(['jwt', 'throttle:30,1']);

Route::get('/frame-image-client/{filename}', function (string $filename) {
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
        ->header('Access-Control-Allow-Origin', 'https://sweetlens.vercel.app')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type');
})->name('frame.image');

Route::get('/cleanup-trigger', function () {
    $token = config('services.cleanup.token');
    if (!$token || !hash_equals($token, (string) request()->query('token'))) {
        abort(403);
    }
    Artisan::call('media:cleanup');
    return response()->json([
        'message' => 'Cleanup completed at ' . now(),
        'status' => 'success'
    ]);
});

// L5: không còn public — cần đăng nhập (staff/admin) + JwtMiddleware tự gán id_admin từ token
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
})->middleware(['jwt', 'role:1,2']);

Route::post('/ratings', [RatingController::class, 'store']);
Route::get('/ratings', [RatingController::class, 'index']);

// === PROTECTED ROUTES (cần JWT token) ===
Route::middleware('jwt')->group(function () {

    // — Mọi vai trò đã đăng nhập (customer kiosk, staff, admin) —
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('get-new-id', [DiscountController::class, 'getNewId']);
    Route::put('update-pay', [DiscountController::class, 'updatePay']);

    // Kiosk (customer) cần đọc danh sách sticker + hiệu ứng AI khi chỉnh ảnh
    Route::get('/stickers', [StickerController::class, 'index']);
    Route::get('/ai-topics', [AiTopicController::class, 'index']);

    // — Staff (role 1) + Admin (role 2) —
    Route::middleware('role:1,2')->group(function () {
        Route::get('get-orders', [PayController::class, 'getOrders']);
        Route::get('/frame-image', [TemplateController::class, 'getFrameImage']);
        Route::get('/top-frames', [TemplateController::class, 'getTopFramesByAdmin']);

        Route::post('/stickers', [StickerController::class, 'store']);
        Route::put('/stickers/{id}', [StickerController::class, 'update']);
        Route::delete('/stickers/{id}', [StickerController::class, 'destroy']);

        Route::post('/ai-topics', [AiTopicController::class, 'store']);
        Route::match(['put', 'patch'], '/ai-topics/{id}', [AiTopicController::class, 'update']);
        Route::delete('/ai-topics/{id}', [AiTopicController::class, 'destroy']);

        Route::get('/frames', [TemplateFrameController::class, 'index']);
        Route::post('/frames', [TemplateFrameController::class, 'store']);
        Route::put('/frames/{id}', [TemplateFrameController::class, 'update']);
        Route::delete('/frames/{id}', [TemplateFrameController::class, 'destroy']);
    });

    // — Chỉ Admin (role 2) —
    Route::middleware('role:2')->group(function () {
        Route::post('/camera', [CameraController::class, 'update']);

        Route::get('/admin/events/{id_admin}', [EventController::class, 'getByAdminId']);

        Route::get('/count', [ReportController::class, 'countUsers']);
        Route::get('/sum-price', [ReportController::class, 'getSumPrice']);
        Route::get('/price', [ReportController::class, 'getPrice']);

        Route::get('/events-admin', [EventController::class, 'index']);
        Route::get('/event-notes', [EventController::class, 'notes']);
        Route::post('/events-admin', [EventController::class, 'store']);
        Route::put('/events-admin/{id}', [EventController::class, 'update']);
        Route::delete('/events-admin/{id}', [EventController::class, 'destroy']);
        Route::get('/events-admin/{id_admin}', [EventController::class, 'getByAdminId']);
        Route::post('/events-admin/{id}/note', [EventController::class, 'updateNote']);
        Route::post('/events-admin/{id}/logo', [EventController::class, 'updateLogo']);
        Route::post('/events-admin/{id}/background', [EventController::class, 'updateBackground']);

        Route::get('/users-admin', [UserController::class, 'index']);
        Route::post('/users-admin/{id}', [UserController::class, 'update']);

        Route::get('get-promotion', [DiscountController::class, 'index1']);
        Route::post('add-promotion', [DiscountController::class, 'store']);
        Route::put('promotion/{id}', [DiscountController::class, 'update']);
        Route::delete('de-promotion/{id}', [DiscountController::class, 'destroy']);

        Route::get('/summary', [RevenueController::class, 'summary']);
        Route::get('/range', [RevenueController::class, 'byDateRange']);
        Route::get('/month', [RevenueController::class, 'byMonth']);
        Route::get('/quarter', [RevenueController::class, 'byQuarter']);
        Route::get('/year', [RevenueController::class, 'byYear']);

        Route::get('/size', [SizeController::class, 'show']);
        Route::post('/size', [SizeController::class, 'update']);

        Route::get('/users', [AdminController::class, 'index']);
        Route::post('/users', [AdminController::class, 'store']);
        Route::put('/users/{id}', [AdminController::class, 'update']);
        Route::delete('/users/{id}', [AdminController::class, 'destroy']);

        Route::delete('/ratings/{id}', [RatingController::class, 'destroy']);

        Route::get('/event', [EventController::class, 'show']);

        Route::prefix('content-chat')->group(function () {
            Route::get('/', [ContentController::class, 'index']);
            Route::post('/', [ContentController::class, 'store']);
            Route::put('/{id}', [ContentController::class, 'update']);
            Route::delete('/{id}', [ContentController::class, 'destroy']);
        });
    });
});


