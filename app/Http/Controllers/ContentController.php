<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $id_admin = $request->query('id_admin');
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);
        $search = $request->query('search');
        $filter_title = $request->query('filter_title');

        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu id_admin'], 400);
        }

        $offset = ($page - 1) * $limit;

        $query = DB::table('content_chat')
            ->where('id_admin', $id_admin)
            ->select('id', 'title', 'content', 'created_at');

        // Tìm kiếm
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Lọc theo title
        if ($filter_title && $filter_title !== 'all') {
            $query->where('title', $filter_title);
        }

        $total = $query->count();

        $contents = $query->orderBy('id', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $uniqueTitles = DB::table('content_chat')
            ->where('id_admin', $id_admin)
            ->whereNotNull('title')
            ->distinct()
            ->pluck('title')
            ->sort()
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $contents,
            'unique_titles' => $uniqueTitles,
            'total_pages' => ceil($total / $limit),
            'total' => $total
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_admin' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $data = [
            'id_admin' => $request->input('id_admin'),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'created_at' => now(),
        ];

        $id = DB::table('content_chat')->insertGetId($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm nội dung thành công!',
            'id' => $id
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $content = DB::table('content_chat')->where('id', $id)->first();

        if (!$content) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $data = [];
        if ($request->has('title')) $data['title'] = $request->input('title');
        if ($request->has('content')) $data['content'] = $request->input('content');

        DB::table('content_chat')->where('id', $id)->update($data);

        return response()->json(['status' => 'success', 'message' => 'Cập nhật thành công!']);
    }

    public function destroy($id)
    {
        $content = DB::table('content_chat')->where('id', $id)->first();

        if (!$content) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy'], 404);
        }

        DB::table('content_chat')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Xóa thành công']);
    }
}