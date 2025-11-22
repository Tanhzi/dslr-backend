<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class MediaController extends Controller
{
    // API upload media (thay thế upload.php)
public function store(Request $request)
{
    $request->validate([
        'files' => 'required|array|min:1',
        'session_id' => 'required|string',
        'id_admin' => 'nullable|integer',
        'download_link' => 'nullable|url',
    ]);

    $files = $request->input('files'); // JSON payload
    $sessionId = $request->session_id;
    $idAdmin = $request->id_admin;
    $downloadLink = $request->download_link;

    $uploadDir = 'uploads/';
    $results = [];
    $errors = [];

    foreach ($files as $index => $file) {
        if (!isset($file['data']) || !isset($file['type'])) {
            $errors[] = "File at index $index is missing 'data' or 'type'.";
            continue;
        }

        $fileData = $file['data'];
        $fileType = $file['type'];

        // Xử lý base64
        if (!preg_match('/^data:image\/(\w+);base64,/', $fileData, $matches)) {
            $errors[] = "File at index $index has invalid base64 header.";
            continue;
        }

        $extension = strtolower($matches[1]);
        $fileData = substr($fileData, strpos($fileData, ',') + 1);
        $decodedData = base64_decode($fileData);

        if ($decodedData === false) {
            $errors[] = "Failed to decode base64 for file at index $index.";
            continue;
        }

        // Chuẩn bị đường dẫn (cho preview)
        $fileName = $sessionId . '_' . $fileType . '_' . Str::random(10) . '.' . $extension;
        $relativePath = $uploadDir . $fileName;

        // Lưu file vào disk (cho tất cả loại, kể cả QR — nếu bạn vẫn muốn preview)
        Storage::disk('public')->makeDirectory($uploadDir);
        Storage::disk('public')->put($relativePath, $decodedData);

        // Xử lý QR: lưu binary vào cột `qr`, các file khác để null
        $qrBinary = ($fileType === 'qr') ? $decodedData : null;

        // Lưu vào DB
        $media = Media::create([
            'file_path' => $relativePath,         // đường dẫn để preview
            'file_type' => $fileType,
            'id_admin' => $idAdmin,
            'session_id' => $sessionId,
            'qr' => $qrBinary,                   // 👈 BINARY DATA (BLOB)
            'link' => $downloadLink,
            'created_at' => now(),
        ]);

        $results[] = [
            'type' => $fileType,
            'status' => 'success',
            'path' => $relativePath,
            'qr_stored' => ($fileType === 'qr'),
            'link' => $downloadLink,
            'url' => Storage::disk('public')->url($relativePath),
        ];
    }

    return response()->json([
        'message' => 'Upload và lưu database thành công.',
        'results' => $results,
        'errors' => $errors,
        'session_id' => $sessionId,
        'download_link' => $downloadLink,
    ]);
}

    // API lấy media theo session_id (thay thế get_session_media.php)
    public function showBySession(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        $mediaItems = Media::where('session_id', $request->session_id)
            ->get()
            ->map(function ($item) {
                return [
                    'file_type' => $item->file_type,
                    'url' => Storage::disk('public')->url($item->file_path),
                ];
            });

        return response()->json($mediaItems);
    }

    // Trong MediaController.php
    public function showDownloadPage(Request $request)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            return response('Lỗi: Không tìm thấy ID phiên chụp.', 400)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        return view('download', ['sessionId' => $sessionId]);
    }

    //lấy qr và link
