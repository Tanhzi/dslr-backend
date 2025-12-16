<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    public function handleChat(Request $request)
    {
        $question = $request->input('question');

        if (!$question) {
            return response()->json(['error' => 'Thiếu câu hỏi'], 400);
        }

        try {
            $response = Http::timeout(30)->post('http://localhost:5001/chat', [
                'question' => $question
            ]);

            if (!$response->successful()) {
                Log::error('AI chat server error', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['error' => 'AI tạm không phản hồi'], 500);
            }

            $data = $response->json();

            if (!isset($data['answer'])) {
                return response()->json(['error' => 'Định dạng phản hồi không hợp lệ'], 500);
            }

            return response()->json([
                'answer' => $data['answer'],
                'time' => $data['time'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Lỗi gọi AI chatbot', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Lỗi kết nối trợ lý AI'], 500);
        }
    }
}