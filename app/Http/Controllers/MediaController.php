<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class MediaController extends Controller
{
    protected string $bucket = 'event-assets';
    protected string $supabaseUrl;
    protected string $supabaseKey;

    public function __construct()
    {
        $this->supabaseUrl = env('SUPABASE_URL');
        $this->supabaseKey = env('SUPABASE_KEY');
    }

    protected function getPublicUrl(string $filePath): string
    {
        return $this->supabaseUrl . '/storage/v1/object/public/' . $this->bucket . '/' . $filePath;
    }

    protected function uploadToSupabase(string $filePath, string $contents, string $mimeType)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->supabaseKey,
            'apikey' => $this->supabaseKey,
            'Content-Type' => $mimeType,
        ])->withBody($contents, $mimeType)->post($url);
    }

    protected function downloadFromSupabase(string $filePath)
    {
        $url = $this->getPublicUrl($filePath);
        return Http::withOptions(['timeout' => 30])->get($url);
    }

    // API upload media (ảnh gốc + QR)
    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'session_id' => 'required|string',
            'id_admin' => 'nullable|integer',
            'download_link' => 'nullable|url',
        ]);

        $files = $request->input('files');
        $sessionId = $request->session_id;
        $idAdmin = $request->id_admin;
        $downloadLink = $request->download_link;

        $results = [];
        $errors = [];

        foreach ($files as $index => $file) {
            if (!isset($file['data']) || !isset($file['type'])) {
                $errors[] = "File at index $index is missing 'data' or 'type'.";
                continue;
            }

            $fileData = $file['data'];
            $fileType = $file['type'];

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

            // Tạo tên file
            $fileName = $sessionId . '_' . $fileType . '_' . Str::random(10) . '.' . $extension;
            $relativePath = 'media/' . $fileName;

            // Upload lên Supabase
            $mimeType = "image/{$extension}";
            $response = $this->uploadToSupabase($relativePath, $decodedData, $mimeType);

            if ($response->failed()) {
                Log::error("Supabase upload media index $index failed", $response->json());
                $errors[] = "Upload to Supabase failed for file $index";
                continue;
            }

            // Lưu vào DB
            $media = Media::create([
                'file_path' => $relativePath,
                'file_type' => $fileType,
                'id_admin' => $idAdmin,
                'session_id' => $sessionId,
                'link' => $downloadLink,
                'created_at' => now(),
            ]);

            $results[] = [
                'type' => $fileType,
                'status' => 'success',
                'path' => $relativePath,
                'url' => $this->getPublicUrl($relativePath),
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

    // Lấy media theo session_id
    public function showBySession(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        $mediaItems = Media::where('session_id', $request->session_id)
            ->get()
            ->map(function ($item) {
                return [
                    'file_type' => $item->file_type,
                    'url' => $this->getPublicUrl($item->file_path),
                ];
            });

        return response()->json($mediaItems);
    }

    public function showDownloadPage(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response('Lỗi: Không tìm thấy ID phiên chụp.', 400)
                ->header('Content-Type', 'text/html; charset=utf-8');
        }
        return view('download', ['sessionId' => $sessionId]);
    }

    // Lấy QR và link
    public function getQrBySession(Request $request)
    {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json(['error' => 'Thiếu session_id'], 400);
        }

        $qrRecord = Media::where('session_id', $sessionId)
            ->where('file_type', 'qr')
            ->first();

        if (!$qrRecord) {
            return response()->json(['error' => 'Không tìm thấy mã QR'], 404);
        }

        // Trả public URL (không cần base64 nữa)
        return response()->json([
            'qr_image_url' => $this->getPublicUrl($qrRecord->file_path),
            'qr_link' => $qrRecord->link ?? ''
        ]);
    }

    // Gửi QR qua email
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
            ->first();

        if (!$qrMedia) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy mã QR cho session này.'
            ], 404);
        }

        // TẢI QR TỪ SUPABASE VỀ ĐỂ GỬI EMAIL
        $qrResponse = $this->downloadFromSupabase($qrMedia->file_path);
        if ($qrResponse->failed()) {
            Log::error("Tải QR từ Supabase thất bại", [
                'path' => $qrMedia->file_path,
                'error' => $qrResponse->body()
            ]);
            return response()->json(['status' => 'error', 'message' => 'Không tải được QR'], 500);
        }

        $qrBase64 = base64_encode($qrResponse->body());
        $downloadLink = $qrMedia->link ?? url("/download?session_id={$sessionId}");

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

        try {
            $client = new Client([
                'base_uri' => 'https://api.brevo.com/v3/',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Api-Key' => config('services.brevo.api_key'),
                ],
            ]);

            $response = $client->post('smtp/email', [
                'json' => [
                    'sender' => ['name' => 'SweetLens', 'email' => 'sweetlensp@gmail.com'],
                    'to' => [['email' => $email]],
                    'subject' => '📸 Ảnh của bạn đã sẵn sàng để tải về!',
                    'htmlContent' => $html,
                    'attachment' => [[
                        'name' => 'qr-tai-anh.png',
                        'content' => $qrBase64,
                    ]],
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                return response()->json([
                    'status' => 'success',
                    'message' => "Đã gửi email chứa QR và link tải ảnh đến {$email}"
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Gửi email QR thất bại: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gửi email thất bại: ' . $e->getMessage()
            ], 500);
        }
    }

    // Gửi ảnh gốc qua email (không thay đổi logic — vì ảnh gốc truyền thẳng từ frontend)
    public function sendOriginalImagesEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'session_id' => 'required|string',
            'images' => 'required|array|min:1',
            'images.*' => 'string',
        ], [
            'images.required' => 'Vui lòng chọn ít nhất một ảnh để gửi.',
            'images.*.string' => 'Ảnh không hợp lệ (phải là data URL).',
        ]);

        $email = $request->email;
        $sessionId = $request->session_id;
        $imagesBase64 = $request->images;

        $attachments = [];
        foreach ($imagesBase64 as $index => $base64) {
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
                continue;
            }
            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') $extension = 'jpg';

            $data = substr($base64, strpos($base64, ',') + 1);
            $decoded = base64_decode($data, true);
            if ($decoded === false) continue;

            $filename = "image_{$index}.{$extension}";
            $attachments[] = [
                'name' => $filename,
                'content' => base64_encode($decoded),
            ];
        }

        if (empty($attachments)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không có ảnh hợp lệ để gửi.'
            ], 400);
        }

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 10px;'>
                <h2 style='color: #e91e63; text-align: center;'>✨ Ảnh gốc của bạn!</h2>
                <p>Xin chào,</p>
                <p>Cảm ơn bạn đã sử dụng dịch vụ! Dưới đây là ảnh gốc bạn yêu cầu (không được lưu trên hệ thống).</p>
                <p>Chúc bạn một ngày tuyệt vời! ❤️</p>
                <hr style='margin: 20px 0; border: 0; border-top: 1px solid #eee;' />
                <p style='font-size: 12px; color: #777;'>Ảnh này chỉ được gửi qua email và không được lưu trữ.</p>
            </div>
        ";

        $client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => config('services.brevo.api_key'),
            ],
        ]);

        try {
            $response = $client->post('smtp/email', [
                'json' => [
                    'sender' => ['name' => 'SweetLens', 'email' => 'sweetlensp@gmail.com'],
                    'to' => [['email' => $email]],
                    'subject' => '📸 Ảnh gốc của bạn (không lưu trên web)',
                    'htmlContent' => $html,
                    'attachment' => $attachments,
                ],
            ]);

            if ($response->getStatusCode() === 201) {
                return response()->json([
                    'status' => 'success',
                    'message' => "Đã gửi ảnh gốc đến {$email} và không lưu lên web."
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gửi email thất bại: Mã trạng thái ' . $response->getStatusCode()
            ], 500);

        } catch (\Exception $e) {
            Log::error("Gửi ảnh gốc qua email thất bại: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gửi email thất bại: ' . $e->getMessage()
            ], 500);
        }
    }
}