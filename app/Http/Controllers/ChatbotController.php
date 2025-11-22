<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $userMessage = strtolower(trim($request->message));

        // ✅ === PHẢN HỒI NHANH (KHÔNG CẦN GỌI AI) ===
        $quickReplies = [
            // 📍 Vị trí / chi nhánh
            'địa chỉ' => "Chúng mình có 3 chi nhánh:\n📍 **SweetLens Quận 1**: 123 Nguyễn Huệ, Q1, TP.HCM\n📍 **SweetLens Quận 7**: 456 Nguyễn Thị Thập, Q7, TP.HCM\n📍 **SweetLens Đà Nẵng**: 789 Bạch Đằng, Hải Châu, Đà Nẵng",
            'ở đâu' => "Chúng mình có 3 chi nhánh:\n📍 **SweetLens Quận 1**: 123 Nguyễn Huệ, Q1, TP.HCM\n📍 **SweetLens Quận 7**: 456 Nguyễn Thị Thập, Q7, TP.HCM\n📍 **SweetLens Đà Nẵng**: 789 Bạch Đằng, Hải Châu, Đà Nẵng",
            'chi nhánh' => "Chúng mình có 3 chi nhánh:\n📍 **SweetLens Quận 1**: 123 Nguyễn Huệ, Q1, TP.HCM\n📍 **SweetLens Quận 7**: 456 Nguyễn Thị Thập, Q7, TP.HCM\n📍 **SweetLens Đà Nẵng**: 789 Bạch Đằng, Hải Châu, Đà Nẵng",
            'quận 1' => "📍 **SweetLens Quận 1**: 123 Đường Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP.HCM",
            'quận 7' => "📍 **SweetLens Quận 7**: 456 Đường Nguyễn Thị Thập, Phường Tân Phú, Quận 7, TP.HCM",
            'đà nẵng' => "📍 **SweetLens Đà Nẵng**: 789 Đường Bạch Đằng, Quận Hải Châu, Đà Nẵng",

            // ⏰ Giờ mở cửa
            'giờ mở cửa' => "Mở cửa hàng ngày từ **9:00 sáng đến 10:00 tối** 💖",
            'mở cửa' => "Mở cửa hàng ngày từ **9:00 sáng đến 10:00 tối** 💖",
            'giờ' => "Mở cửa hàng ngày từ **9:00 sáng đến 10:00 tối** 💖",
            'đóng cửa' => "Chúng mình đóng cửa lúc **10:00 tối** mỗi ngày nhé 💤",

            // 📸 Tính năng máy
            'chụp ảnh' => "Máy chụp tự động sau **10 giây**, hỗ trợ nhiều **khung hình dễ thương** 📸",
            'in ảnh' => "In ảnh **siêu tốc trong 15 giây**, chất lượng cao, hỗ trợ **khổ nhỏ & lớn** 🖨️",
            'thanh toán' => "Hỗ trợ thanh toán **không chạm** qua QR, voucher hoặc **máy đọc tiền** 💳",
            'mất bao lâu' => "Chỉ mất **khoảng 1–2 phút** để chụp & in ảnh xong! 💨",

            // 📞 Hỗ trợ
            'hỗ trợ' => "Vui lòng liên hệ nhân viên tại quầy hoặc gọi **hotline: 1900 888 666** 📞",
            'hotline' => "**Hotline**: 1900 888 666 📞",
            'liên hệ' => "📧 Email: support@sweetlens.vn\n📞 Hotline: 1900 888 666",
            'admin' => "Vui lòng đăng nhập quản trị để truy cập tính năng admin.",

            // 💬 Câu chào / chung
            'xin chào' => "Xin chào! Mình có thể giúp gì cho bạn về **SweetLens Photo Booth**? 😊",
            'chào' => "Xin chào! Mình có thể giúp gì cho bạn về **SweetLens Photo Booth**? 😊",
            'cảm ơn' => "Không có gì đâu! Chúc bạn có những **khoảnh khắc ngọt ngào** tại SweetLens 💖",
            'thank' => "You're welcome! Have a sweet moment with SweetLens! 💖",
        ];

        // 🔍 Kiểm tra từng keyword (linh hoạt: chứa từ khóa là trả lời)
        foreach ($quickReplies as $keyword => $reply) {
            if (str_contains($userMessage, $keyword)) {
                return response()->json(['reply' => $reply]);
            }
        }

        // ❓ Nếu không khớp → dùng AI (DeepSeek miễn phí)
        $stores = [
            "SweetLens Quận 1: 123 Đường Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP.HCM",
            "SweetLens Quận 7: 456 Đường Nguyễn Thị Thập, Phường Tân Phú, Quận 7, TP.HCM",
            "SweetLens Đà Nẵng: 789 Đường Bạch Đằng, Quận Hải Châu, Đà Nẵng"
        ];
        $storesInfo = implode("\n", $stores);

        $systemPrompt = "Bạn là trợ lý AI **thân thiện, ngắn gọn** của SweetLens Photo Booth.\n" .
                        "Danh sách cửa hàng:\n{$storesInfo}\n" .
                        "Hãy trả lời **ngắn gọn trong 1–2 câu**. Nếu hỏi về vị trí, dùng đúng địa chỉ trên.\n" .
                        "Không bịa thông tin. Nếu không biết, nói: 'Mình chỉ hỗ trợ các chi nhánh đã liệt kê.'\n" .
                        "Tránh dùng markdown, chỉ dùng text thuần.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'HTTP-Referer' => config('app.url') ?: 'http://localhost',
                'X-Title' => 'SweetLens Photobooth',
                'Content-Type' => 'application/json',
            ])->timeout(25)->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'deepseek/deepseek-r1-distill-llama-70b:free',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $request->message]
                ]
            ]);

            if ($response->failed()) {
                Log::error('OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json([
                    'reply' => 'Mình đang bận một chút! Vui lòng thử lại sau vài giây 💖'
                ]);
            }

            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? 'Xin lỗi, mình chưa hiểu câu hỏi này.';

            return response()->json([
                'reply' => trim($reply)
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot exception', ['message' => $e->getMessage()]);
            return response()->json([
                'reply' => 'Mình đang bận một chút! Vui lòng thử lại sau vài giây 💖'
            ]);
        }
    }
}