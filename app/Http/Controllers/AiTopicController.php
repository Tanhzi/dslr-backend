<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AiTopicController extends Controller
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

public function index(Request $request)
{
    $id_admin = $request->query('id_admin');
    $type = $request->query('type'); // 'swap' hoặc 'background'

    if (!$id_admin) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu id_admin'], 400);
    }

    // Xây dựng truy vấn cơ bản
    $query = DB::table('ai_topics')
        ->where('id_admin', $id_admin)
        ->select('id', 'name', 'topic', 'type', 'illustration', 'prompt', 'status');

    // Nếu có truyền type thì thêm điều kiện
    if ($type) {
        // Optional: validate type để đảm bảo chỉ nhận 'swap' hoặc 'background'
        if (!in_array($type, ['swap', 'background'])) {
            return response()->json(['status' => 'error', 'message' => 'Giá trị type không hợp lệ'], 400);
        }
        $query->where('type', $type);
    }

    $topics = $query->orderBy('id', 'desc')->get();

    // Map dữ liệu để trả về URL ảnh đầy đủ
    $topics = $topics->map(function ($item) {
        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'topic' => $item->topic,
            'type' => $item->type,
            'illustration' => $item->illustration ? $this->getPublicUrl($item->illustration) : null,
            'prompt' => $item->prompt,
            'status' => $item->status,
        ];
    });

    return response()->json([
        'status' => 'success',
        'data' => $topics,
        'total' => $topics->count()
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'name' => 'required|string|max:255',
            'topic' => 'nullable|string|max:255',
            'type' => 'required|string|max:50', // swap, background
            'status' => 'required|in:Đang hoạt động,Không hoạt động',
            'illustration' => 'nullable|image|max:10240', // Max 10MB
            'prompt' => 'nullable|string', // Text prompt
        ]);

        $illustrationPath = null;

        // Nếu có file ảnh gửi lên (kể cả ảnh upload hay ảnh FE tạo rồi gửi dạng blob)
        if ($request->hasFile('illustration')) {
            $file = $request->file('illustration');
            $filename = 'ai_illustrations/' . uniqid('ai_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());
            $mimeType = $file->getMimeType();

            $response = $this->uploadToSupabase($filename, $contents, $mimeType);

            if ($response->failed()) {
                return response()->json(['status' => 'error', 'message' => 'Lỗi upload ảnh lên Storage'], 500);
            }
            $illustrationPath = $filename;
        }

        $id = DB::table('ai_topics')->insertGetId([
            'id_admin' => $request->id_admin,
            'name' => $request->name,
            'topic' => $request->topic,
            'type' => $request->type,
            'illustration' => $illustrationPath,
            'prompt' => $request->prompt, // Lưu prompt nếu có
            'status' => $request->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Thêm thành công!', 'id' => $id], 201);
    }

    public function update(Request $request, $id)
    {
        $topic = DB::table('ai_topics')->where('id', $id)->first();
        if (!$topic) return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);

        // Validate
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'topic' => 'nullable|string',
            'type' => 'nullable|string',
            'status' => 'sometimes|required',
            'illustration' => 'nullable|image|max:10240',
            'prompt' => 'nullable|string'
        ]);

        if ($validator->fails()) return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);

        $data = [];
        if ($request->has('name')) $data['name'] = $request->name;
        if ($request->has('topic')) $data['topic'] = $request->topic;
        if ($request->has('type')) $data['type'] = $request->type;
        if ($request->has('status')) $data['status'] = $request->status;
        if ($request->has('prompt')) $data['prompt'] = $request->prompt;

        // Xử lý ảnh mới
        if ($request->hasFile('illustration')) {
            // Xóa ảnh cũ
            if ($topic->illustration) {
                $this->deleteFromSupabase($topic->illustration);
            }

            $file = $request->file('illustration');
            $filename = 'ai_illustrations/' . uniqid('ai_upd_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());
            
            $this->uploadToSupabase($filename, $contents, $file->getMimeType());
            $data['illustration'] = $filename;
        }

        $data['updated_at'] = now();
        DB::table('ai_topics')->where('id', $id)->update($data);

        return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công!']);
    }

    public function destroy($id)
    {
        $topic = DB::table('ai_topics')->where('id', $id)->first();
        if (!$topic) return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);

        if ($topic->illustration) {
            $this->deleteFromSupabase($topic->illustration);
        }

        DB::table('ai_topics')->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}