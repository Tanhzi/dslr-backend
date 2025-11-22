<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TemplateFrameController extends Controller
{
public function index(Request $request)
{
    $id_admin = $request->query('id_admin');
    $id_topic = $request->query('id_topic');
    $cuts = $request->query('cuts');

    // Kiểm tra bắt buộc: id_admin, id_topic, cuts
    if (!$id_admin || !$id_topic || !$cuts) {
        return response()->json([
            'status' => 'error',
            'message' => 'Thiếu tham số bắt buộc: id_admin, id_topic hoặc cuts'
        ], 400);
    }

    $page = $request->query('page', 1);
    $limit = $request->query('limit', 10);
    $search = $request->query('search');

    $offset = ($page - 1) * $limit;

    $query = DB::table('template')
        ->leftJoin('event', 'template.id_topic', '=', 'event.id')
        ->where('template.id_admin', $id_admin)
        ->where('template.id_topic', $id_topic)
        ->where('template.cuts', $cuts)
        ->select('template.id', 'template.frame', 'template.type', 'template.cuts', 'template.id_topic', 'event.name as event_name');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('template.type', 'like', "%{$search}%")
              ->orWhere('event.name', 'like', "%{$search}%");
        });
    }

    $total = $query->count();
    $frames = $query->orderBy('template.id', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();

    $frames = $frames->map(function ($item) {
        $base64 = null;

        // Chỉ xử lý file từ storage (loại bỏ hoàn toàn logic BLOB cũ)
        if ($item->frame && strlen($item->frame) < 200) {
            $path = storage_path('app/public/frames/' . $item->frame);
            if (file_exists($path)) {
                $base64 = base64_encode(file_get_contents($path));
            }
        }

        return [
            'id' => $item->id,
            'frame' => $base64 ? 'data:image/png;base64,' . $base64 : null,
            'type' => $item->type ?? '',
            'cuts' => $item->cuts ?? '',
            'id_topic' => $item->id_topic,
            'event_name' => $item->event_name ?? 'Chưa có sự kiện',
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $frames,
        'total_pages' => ceil($total / $limit),
        'current_page' => (int)$page,
        'total' => $total
    ]);
}

    // === THÊM NHIỀU KHUNG - ĐÃ SỬA LỖI store() on array ===
    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'frames' => 'required|array|min:1',
            'frames.*.id_topic' => 'required|exists:event,id',
            'frames.*.type' => 'required|string|max:100',
            'frames.*.cuts' => 'required|in:3,41,42,6',
            'frames.*.frame' => 'required|file|image|mimes:png,jpg,jpeg|max:8048',
        ]);

        $successCount = 0;
        $failedCount = 0;
        $results = [];

        // LẤY DỮ LIỆU TỪ frames[index][field]
        $framesData = $request->input('frames', []);
        $files = $request->file('frames'); // Đây là mảng file: frames[0][frame], frames[1][frame]...

        foreach ($framesData as $index => $data) {
            if (!isset($files[$index]['frame'])) continue;

            $file = $files[$index]['frame'];
            try {
                $path = $file->store('frames', 'public');
                $filename = basename($path);

                $id = DB::table('template')->insertGetId([
                    'id_admin' => $request->id_admin,
                    'id_topic' => $data['id_topic'],
                    'frame' => $filename,
                    'type' => $data['type'],
                    'cuts' => $data['cuts']
                ]);

                $successCount++;
                $results[] = ['id' => $id, 'status' => 'success'];
            } catch (\Exception $e) {
                $failedCount++;
                $results[] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Thêm thành công $successCount khung, thất bại $failedCount",
            'data' => $results
        ], 201);
    }

    // === CHỈNH SỬA - ĐÃ SỬA HOÀN TOÀN ===
    public function update(Request $request, $id)
    {
        // Kiểm tra khung có tồn tại không
        $frame = DB::table('template')->where('id', $id)->first();
        if (!$frame) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Không tìm thấy khung ảnh'
            ], 404);
        }

        // Validation linh hoạt - chỉ validate những trường được gửi lên
        $rules = [];
        
        if ($request->has('id_topic')) {
            $rules['id_topic'] = 'required|integer|exists:event,id';
        }
        
        if ($request->has('type')) {
            $rules['type'] = 'required|string|max:100';
        }
        
        if ($request->has('cuts')) {
            $rules['cuts'] = 'required|in:3,41,42,6';
        }
        
        if ($request->hasFile('frame')) {
            $rules['frame'] = 'required|image|mimes:png,jpg,jpeg|max:8048';
        }

        // Nếu không có gì để update
        if (empty($rules)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không có dữ liệu để cập nhật'
            ], 400);
        }

        // Validate
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors()
            ], 422);
        }

        // Chuẩn bị dữ liệu update
        $data = [];

        if ($request->has('id_topic')) {
            $data['id_topic'] = $request->id_topic;
        }

        if ($request->has('type')) {
            $data['type'] = $request->type;
        }

        if ($request->has('cuts')) {
            $data['cuts'] = $request->cuts;
        }

        // Xử lý file ảnh mới
        if ($request->hasFile('frame')) {
            // Xóa file cũ nếu có (chỉ xóa file trong storage, không xóa BLOB)
            if ($frame->frame && strlen($frame->frame) < 200) {
                $oldPath = 'frames/' . $frame->frame;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            // Lưu file mới
            $path = $request->file('frame')->store('frames', 'public');
            $data['frame'] = basename($path);
        }

        // Thực hiện update
        try {
            DB::table('template')->where('id', $id)->update($data);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật khung ảnh thành công',
                'data' => $data
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $frame = DB::table('template')->where('id', $id)->first();
        if (!$frame) return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);

        if ($frame->frame && strlen($frame->frame) < 200) {
            Storage::disk('public')->delete('frames/' . $frame->frame);
        }

        DB::table('template')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}