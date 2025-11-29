<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateFrameController extends Controller
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

        $frames = $frames->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'frame' => $item->frame ? $this->getPublicUrl($item->frame) : null,
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
                $filename = 'frames/' . uniqid('frame_', true) . '.' . $uploadedFile->getClientOriginalExtension();
                $contents = file_get_contents($uploadedFile->getPathname());
                $mimeType = $uploadedFile->getMimeType();

                $response = $this->uploadToSupabase($filename, $contents, $mimeType);

                if ($response->failed()) {
                    Log::error("Supabase upload frame index $index failed", $response->json());
                    continue;
                }

                DB::table('template')->insert([
                    'id_admin' => $request->id_admin,
                    'id_topic' => $data['id_topic'],
                    'frame' => $filename,
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
                    // Xóa file cũ trên Supabase
                    if (!empty($frameRecord->frame)) {
                        $this->deleteFromSupabase($frameRecord->frame);
                    }

                    $filename = 'frames/' . uniqid('frame_upd_', true) . '.' . $uploadedFile->getClientOriginalExtension();
                    $contents = file_get_contents($uploadedFile->getPathname());
                    $mimeType = $uploadedFile->getMimeType();

                    $response = $this->uploadToSupabase($filename, $contents, $mimeType);

                    if ($response->failed()) {
                        Log::error("Supabase upload update frame $id failed", $response->json());
                        return response()->json(['status' => 'error', 'message' => 'Lỗi upload ảnh mới'], 500);
                    }

                    $data['frame'] = $filename;
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
            // Xóa file trên Supabase
            if (!empty($frameRecord->frame)) {
                $this->deleteFromSupabase($frameRecord->frame);
            }

            DB::table('template')->where('id', $id)->delete();
            return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            Log::error("Lỗi xóa khung $id: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Lỗi khi xóa'], 500);
        }
    }
}