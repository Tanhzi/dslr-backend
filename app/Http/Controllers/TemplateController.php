<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
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

    // Tải nội dung file từ Supabase dưới dạng binary
    protected function downloadFileFromSupabase(string $filePath)
    {
        $url = $this->getPublicUrl($filePath);
        return Http::withOptions(['timeout' => 30])->get($url);
    }

    public function getFrameImage(Request $request)
    {
        $frameId = $request->query('id');

        if (!$frameId) {
            return response()->json(['error' => 'Thiếu tham số id'], 400);
        }

        $template = DB::table('template')->where('id', $frameId)->first();

        if (!$template || !$template->frame) {
            return response()->json(['error' => 'Không tìm thấy khung ảnh'], 404);
        }

        // Giả sử $template->frame là đường dẫn tương đối (vd: frames/xyz.png)
        $response = $this->downloadFileFromSupabase($template->frame);

        if ($response->failed()) {
            Log::error("Tải khung ảnh từ Supabase thất bại", [
                'frame_id' => $frameId,
                'path' => $template->frame,
                'error' => $response->body()
            ]);
            return response()->json(['error' => 'Không thể tải khung ảnh'], 404);
        }

        $base64 = base64_encode($response->body());
        $imageUrl = 'data:image/png;base64,' . $base64;

        return response()->json([
            'image_url' => $imageUrl
        ]);
    }

    public function getTopFramesByAdmin(Request $request)
    {
        $id_admin = $request->query('id_admin');

        if (!$id_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thiếu tham số bắt buộc: id_admin'
            ], 400);
        }

        $topFrames = DB::table('pays')
            ->select('id_frame', DB::raw('COUNT(*) as usage_count'))
            ->where('id_admin', $id_admin)
            ->whereNotNull('id_frame')
            ->groupBy('id_frame')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        if ($topFrames->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        }

        $frameIds = $topFrames->pluck('id_frame')->toArray();

        // Dùng DB::table thay vì Eloquent để đồng nhất với getFrameImage
        $templates = DB::table('template')
            ->whereIn('id', $frameIds)
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($topFrames as $item) {
            $template = $templates[$item->id_frame] ?? null;

            if (!$template || !$template->frame) {
                continue;
            }

            $response = $this->downloadFileFromSupabase($template->frame);

            $base64 = null;
            if ($response->successful()) {
                $base64 = base64_encode($response->body());
            } else {
                Log::warning("Không tải được ảnh khung trong top frames", [
                    'id_frame' => $item->id_frame,
                    'path' => $template->frame
                ]);
            }

            $result[] = [
                'id_frame' => (int) $item->id_frame,
                'usage_count' => (int) $item->usage_count,
                'frame' => $base64 ? 'data:image/png;base64,' . $base64 : null,
                'type' => $template->type ?? '',
                'cuts' => $template->cuts ?? ''
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $result
        ]);
    }
}