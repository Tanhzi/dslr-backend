<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private const MAX_HISTORY = 10;
    private const MAX_KNOWLEDGE_CHARS = 6000;

    /**
     * Trả lời câu hỏi chatbot bằng OpenRouter.
     * Trước khi gọi model, lấy dữ liệu chatbot do admin cấu hình (content_chat) làm ngữ cảnh.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'id_admin' => 'nullable|integer',
            'history' => 'nullable|array|max:20',
        ]);

        $question = trim($request->input('question'));
        $knowledge = $this->getKnowledge($request->input('id_admin'));

        $systemPrompt = $this->buildSystemPrompt($knowledge);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ((array) $request->input('history', []) as $msg) {
            if (!is_array($msg)) continue;
            $role = $msg['role'] ?? '';
            $content = isset($msg['content']) ? trim((string) $msg['content']) : '';
            if ($content === '' || !in_array($role, ['user', 'assistant'], true)) continue;
            $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 1000)];
            if (count($messages) >= self::MAX_HISTORY + 1) break;
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $apiKey = config('services.openrouter.api_key');
        if (!$apiKey) {
            Log::warning('OPENROUTER_API_KEY chưa được cấu hình trong .env');
            return response()->json([
                'answer' => 'Trợ lý AI đang được cấu hình. Vui lòng thử lại sau.',
                'time' => 0,
            ]);
        }

        $start = microtime(true);

        $models = array_values(array_filter(array_map('trim', explode(',', (string) config('services.openrouter.model')))));
        if (empty($models)) {
            $models = ['poolside/laguna-s-2.1:free'];
        }

        try {
            $client = new Client();
            $answer = null;
            $lastError = null;

            // 2 lượt: free tier của OpenRouter hay 429 tạm thời (shared pool) —
            // hết model ở lượt 1 thì chờ 2s và chạy lại toàn bộ chain.
            for ($round = 0; $round < 2 && !$answer; $round++) {
                if ($round > 0) {
                    sleep(2);
                }

                foreach ($models as $model) {
                    try {
                        $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                                'HTTP-Referer' => config('app.url'),
                                'X-Title' => 'SweetLens Photo Booth',
                            ],
                            'json' => [
                                'model' => $model,
                                'messages' => $messages,
                                'max_tokens' => 500,
                                'temperature' => 0.7,
                            ],
                            'timeout' => 30,
                            'http_errors' => false,
                        ]);

                        $status = $response->getStatusCode();
                        $data = json_decode($response->getBody()->getContents(), true);
                        $content = $data['choices'][0]['message']['content'] ?? null;

                        if ($status === 200 && is_string($content) && trim($content) !== '') {
                            $answer = trim($content);
                            break;
                        }

                        $lastError = 'model ' . $model . ' status=' . $status . ' ' . json_encode($data['error'] ?? ($content === null ? 'rỗng' : 'rỗng nội dung'));
                        Log::warning('OpenRouter model thất bại, thử model kế tiếp', ['model' => $model, 'round' => $round, 'status' => $status, 'error' => $data['error'] ?? null]);

                        // 429 = rate-limit tạm thời → model sau cũng dễ dính, nghỉ chút rồi thử tiếp
                        if ($status === 429) {
                            usleep(500000);
                        }
                    } catch (\Exception $e) {
                        $lastError = 'model ' . $model . ': ' . $e->getMessage();
                        Log::warning('OpenRouter model lỗi, thử model kế tiếp', ['model' => $model, 'round' => $round, 'error' => $e->getMessage()]);
                    }
                }
            }

            if (!$answer) {
                Log::warning('OpenRouter không trả lời được từ model nào', ['last' => $lastError]);
                $answer = 'Xin lỗi, trợ lý AI chưa thể trả lời lúc này. Vui lòng thử lại sau.';
            }

            return response()->json([
                'answer' => $answer,
                'time' => round(microtime(true) - $start, 2),
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi gọi OpenRouter: ' . $e->getMessage());
            return response()->json([
                'answer' => 'Xin lỗi, trợ lý AI đang bận. Vui lòng thử lại sau ít phút.',
                'time' => round(microtime(true) - $start, 2),
            ]);
        }
    }

    /**
     * Lấy dữ liệu chatbot do admin cấu hình (bảng content_chat), cache 10 phút.
     */
    private function getKnowledge($idAdmin): string
    {
        if (!$idAdmin) {
            return '';
        }

        $rows = Cache::remember(
            'chat_context_' . $idAdmin,
            600,
            fn () => DB::table('content_chat')
                ->where('id_admin', $idAdmin)
                ->orderBy('id')
                ->get(['title', 'content'])
                ->toArray()
        );

        if (empty($rows)) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            $title = trim((string) $row->title);
            $content = trim((string) $row->content);
            if ($content === '') continue;
            $lines[] = ($title !== '' ? $title . ': ' : '') . $content;
        }

        $knowledge = implode("\n", $lines);

        if (mb_strlen($knowledge) > self::MAX_KNOWLEDGE_CHARS) {
            $knowledge = mb_substr($knowledge, 0, self::MAX_KNOWLEDGE_CHARS) . '...';
        }

        return $knowledge;
    }

    private function buildSystemPrompt(string $knowledge): string
    {
        $prompt = "Bạn là trợ lý AI của SweetLens Photo Booth - hệ thống máy chụp ảnh tự động.\n"
            . "Bạn trả lời cho khách hàng tại máy chụp bằng tiếng Việt, ngắn gọn (1-2 câu, tối đa 3 câu), văn bản thuần (không dùng markdown).\n";

        if ($knowledge !== '') {
            $prompt .= "\nDữ liệu về quán do quản trị viên cấu hình (nguồn thông tin chính):\n"
                . $knowledge . "\n";
        }

        $prompt .= "\nQuy tắc:\n"
            . "- Chỉ trả lời dựa trên dữ liệu trên; nếu không có thông tin thì lịch sự hướng dẫn khách liên hệ nhân viên tại quán.\n"
            . "- Không bịa địa chỉ, giá tiền, giờ mở cửa nếu không có trong dữ liệu.\n"
            . "- Nếu khách hỏi về dịch vụ chụp ảnh, giá, sự kiện: trả lời dựa trên dữ liệu đã cung cấp.\n"
            . "- Chỉ trả lời các vấn đề liên quan đến SweetLens và dịch vụ chụp ảnh.";

        return $prompt;
    }
}
