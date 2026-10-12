<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class JwtService
{
    protected string $secret;
    protected int $ttl;
    protected string $algo;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->ttl = config('jwt.ttl', 60);
        $this->algo = config('jwt.algorithm', 'HS256');
    }

    public function generateToken(array $payload): string
    {
        $issuedAt = time();
        $payload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + ($this->ttl * 60),
        ]);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function validateToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (ExpiredException $e) {
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Decode kể cả khi token đã hết hạn, miễn còn trong cửa sổ refresh_grace
     * (dùng cho POST /api/refresh — sliding expiration).
     */
    public function decodeWithGrace(string $token): ?object
    {
        // JWT::$leeway là static — chỉ tăng tạm trong lúc decode rồi khôi phục,
        // nếu không middleware validateToken cũng sẽ chấp nhận token hết hạn.
        $previousLeeway = JWT::$leeway;

        try {
            JWT::$leeway = (int) config('jwt.refresh_grace', 60 * 24 * 7) * 60; // phút → giây

            return JWT::decode($token, new Key($this->secret, $this->algo));
        } catch (Exception $e) {
            return null;
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * Lấy token từ request: ưu tiên header Authorization, fallback cookie httpOnly.
     */
    public function parseToken(Request $request): ?string
    {
        return $request->bearerToken()
            ?: $request->cookie(config('jwt.cookie.name', 'sweetlens_token'));
    }

    /**
     * Cookie httpOnly mang JWT — FE phải fetch với credentials: 'include'.
     * secure theo request hiện tại (HTTPS trên Render, false ở localhost dev).
     */
    public function authCookie(Request $request, string $token): Cookie
    {
        $cfg = config('jwt.cookie', []);
        $sameSite = strtolower($cfg['same_site'] ?? 'lax');

        // SameSite=None BẮT BUỘC kèm attr Secure, nếu không trình duyệt
        // sẽ TỪ CHỐI cookie. Secure vẫn OK trên http://localhost
        // (localhost là secure context); request HTTPS thật thì đã secure sẵn.
        return cookie(
            name: $cfg['name'] ?? 'sweetlens_token',
            value: $token,
            minutes: $this->ttl,
            path: $cfg['path'] ?? '/',
            domain: $cfg['domain'] ?? null,
            secure: $request->secure() || $sameSite === 'none',
            httpOnly: true,
            raw: false,
            sameSite: $cfg['same_site'] ?? 'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(config('jwt.cookie.name', 'sweetlens_token'));
    }
}
