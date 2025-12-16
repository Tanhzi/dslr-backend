<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiImageController extends Controller
{
    // ================ ADMIN-GRADE API (NHƯNG MỞ CÔNG KHAI) ================
public function generate(Request $request)
{
    $prompt = $request->input('prompt');
    if (!$prompt) {
        return response()->json(['success' => false, 'error' => 'Prompt is required'], 400);
    }

    try {
        $client = new \GuzzleHttp\Client();

        $response = $client->post("http://127.0.0.1:5000/admin-generate-image", [
            'json' => ['prompt' => $prompt], // 👈 GỬI DƯỚI DẠNG JSON
            'timeout' => 60,
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return response()->json($data, $response->getStatusCode());

    } catch (\Exception $e) {
        Log::error("AI Generate Error", ['error' => $e->getMessage()]);
        return response()->json(['success' => false, 'error' => 'Lỗi tạo ảnh AI'], 500);
    }
}

    // ================ USER-FACING AI APIs (CÔNG KHAI) ================

    public function animeStyle(Request $request)
    {
        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'Thiếu file ảnh'], 400);
        }
        return $this->proxyToAiServer($request, 'anime-style');
    }

    public function enhance(Request $request)
    {
        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'Thiếu file ảnh'], 400);
        }
        return $this->proxyToAiServer($request, 'enhance');
    }

    public function userGenerateTarget(Request $request)
    {
        if (!$request->hasFile('image') || !$request->filled('prompt')) {
            return response()->json(['error' => 'Thiếu file ảnh hoặc prompt'], 400);
        }
        return $this->proxyToAiServer($request, 'user-generate-target');
    }

    public function faceSwap(Request $request)
    {
        if (!$request->hasFile('source') || !$request->hasFile('target')) {
            return response()->json(['error' => 'Thiếu file: source và target'], 400);
        }
        return $this->proxyToAiServer($request, 'face-swap');
    }

    public function backgroundAi(Request $request)
    {
        if (!$request->hasFile('foreground') || !$request->hasFile('background')) {
            return response()->json(['error' => 'Thiếu file: foreground và background'], 400);
        }
        return $this->proxyToAiServer($request, 'background-ai');
    }

    // ================ PRIVATE HELPER ================

    private function proxyToAiServer(Request $request, string $endpoint)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $multipart = [];

            foreach ($request->allFiles() as $name => $file) {
                $multipart[] = [
                    'name' => $name,
                    'contents' => fopen($file->getRealPath(), 'r'),
                    'filename' => $file->getClientOriginalName(),
                ];
            }

            foreach ($request->all() as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    $multipart[] = ['name' => $key, 'contents' => $value];
                }
            }

            $response = $client->post("http://127.0.0.1:5000/{$endpoint}", [
                'multipart' => $multipart,
                'timeout' => 60,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return response()->json($data, $response->getStatusCode());

        } catch (\Exception $e) {
            Log::error("AI Proxy Error ({$endpoint})", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Lỗi xử lý AI'], 500);
        }
    }
}