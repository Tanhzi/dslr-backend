<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FrameController extends Controller
{
    public function index(Request $request)
    {
        $id_admin = $request->query('id_admin');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $cuts = $request->query('cuts');

        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu id_admin'], 400);
        }

        $offset = ($page - 1) * $limit;

        // LƯU Ý: select 'template.frame' (không phải frame_path)
        $query = DB::table('template')
            ->leftJoin('event', 'template.id_topic', '=', 'event.id')
            ->where('template.id_admin', $id_admin)
            ->select('template.id', 'template.frame', 'template.type', 'template.cuts', 'template.id_topic', 'event.name as event_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('template.type', 'like', "%{$search}%")
                  ->orWhere('event.name', 'like', "%{$search}%");
            });
        }

        if ($cuts && $cuts !== 'all') {
            $query->where('template.cuts', $cuts);
        }

        $total = $query->count();
        $frames = $query->orderBy('template.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Chuyển đường dẫn thành URL công khai
$frames = $frames->map(function ($item) {
    if (!$item->frame) {
        return [
            'id' => (int) $item->id,
            'frame' => null,
            'type' => $item->type ?? '',
            'cuts' => $item->cuts ? (string) $item->cuts : '',
            'id_topic' => $item->id_topic ? (int) $item->id_topic : null,
            'event_name' => $item->event_name ?? 'Chưa có sự kiện',
        ];
    }

    // ✅ Lấy tên file từ đường dẫn (frame = "frames/frame_xxx.png")
    $filename = basename($item->frame);
    // ✅ Tạo URL qua route API: /api/frame-image/frame_xxx.png
    $frameUrl = url('/api/frame-image-client/' . rawurlencode($filename));

    return [
        'id' => (int) $item->id,
        'frame' => $frameUrl, // ← DÙNG URL NÀY
        'type' => $item->type ?? '',
        'cuts' => $item->cuts ? (string) $item->cuts : '',
        'id_topic' => $item->id_topic ? (int) $item->id_topic : null,
        'event_name' => $item->event_name ?? 'Chưa có sự kiện',
    ];
});

        return response()->json([
            'status' => 'success',
            'data' => $frames,
        ]);
    }
}