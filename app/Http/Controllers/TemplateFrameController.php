<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TemplateFrameController extends Controller
{
    /**
     * Hiển thị danh sách khung ảnh
     */
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
            $fullUrl = $item->frame 
                ? Storage::url($item->frame) 
                : null;

            return [
                'id' => (int) $item->id,
                'frame' => 'https://dslr-api.onrender.com' . $fullUrl, 
                'type' => $item->type ?? '',
                'cuts' => $item->cuts ? (string) $item->cuts : '',
                'id_topic' => $item->id_topic ? (int) $item->id_topic : null,
                'event_name' => $item->event_name ?? 'Chưa có sự kiện',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $frames,
            'total_pages' => ceil($total / $limit),
            'current_page' => (int) $page,
            'total' => $total
        ]);
    }

    /**
     * Thêm nhiều khung ảnh
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'frames' => 'required|array|min:1',
            'frames.*.id_topic' => 'required|exists:event,id',
            'frames.*.type' => 'required|string|max:100',
            'frames.*.cuts' => 'required|in:3,41,42,6',
            'frames.*.frame' => 'required|image|mimes:png,jpg,jpeg|max:8192',
        ]);

        $successCount = 0;
        $framesData = $request->input('frames', []);
        $files = $request->file('frames');

        foreach ($framesData as $index => $data) {
            if (!isset($files[$index]['frame'])) {
                Log::warning("Thiếu file ở index $index khi thêm khung");
                continue;
            }

            $uploadedFile = $files[$index]['frame'];
            if (!$uploadedFile->isValid()) {
                Log::warning("File không hợp lệ ở index $index: " . $uploadedFile->getErrorMessage());
                continue;
            }

            try {
                // Tạo tên file duy nhất
                $filename = uniqid('frame_', true) . '.' . $uploadedFile->getClientOriginalExtension();
                // Lưu vào storage/app/public/frames/
                $path = $uploadedFile->storeAs('frames', $filename, 'public');

                DB::table('template')->insert([
                    'id_admin' => $request->id_admin,
                    'id_topic' => $data['id_topic'],
                    'frame' => $path, // ← lưu vào cột `frame` (VARCHAR)
                    'type' => $data['type'],
                    'cuts' => $data['cuts']
                ]);

                $successCount++;
            } catch (\Exception $e) {
                Log::error("Lỗi lưu khung ở index $index: " . $e->getMessage());
                continue;
            }
        }

        if ($successCount === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không có khung nào được thêm thành công!'
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Thêm thành công $successCount khung ảnh!",
        ], 201);
    }

    /**
     * Cập nhật khung ảnh
     */
    public function update(Request $request, $id)
    {
        $frameRecord = DB::table('template')->where('id', $id)->first();
        if (!$frameRecord) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy khung ảnh'], 404);
        }

        $data = [];

        if ($request->has('id_topic')) {
            $request->validate(['id_topic' => 'required|integer|exists:event,id']);
            $data['id_topic'] = $request->id_topic;
        }

        if ($request->has('type')) {
            $request->validate(['type' => 'required|string|max:100']);
            $data['type'] = $request->type;
        }

        if ($request->has('cuts')) {
            $request->validate(['cuts' => 'required|in:3,41,42,6']);
            $data['cuts'] = $request->cuts;
        }

        if ($request->hasFile('frame')) {
            $request->validate(['frame' => 'required|image|mimes:png,jpg,jpeg|max:8192']);
            $uploadedFile = $request->file('frame');

            if ($uploadedFile->isValid()) {
                try {
                    // Xóa file cũ nếu có
                    if (!empty($frameRecord->frame)) {
                        Storage::disk('public')->delete($frameRecord->frame);
                    }

                    $filename = uniqid('frame_upd_', true) . '.' . $uploadedFile->getClientOriginalExtension();
                    $path = $uploadedFile->storeAs('frames', $filename, 'public');
                    $data['frame'] = $path; // ← lưu vào cột `frame`
                } catch (\Exception $e) {
                    Log::error("Lỗi cập nhật ảnh khung $id: " . $e->getMessage());
                    return response()->json(['status' => 'error', 'message' => 'Lỗi lưu ảnh mới'], 500);
                }
            }
        }

        if (empty($data)) {
            return response()->json(['status' => 'error', 'message' => 'Không có dữ liệu cập nhật'], 400);
        }

        try {
            DB::table('template')->where('id', $id)->update($data);
            return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công']);
        } catch (\Exception $e) {
            Log::error("Lỗi cập nhật khung $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật'], 500);
        }
    }

    /**
     * Xóa khung ảnh
     */
    public function destroy($id)
    {
        $frameRecord = DB::table('template')->where('id', $id)->first();
        if (!$frameRecord) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        try {
            // Xóa file ảnh
            if (!empty($frameRecord->frame)) {
                Storage::disk('public')->delete($frameRecord->frame);
            }

            DB::table('template')->where('id', $id)->delete();
            return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            Log::error("Lỗi xóa khung $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi xóa'], 500);
        }
    }
}