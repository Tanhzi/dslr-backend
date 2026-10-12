<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JWT cookie httpOnly + refresh (sliding) + CSRF Origin check.
 */
class AuthCookieTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'username' => 'cookieuser',
            'email' => 'cookie@example.com',
            'password' => 'secret123', // mutator User tu bcrypt
            'role' => 2,
            'id_admin' => null,
            'id_topic' => null,
        ], $attrs));
    }

    private function tokenFor(User $user, ?int $expOffset = null): string
    {
        $iat = time();
        $exp = $expOffset === null ? $iat + 3600 : $iat + $expOffset;

        return JWT::encode([
            'sub' => $user->id,
            'username' => $user->username,
            'role' => (int) $user->role,
            'iat' => $iat,
            'exp' => $exp,
        ], config('jwt.secret'), 'HS256');
    }

    public function test_login_sets_httponly_cookie(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/login', [
            'username' => 'cookieuser',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertCookie('sweetlens_token');
    }

    public function test_cookie_authenticates_request(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        // getJson/postJson chi gui cookie khi withCredentials = true
        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJson(['username' => 'cookieuser']);
    }

    public function test_refresh_returns_new_token_and_cookie(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        $response = $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->postJson('/api/refresh');

        $response->assertStatus(200)
            ->assertJson(['status' => 'success'])
            ->assertCookie('sweetlens_token');
    }

    public function test_refresh_accepts_token_expired_within_grace(): void
    {
        $user = $this->makeUser();
        $expired = $this->tokenFor($user, -60); // het han 60 giay truoc (grace 7 ngay)

        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $expired)
            ->postJson('/api/refresh')
            ->assertStatus(200)
            ->assertCookie('sweetlens_token');
    }

    public function test_refresh_rejects_token_expired_beyond_grace(): void
    {
        $user = $this->makeUser();
        // 8 NGÀY = 8*24*3600 = 288000 giây (ngoài grace 7 ngày)
        $tooOld = $this->tokenFor($user, -(8 * 24 * 3600));

        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $tooOld)
            ->postJson('/api/refresh')
            ->assertStatus(401);
    }

    public function test_refresh_without_token_returns_401(): void
    {
        $this->postJson('/api/refresh')->assertStatus(401);
    }

    public function test_logout_clears_cookie(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertCookieExpired('sweetlens_token');
    }

    public function test_cookie_write_request_with_foreign_origin_is_rejected(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->postJson('/api/discounts/use', [], ['Origin' => 'http://evil.example'])
            ->assertStatus(403);
    }

    public function test_cookie_write_request_with_allowed_origin_passes_middleware(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        // middleware cho qua -> loi do validation (422), KHONG phai 403
        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->postJson('/api/discounts/use', [], ['Origin' => 'http://localhost:5173'])
            ->assertStatus(422);
    }

    public function test_cookie_write_request_without_origin_passes_middleware(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->withCredentials()
            ->withUnencryptedCookie('sweetlens_token', $token)
            ->postJson('/api/discounts/use', [])
            ->assertStatus(422);
    }

    public function test_header_token_write_ignores_origin_check(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        // Xac thuc bang Authorization header -> bo qua Origin check
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/discounts/use', [], ['Origin' => 'http://evil.example'])
            ->assertStatus(422);
    }
}
