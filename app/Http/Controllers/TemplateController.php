<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

        // ✅ TRẢ TRỰC TIẾP PUBLIC URL — KHÔNG CẦN TẢI ẢNH!
        $frameUrl = $this->getPublicUrl($template->frame);

        $result[] = [
            'id_frame' => (int) $item->id_frame,
            'usage_count' => (int) $item->usage_count,
            'frame' => $frameUrl, // ← ĐÂY LÀ URL, KHÔNG PHẢI BASE64
            'type' => $template->type ?? '',
            'cuts' => $template->cuts ?? ''
        ];
    }

    return response()->json([
        'status' => 'success',
        'data' => $result
    ]);
}

/**
 * Lấy danh sách khung ảnh theo id_admin, id_topic và cuts
 */
public function getFramesByFilters(Request $request)
{
    $id_admin = $request->query('id_admin');
    $id_topic = $request->query('id_topic');
    $cuts = $request->query('cuts');
    $page = (int) $request->query('page', 1);
    $limit = (int) $request->query('limit', 50);

    if (!$id_admin) {
        return response()->json([
            'status' => 'error',
            'message' => 'Thiếu tham số bắt buộc: id_admin'
        ], 400);
    }

    $cacheKey = 'frames_filters_' . $id_admin . '_' . ($id_topic ?: '0') . '_' . ($cuts ?: 'all') . '_p' . $page . '_l' . $limit;
    $result = Cache::remember($cacheKey, 300, function () use ($id_admin, $id_topic, $cuts, $page, $limit) {
        $query = DB::table('template')
            ->leftJoin('event', 'template.id_topic', '=', 'event.id')
            ->where('template.id_admin', $id_admin)
            ->select(
                'template.id',
                'template.frame',
                'template.type',
                'template.cuts',
                'template.id_topic',
                'event.name as event_name'
            );

        if ($id_topic) {
            $query->where('template.id_topic', $id_topic);
        }

        if ($cuts && $cuts !== 'all') {
            $query->where('template.cuts', $cuts);
        }

        $total = $query->count();
        $frames = $query->orderBy('template.id')->offset(($page - 1) * $limit)->limit($limit)->get();

        $data = $frames->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'frame' => $item->frame ? $this->getPublicUrl($item->frame) : null,
                'type' => $item->type ?? '',
                'cuts' => $item->cuts ? (string) $item->cuts : '',
                'id_topic' => $item->id_topic ? (int) $item->id_topic : null,
                'event_name' => $item->event_name ?? 'Chưa có sự kiện',
            ];
        });

        return ['data' => $data, 'total' => $total, 'total_pages' => ceil($total / $limit)];
    });

    return response()->json([
        'status' => 'success',
        'data' => $result['data'],
        'total' => $result['total'],
        'total_pages' => $result['total_pages'],
    ]);
}
}