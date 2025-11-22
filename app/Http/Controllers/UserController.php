<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // GET /api/users → get_user.php
    public function index(Request $request)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $users = User::where('id_admin', $id_admin)
            ->select('id', 'username')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => (int) $user->id,
                    'username' => (string) $user->username,
                ];
            });

        return response()->json($users);
    }

    // POST /api/users/{id} → update_user.php
    public function update(Request $request, $id)
    {
        $id_admin = $request->query('id_admin');
        if (!$id_admin) {
            return response()->json(['status' => 'error', 'message' => 'Thiếu tham số: id_admin'], 400);
        }

        $request->validate([
            'id_topic' => 'required|integer',
        ]);

        $updated = User::where('id', $id)
            ->where('id_admin', $id_admin)
            ->update(['id_topic' => $request->id_topic]);

        if ($updated) {
            return response()->json(['status' => 'success', 'message' => 'Cập nhật id_topic thành công']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy user'], 404);
        }
    }
}