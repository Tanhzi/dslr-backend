<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * Kiểm tra vai trò của user đã đăng nhập.
     * Dùng: ->middleware('role:2') hoặc ->middleware('role:1,2')
     * Role: 0 = customer (kiosk), 1 = staff, 2 = admin
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Missing token'], 401);
        }

        $allowed = array_map('intval', $roles);
        if (!in_array((int) $user->role, $allowed, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
            ], 403);
        }

        return $next($request);
    }
}
