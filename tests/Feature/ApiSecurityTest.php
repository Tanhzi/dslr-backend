<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kiểm tra an ninh endpoint (L1) — không cần DB:
 * token thiếu/sai → 401, phân quyền → 403/401, validation → 422.
 */
class ApiSecurityTest extends TestCase
{
    public function test_chat_requires_token(): void
    {
        $this->postJson('/api/chat', ['question' => 'xin chao'])
            ->assertStatus(401);
    }

    /** L5: GET /events không được public nữa. */
    public function test_events_requires_token(): void
    {
        $this->getJson('/api/events?id_admin=2')
            ->assertStatus(401);
    }

    public function test_events_rejects_garbage_token(): void
    {
        $this->getJson('/api/events?id_admin=2', ['Authorization' => 'Bearer not-a-jwt'])
            ->assertStatus(401);
    }

    public function test_revenue_summary_requires_token(): void
    {
        $this->getJson('/api/summary?id_admin=2')
            ->assertStatus(401);
    }

    public function test_login_returns_validation_errors_without_db(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_register_returns_validation_errors(): void
    {
        $this->postJson('/api/register', ['email' => 'sai-format'])
            ->assertStatus(422);
    }

    public function test_unknown_api_route_returns_404(): void
    {
        $this->getJson('/api/khong-ton-tai')
            ->assertStatus(404);
    }
}
