<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AiTopicController extends Controller
{
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

        // Tìm kiếm
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Lọc theo tên
        if ($filter_name && $filter_name !== 'all') {
            $query->where('name', $filter_name);
        }

        $total = $query->count();

        $topics = $query->orderBy('id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Xử lý illustration: nếu là ảnh → trả về base64, nếu là prompt → trả về text
        $topics = $topics->map(function ($item) {
            $illustration = null;

            // Nếu là ảnh (is_prompt = 0) và có tên file
            if (!$item->is_prompt && $item->illustration) {
                $path = storage_path('app/public/ai_illustrations/' . $item->illustration);
                if (file_exists($path)) {
                    $illustration = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
                }
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type ?? '',
                'illustration' => $illustration ?? $item->illustration, // ảnh base64 hoặc prompt text
                'is_prompt' => (bool)$item->is_prompt,
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

        $data = [
            'id_admin' => $request->id_admin,
            'name' => $request->name,
            'type' => $request->type,
            'status' => $request->status,
            'is_prompt' => $request->has('prompt') && $request->filled('prompt') ? 1 : 0,
        ];

        if ($request->hasFile('illustration') && !$request->filled('prompt')) {
            $path = $request->file('illustration')->store('ai_illustrations', 'public');
            $data['illustration'] = basename($path);
        } elseif ($request->filled('prompt')) {
            $data['illustration'] = $request->prompt;
        }

        $id = DB::table('ai_topics')->insertGetId($data + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        // Xử lý ảnh hoặc prompt mới
        if ($request->hasFile('illustration') && !$request->filled('prompt')) {
            // Xóa ảnh cũ nếu có
            if (!$topic->is_prompt && $topic->illustration) {
                Storage::disk('public')->delete('ai_illustrations/' . $topic->illustration);
            }
            $path = $request->file('illustration')->store('ai_illustrations', 'public');
            $data['illustration'] = basename($path);
            $data['is_prompt'] = 0;
        } elseif ($request->filled('prompt')) {
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

        // Xóa ảnh nếu có
        if (!$topic->is_prompt && $topic->illustration) {
            Storage::disk('public')->delete('ai_illustrations/' . $topic->illustration);
        }

        DB::table('ai_topics')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}