public function getQrBySession(Request $request)
{
    $sessionId = $request->query('session_id');

    if (!$sessionId) {
        return response()->json(['error' => 'Thiếu session_id'], 400);
    }

    $qrRecord = Media::where('session_id', $sessionId)
        ->where('file_type', 'qr')
        ->first();

    if (!$qrRecord || !$qrRecord->qr) {
        return response()->json(['error' => 'Không tìm thấy mã QR'], 404);
    }

    // ✅ SỬA DÒNG NÀY: thêm 'data:' ở đầu
    $base64 = base64_encode($qrRecord->qr);
    $qrImageUrl = 'data:image/png;base64,' . $base64; // ← ĐÂY MỚI LÀ DATA URL HỢP LỆ

    return response()->json([
        'qr_image_url' => $qrImageUrl,
        'qr_link' => $qrRecord->link ?? ''
    ]);
}
public function sendQrEmail(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'session_id' => 'required|string',
    ]);

    $email = $request->email;
    $sessionId = $request->session_id;

    $qrMedia = Media::where('session_id', $sessionId)
        ->where('file_type', 'qr')
        ->whereNotNull('file_path')
        ->first();

    if (!$qrMedia) {
        return response()->json([
            'status' => 'error',
            'message' => 'Không tìm thấy mã QR cho session này.'
        ], 404);
    }

    $downloadLink = $qrMedia->link ?? url("/download?session_id={$sessionId}");
    $qrFilePath = storage_path('app/public/' . $qrMedia->file_path);

    if (!file_exists($qrFilePath)) {
        return response()->json([
            'status' => 'error',
            'message' => 'File QR không tồn tại trên server.'
        ], 500);
    }

    // ✅ SỬA TỪ ĐÂY TRỞ XUỐNG
    $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px;'>
            <h2 style='color: #e91e63; text-align: center;'>✨ Ảnh của bạn đã sẵn sàng!</h2>
            <p>Xin chào,</p>
            <p>Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi! 🎉</p>
            <p>Bạn có thể tải ảnh của mình bằng cách:</p>
            <ul>
                <li>Quét mã QR trong file đính kèm</li>
                <li>Hoặc nhấn vào <a href='{$downloadLink}' style='color: #8a2be2; font-weight: bold;'>link này</a></li>
            </ul>
            <p>Chúc bạn một ngày tuyệt vời! ❤️</p>
            <hr style='margin: 20px 0; border: 0; border-top: 1px solid #eee;' />
            <p style='font-size: 12px; color: #777;'>
                Link có hiệu lực trong 24 giờ.<br/>
                Nếu bạn không yêu cầu, vui lòng bỏ qua email này.
            </p>
        </div>
    ";

    Mail::html($html, function ($message) use ($email, $qrFilePath) {
        $message->to($email)
                ->subject('📸 Ảnh của bạn đã sẵn sàng để tải về!')
                ->attach($qrFilePath, [
                    'as' => 'qr-tai-anh.png',
                    'mime' => 'image/png',
                ]);
    });

    return response()->json([
        'status' => 'success',
        'message' => "Đã gửi email chứa QR và link tải ảnh đến {$email}"
    ]);
}
public function sendOriginalImagesEmail(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'session_id' => 'required|string',
        'images' => 'required|array|min:1',
        'images.*' => 'string', // base64 strings
    ]);

    $email = $request->email;
    $sessionId = $request->session_id;
    $imagesBase64 = $request->images;

    // Thư mục tạm để lưu ảnh gốc (sẽ xóa sau khi gửi email)
    $tempDir = storage_path('app/temp/' . $sessionId);
    if (!File::exists($tempDir)) {
        File::makeDirectory($tempDir, 0755, true);
    }

    $attachedFiles = [];

    foreach ($imagesBase64 as $index => $base64) {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            continue; // skip invalid
        }

        $extension = strtolower($matches[1]);
        $data = substr($base64, strpos($base64, ',') + 1);
        $decoded = base64_decode($data);

        if ($decoded === false) continue;

        $filename = "image_{$index}.{$extension}";
        $path = $tempDir . '/' . $filename;
        file_put_contents($path, $decoded);
        $attachedFiles[] = $path;
    }

    if (empty($attachedFiles)) {
        return response()->json(['error' => 'Không có ảnh hợp lệ để gửi.'], 400);
    }

    // Gửi email
    $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px;'>
            <h2 style='color: #e91e63; text-align: center;'>✨ Ảnh của bạn đã sẵn sàng!</h2>
            <p>Xin chào,</p>
            <p>Cảm ơn bạn đã sử dụng dịch vụ! Dưới đây là ảnh gốc bạn yêu cầu (không được lưu trên hệ thống).</p>
            <p>Chúc bạn một ngày tuyệt vời! ❤️</p>
            <hr style='margin: 20px 0; border: 0; border-top: 1px solid #eee;' />
            <p style='font-size: 12px; color: #777;'>Ảnh này chỉ được gửi qua email và không được lưu trữ.</p>
        </div>
    ";

    try {
        Mail::html($html, function ($message) use ($email, $attachedFiles) {
            $message->to($email)
                    ->subject('📸 Ảnh gốc của bạn (không lưu trên web)')
                    ->bcc('sweetlensp@gmail.com'); // optional: gửi bản sao cho bạn

            foreach ($attachedFiles as $file) {
                $message->attach($file);
            }
        });

        // Xóa thư mục tạm sau khi gửi
        File::deleteDirectory($tempDir);

        return response()->json([
            'status' => 'success',
            'message' => "Đã gửi ảnh gốc đến {$email} và không lưu lên web."
        ]);
    } catch (\Exception $e) {
        // Dọn dẹp nếu gửi thất bại
        File::deleteDirectory($tempDir);
        return response()->json([
            'status' => 'error',
            'message' => 'Gửi email thất bại: ' . $e->getMessage()
        ], 500);
    }
}
}