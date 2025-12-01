<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StickerController extends Controller
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

    protected function uploadToSupabase(string $filePath, string $contents, string $mimeType)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->supabaseKey,
            'apikey' => $this->supabaseKey,
            'Content-Type' => $mimeType,
        ])->withBody($contents, $mimeType)->post($url);
    }

    protected function deleteFromSupabase(string $filePath)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $this->bucket . '/' . $filePath;
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->supabaseKey,
            'apikey' => $this->supabaseKey,
        ])->delete($url);
    }

    protected function downloadFromSupabase(string $filePath)
    {
        $url = $this->getPublicUrl($filePath);
        return Http::withOptions(['timeout' => 30])->get($url);
    }

public function index(Request $request)
{
    $id_admin = $request->query('id_admin');
    $page = $request->query('page', 1);
    $limit = $request->query('limit', 10);
    $search = $request->query('search');
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

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('stickers.type', 'like', "%{$search}%")
              ->orWhere('event.name', 'like', "%{$search}%");
        });
    }

    if ($filter_type && $filter_type !== 'all') {
        $query->where('stickers.type', $filter_type);
    }

    $total = $query->count();

    $stickers = $query->orderBy('stickers.id', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();

    $stickers = $stickers->map(function ($item) {
        $imageUrl = null;

        // Nếu là đường dẫn file (không phải BLOB)
        if ($item->sticker && strlen($item->sticker) < 200) {
            $imageUrl = $this->getPublicUrl($item->sticker);
        }

        // Nếu là BLOB (cũ) → bạn có thể bỏ hoặc giữ nguyên (nhưng không nên dùng BLOB)
        // Ở đây mình giữ tương thích: nếu không phải file → coi là text (nhưng sticker thường là ảnh)
        // Nên ưu tiên chỉ hỗ trợ file từ Supabase

        return [
            'id' => (int) $item->id,
            'sticker' => $imageUrl, // ← TRẢ URL, KHÔNG PHẢI BASE64
            'type' => $item->type ?? '',
            'id_topic' => $item->id_topic ? (int) $item->id_topic : null,
            'event_name' => $item->event_name ?? 'Chưa có sự kiện',
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $stickers,
        'total_pages' => ceil($total / $limit),
        'current_page' => (int) $page,
        'total' => $total
    ]);
}
    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'id_topic' => 'nullable|exists:event,id',
            'type' => 'nullable|string|max:100',
            'stickers' => 'required|array|min:1',
            'stickers.*' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp,svg|max:8048',
        ]);

        $successCount = 0;
        foreach ($request->file('stickers') as $file) {
            try {
                $filename = 'stickers/' . uniqid('sticker_', true) . '.' . $file->getClientOriginalExtension();
                $contents = file_get_contents($file->getPathname());
                $mimeType = $file->getMimeType();

                $response = $this->uploadToSupabase($filename, $contents, $mimeType);

                if ($response->failed()) {
                    \Log::error("Upload sticker thất bại", $response->json());
                    continue;
                }

                DB::table('stickers')->insert([
                    'id_admin' => $request->id_admin,
                    'id_topic' => $request->id_topic ?? null,
                    'sticker' => $filename, // lưu đường dẫn tương đối
                    'type' => $request->type ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $successCount++;
            } catch (\Exception $e) {
                \Log::error("Lỗi lưu sticker: " . $e->getMessage());
                continue;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã thêm thành công $successCount sticker!"
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $sticker = DB::table('stickers')->where('id', $id)->first();
        if (!$sticker) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy sticker'], 404);
        }

        $rules = [];
        if ($request->has('id_topic')) $rules['id_topic'] = 'nullable|integer|exists:event,id';
        if ($request->has('type')) $rules['type'] = 'nullable|string|max:100';
        if ($request->hasFile('sticker')) $rules['sticker'] = 'required|image|mimes:png,jpg,jpeg,gif,webp,svg|max:8048';

        if (empty($rules)) {
            return response()->json(['status' => 'error', 'message' => 'Không có dữ liệu để cập nhật'], 400);
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ', 'errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('id_topic')) $data['id_topic'] = $request->id_topic ?: null;
        if ($request->has('type')) $data['type'] = $request->type;

        if ($request->hasFile('sticker')) {
            // Xóa file cũ trên Supabase (nếu là đường dẫn file)
            if ($sticker->sticker && strlen($sticker->sticker) < 200) {
                $this->deleteFromSupabase($sticker->sticker);
            }

            $file = $request->file('sticker');
            $filename = 'stickers/' . uniqid('sticker_upd_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());
            $mimeType = $file->getMimeType();

            $response = $this->uploadToSupabase($filename, $contents, $mimeType);
            if ($response->failed()) {
                \Log::error("Cập nhật sticker thất bại", $response->json());
                return response()->json(['status' => 'error', 'message' => 'Upload ảnh mới thất bại'], 500);
            }

            $data['sticker'] = $filename;
        }

        try {
            $data['updated_at'] = now();
            DB::table('stickers')->where('id', $id)->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật sticker thành công'
            ]);
        } catch (\Exception $e) {
            \Log::error("Lỗi DB khi cập nhật sticker $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi cập nhật'], 500);
        }
    }

    public function destroy($id)
    {
        $sticker = DB::table('stickers')->where('id', $id)->first();
        if (!$sticker) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        // Xóa file trên Supabase (nếu là đường dẫn file)
        if ($sticker->sticker && strlen($sticker->sticker) < 200) {
            $this->deleteFromSupabase($sticker->sticker);
        }

        DB::table('stickers')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}