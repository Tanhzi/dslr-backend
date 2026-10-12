<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $jwtService = new JwtService();
        $token = $jwtService->parseToken($request);

        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Missing token'], 401);
        }

        $decoded = $jwtService->validateToken($token);

        if (!$decoded || !isset($decoded->sub)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid or expired token'], 401);
        }

        // CSRF (chống gửi request chéo site đính kèm cookie):
        // nếu xác thực bằng COOKIE (không có header Authorization) và là
        // phương thức ghi dữ liệu, Origin gửi lên phải nằm trong whitelist.
        // Request không có header Origin (curl, server-to-server) được cho qua.
        $viaCookie = !$request->bearerToken() && $request->cookie(config('jwt.cookie.name', 'sweetlens_token'));
        if ($viaCookie && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $origin = $request->header('Origin');
            $allowed = array_merge(
                config('cors.allowed_origins', []),
                [(string) config('app.url')]
            );
            if ($origin !== null && !in_array($origin, $allowed, true)) {
                return response()->json(['status' => 'error', 'message' => 'Origin not allowed'], 403);
            }
        }

        $user = User::find($decoded->sub);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 401);
        }

        $request->merge(['user' => $user]);
        $request->setUserResolver(fn () => $user);

        // Gán id_admin, id_topic từ user vào request để
        // các controller dùng $request->id_admin hoặc $request->query('id_admin') vẫn hoạt động
        if ($user->id_admin !== null) {
            $request->merge(['id_admin' => $user->id_admin]);
            $request->query->set('id_admin', $user->id_admin);
        }
        if ($user->id_topic !== null) {
            $request->merge(['id_topic' => $user->id_topic]);
            $request->query->set('id_topic', $user->id_topic);
        }

        return $next($request);
    }
}
