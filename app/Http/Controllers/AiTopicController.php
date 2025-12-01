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
    $filter_name = $request->query('filter_name');

    if (!$id_admin) {
        return response()->json(['status' => 'error', 'message' => 'Thiếu id_admin'], 400);
    }

    $offset = ($page - 1) * $limit;

    $query = DB::table('ai_topics')
        ->where('id_admin', $id_admin)
        ->select('id', 'name', 'type', 'illustration', 'is_prompt', 'status');

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('type', 'like', "%{$search}%");
        });
    }

    if ($filter_name && $filter_name !== 'all') {
        $query->where('name', $filter_name);
    }

    $total = $query->count();

    $topics = $query->orderBy('id', 'desc')
        ->offset($offset)
        ->limit($limit)
        ->get();

    $topics = $topics->map(function ($item) {
        $illustration = null;

        if (!$item->is_prompt && $item->illustration && strlen($item->illustration) < 200) {
            // Là ảnh → trả URL
            $illustration = $this->getPublicUrl($item->illustration);
        } elseif ($item->is_prompt) {
            // Là prompt → trả text
            $illustration = $item->illustration;
        }
        // Nếu không phải 2 trường hợp trên → để null

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'type' => $item->type ?? '',
            'illustration' => $illustration,
            'is_prompt' => (bool) $item->is_prompt,
            'status' => $item->status,
        ];
    });

    $uniqueNames = DB::table('ai_topics')
        ->where('id_admin', $id_admin)
        ->whereNotNull('name')
        ->distinct()
        ->pluck('name')
        ->sort()
        ->values();

    return response()->json([
        'status' => 'success',
        'data' => $topics,
        'unique_names' => $uniqueNames,
        'total_pages' => ceil($total / $limit),
        'total' => $total
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'status' => 'required|in:Đang hoạt động,Không hoạt động',
            'illustration' => 'nullable|file|image|mimes:jpeg,jpg,png,gif,webp|max:8048',
            'prompt' => 'nullable|string|max:2000',
        ]);

        // Xác định kiểu: ảnh hay prompt
        $isPrompt = $request->has('prompt') && $request->filled('prompt');
        $illustrationValue = null;

        if ($request->hasFile('illustration') && !$isPrompt) {
            // Lưu ảnh lên Supabase
            $file = $request->file('illustration');
            $filename = 'ai_illustrations/' . uniqid('ai_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());
            $mimeType = $file->getMimeType();

            $response = $this->uploadToSupabase($filename, $contents, $mimeType);

            if ($response->failed()) {
                \Log::error("Upload AI illustration thất bại", $response->json());
                return response()->json(['status' => 'error', 'message' => 'Lỗi upload ảnh'], 500);
            }

            $illustrationValue = $filename;
        } elseif ($isPrompt) {
            // Lưu prompt text
            $illustrationValue = $request->prompt;
        }

        $data = [
            'id_admin' => $request->id_admin,
            'name' => $request->name,
            'type' => $request->type,
            'status' => $request->status,
            'is_prompt' => $isPrompt ? 1 : 0,
            'illustration' => $illustrationValue,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('ai_topics')->insertGetId($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm chủ đề AI thành công!',
            'id' => $id
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $topic = DB::table('ai_topics')->where('id', $id)->first();

        if (!$topic) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'nullable|string|max:100',
            'status' => 'sometimes|required|in:Đang hoạt động,Không hoạt động',
        ];

        if ($request->hasFile('illustration')) {
            $rules['illustration'] = 'image|mimes:jpeg,jpg,png,gif,webp|max:8048';
        }

        if ($request->has('prompt')) {
            $rules['prompt'] = 'nullable|string|max:2000';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('name')) $data['name'] = $request->name;
        if ($request->has('type')) $data['type'] = $request->type;
        if ($request->has('status')) $data['status'] = $request->status;

        // Xử lý cập nhật illustration
        if ($request->hasFile('illustration') && !$request->filled('prompt')) {
            // Xóa ảnh cũ trên Supabase (nếu đang là ảnh)
            if (!$topic->is_prompt && $topic->illustration && strlen($topic->illustration) < 200) {
                $this->deleteFromSupabase($topic->illustration);
            }

            $file = $request->file('illustration');
            $filename = 'ai_illustrations/' . uniqid('ai_upd_', true) . '.' . $file->getClientOriginalExtension();
            $contents = file_get_contents($file->getPathname());
            $mimeType = $file->getMimeType();

            $response = $this->uploadToSupabase($filename, $contents, $mimeType);

            if ($response->failed()) {
                \Log::error("Cập nhật AI illustration thất bại", $response->json());
                return response()->json(['status' => 'error', 'message' => 'Lỗi upload ảnh mới'], 500);
            }

            $data['illustration'] = $filename;
            $data['is_prompt'] = 0;
        } elseif ($request->filled('prompt')) {
            // Chuyển sang prompt text
            if (!$topic->is_prompt && $topic->illustration && strlen($topic->illustration) < 200) {
                // Xóa ảnh cũ nếu trước đó là ảnh
                $this->deleteFromSupabase($topic->illustration);
            }
            $data['illustration'] = $request->prompt;
            $data['is_prompt'] = 1;
        }

        $data['updated_at'] = now();
        DB::table('ai_topics')->where('id', $id)->update($data);

        return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công!']);
    }

    public function destroy($id)
    {
        $topic = DB::table('ai_topics')->where('id', $id)->first();

        if (!$topic) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        // Xóa ảnh trên Supabase nếu đang là ảnh
        if (!$topic->is_prompt && $topic->illustration && strlen($topic->illustration) < 200) {
            $this->deleteFromSupabase($topic->illustration);
        }

        DB::table('ai_topics')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}