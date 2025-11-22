<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Template;

class TemplateController extends Controller
{
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

        $base64 = null;

        // Chỉ xử lý nếu frame là tên file (giả sử tên file < 200 ký tự)
        if (strlen($template->frame) < 200) {
            $path = storage_path('app/public/frames/' . $template->frame);
            if (file_exists($path)) {
                $base64 = base64_encode(file_get_contents($path));
            }
        }

        if (!$base64) {
            return response()->json(['error' => 'Không thể tải khung ảnh'], 404);
        }

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

        // Bước 1: Đếm top 5 id_frame từ bảng pays theo id_admin
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

        // Lấy danh sách id_frame để truy vấn template
        $frameIds = $topFrames->pluck('id_frame')->toArray();

        // Bước 2: Lấy dữ liệu template tương ứng
        $templates = Template::whereIn('id', $frameIds)->get()->keyBy('id');

        // Bước 3: Ghép kết quả và xử lý base64 cho frame
        $result = [];

        foreach ($topFrames as $item) {
            $template = $templates[$item->id_frame] ?? null;

            if (!$template || !$template->frame) {
                // Nếu không có template hoặc frame, vẫn có thể trả count = 0 hoặc bỏ qua
                continue;
            }

            $base64 = null;
            if (strlen($template->frame) < 200) { // giả sử tên file ngắn => không phải BLOB
                $path = storage_path('app/public/frames/' . $template->frame);
                if (file_exists($path)) {
                    $base64 = base64_encode(file_get_contents($path));
                }
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