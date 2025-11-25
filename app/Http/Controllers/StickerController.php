<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StickerController extends Controller
{
    public function index(Request $request)
    {
        $id_admin = $request->query('id_admin');
        $page     = $request->query('page', 1);
        $limit   = $request->query('limit', 10);
        $search  = $request->query('search');
        $filter_type = $request->query('filter_type');

        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu id_admin'], 400);
        }

        $offset = ($page - 1) * $limit;

        $query = DB::table('stickers')
            ->leftJoin('event', 'stickers.id_topic', '=', 'event.id')
            ->where('stickers.id_admin', $id_admin)
            ->select(
                'stickers.id',
                'stickers.sticker',
                'stickers.type',
                'stickers.id_topic',
                'event.name as event_name'
            );

        // Tìm kiếm
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('stickers.type', 'like', "%{$search}%")
                  ->orWhere('event.name', 'like', "%{$search}%");
            });
        }

        // Lọc theo loại/nhóm
        if ($filter_type && $filter_type !== 'all') {
            $query->where('stickers.type', $filter_type);
        }

        $total = $query->count();

        $stickers = $query->orderBy('stickers.id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // ✅ XỬ LÝ BLOB GIỐNG TEMPLATE
        $stickers = $stickers->map(function ($item) {
            $base64 = null;

            // Ưu tiên file trong storage (sticker mới)
            if ($item->sticker && strlen($item->sticker) < 200) {
                $path = storage_path('app/public/stickers/' . $item->sticker);
                if (file_exists($path)) {
                    $base64 = base64_encode(file_get_contents($path));
                }
            }

            // Nếu không → là BLOB (sticker cũ)
            if (!$base64 && !empty($item->sticker)) {
                $base64 = base64_encode($item->sticker);
            }

            return [
                'id'         => $item->id,
                'sticker'    => $base64 ? 'data:image/png;base64,' . $base64 : null,
                'type'       => $item->type ?? '',
                'id_topic'   => $item->id_topic,
                'event_name' => $item->event_name ?? 'Chưa có sự kiện',
            ];
        });

        return response()->json([
            'status'       => 'success',
            'data'         => $stickers,
            'total_pages'  => ceil($total / $limit),
            'current_page' => (int)$page,
            'total'        => $total
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_admin'          => 'required|integer',
            'id_topic'          => 'nullable|exists:event,id',
            'type'              => 'nullable|string|max:100',
            'stickers'          => 'required|array|min:1',
            'stickers.*'        => 'required|file|image|mimes:jpeg,jpg,png,gif,webp,svg|max:8048',
        ]);

        $successCount = 0;
        foreach ($request->file('stickers') as $file) {
            $path     = $file->store('stickers', 'public');
            $filename = basename($path);

            DB::table('stickers')->insert([
                'id_admin'   => $request->id_admin,
                'id_topic'   => $request->id_topic ?? null,
                'sticker'    => $filename,
                'type'       => $request->type ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $successCount++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Đã thêm thành công $successCount sticker!"
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Kiểm tra sticker có tồn tại không
        $sticker = DB::table('stickers')->where('id', $id)->first();
        if (!$sticker) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy sticker'
            ], 404);
        }

        // Validation linh hoạt
        $rules = [];

        if ($request->has('id_topic')) {
            $rules['id_topic'] = 'nullable|integer|exists:event,id';
        }

        if ($request->has('type')) {
            $rules['type'] = 'nullable|string|max:100';
        }

        if ($request->hasFile('sticker')) {
            $rules['sticker'] = 'required|image|mimes:png,jpg,jpeg,gif,webp,svg|max:8048';
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
            $data['id_topic'] = $request->id_topic ?: null;
        }

        if ($request->has('type')) {
            $data['type'] = $request->type;
        }

        // Xử lý file ảnh mới
        if ($request->hasFile('sticker')) {
            // Xóa file cũ nếu có (chỉ xóa file trong storage, không xóa BLOB)
            if ($sticker->sticker && strlen($sticker->sticker) < 200) {
                $oldPath = 'stickers/' . $sticker->sticker;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Lưu file mới
            $path = $request->file('sticker')->store('stickers', 'public');
            $data['sticker'] = basename($path);
        }

        // Thực hiện update
        try {
            $data['updated_at'] = now();
            DB::table('stickers')->where('id', $id)->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật sticker thành công',
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
        $sticker = DB::table('stickers')->where('id', $id)->first();
        if (!$sticker) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        // Xóa file nếu có (không xóa BLOB)
        if ($sticker->sticker && strlen($sticker->sticker) < 200) {
            Storage::disk('public')->delete('stickers/' . $sticker->sticker);
        }

        DB::table('stickers')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}