<?php
// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // GET: Lấy danh sách + tìm kiếm + phân trang
// GET: Lấy danh sách + tìm kiếm + phân trang + lọc theo role
// GET: Lấy danh sách + tìm kiếm + phân trang + lọc theo role (chỉ user và staff, KHÔNG có admin)
public function index(Request $request)
{
    $request->validate([
        'id_admin' => 'required|integer',
        'page' => 'integer|min:1',
        'search' => 'nullable|string|max:255',
        'limit' => 'integer|min:1|max:100',
        'role_filter' => 'nullable|in:all,user,staff' // 'all' = cả user + staff
    ]);

    // Chỉ lấy tài khoản có role = 0 (user) hoặc role = 1 (staff)
    $query = User::where('id_admin', $request->id_admin)
                 ->whereIn('role', [0, 1]); // 🔥 CHỈ DÒNG NÀY LÀ QUAN TRỌNG

    // Áp dụng bộ lọc role nếu có
    $roleFilter = $request->role_filter ?? 'all';
    if ($roleFilter === 'user') {
        $query->where('role', 0);
    } elseif ($roleFilter === 'staff') {
        $query->where('role', 1);
    }
    // 'all' → giữ nguyên cả 0 và 1

    // Tìm kiếm
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('username', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('id_topic', 'like', "%{$search}%");
        });
    }

    $limit = $request->limit ?? 10;
    $page = $request->page ?? 1;
    $total = $query->count();

    $users = $query->offset(($page - 1) * $limit)
                   ->limit($limit)
                   ->get()
                   ->map(function ($user) {
                       return [
                           'id' => $user->id,
                           'username' => $user->username ?? '',
                           'email' => $user->email ?? '',
                           'id_topic' => $user->id_topic ?? '',
                           'id_admin' => $user->id_admin ?? '',
                           'role' => (int) $user->role,
                           'created_at' => $user->created_at,
                       ];
                   });

    return response()->json([
        'status' => 'success',
        'data' => $users,
        'total' => $total,
        'page' => (int)$page,
        'limit' => (int)$limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

    // POST: Thêm người dùng mới
    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'id_topic' => 'nullable|integer',
            'id_admin' => 'required|integer',
            'role' => 'required|integer'
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password, // mutator tự hash
            'id_topic' => $request->id_topic,
            'id_admin' => $request->id_admin,
            'role' => $request->role,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm người dùng thành công!',
            'data' => $user
        ], 201);
    }

    // PUT: Cập nhật (chỉ cần sửa 1 trường cũng được)
    public function update(Request $request, $id)
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'id_topic' => 'nullable|integer',
            'password' => 'nullable|string|min:6',
            'id_admin' => 'required|integer',
            'role' => 'required|integer'
        ]);

        $user = User::where('id', $id)
                    ->where('id_admin', $request->id_admin)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy người dùng hoặc bạn không có quyền!'
            ], 404);
        }

        $user->username = $request->username;
        $user->email = $request->email;
        $user->id_topic = $request->id_topic;
        $user->id_admin = $request->id_admin;
        $user->role = $request->role;

        if ($request->filled('password')) {
            $user->password = $request->password; // mutator hash
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật thành công!'
        ]);
    }

    // DELETE: Xóa
    public function destroy(Request $request, $id)
    {
        $request->validate(['id_admin' => 'required|integer']);

        $user = User::where('id', $id)
                    ->where('id_admin', $request->id_admin)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy hoặc không có quyền xóa!'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa thành công!'
        ]);
    }
}