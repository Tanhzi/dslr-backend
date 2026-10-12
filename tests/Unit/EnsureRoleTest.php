<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Unit test middleware phân quyền (role) — không cần DB
 * (EnsureRole dùng response()->json nên cần boot app qua TestCase).
 */
class EnsureRoleTest extends TestCase
{
    private function requestWithUser(?User $user): Request
    {
        $request = Request::create('/api/protected', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function userWithRole(int $role): User
    {
        $user = new User();
        $user->role = $role;

        return $user;
    }

    private function next(): \Closure
    {
        return fn () => new Response('ok', 200);
    }

    public function test_admin_passes_role_2(): void
    {
        $request = $this->requestWithUser($this->userWithRole(2));
        $response = (new EnsureRole())->handle($request, $this->next(), '2');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_staff_passes_role_1_2(): void
    {
        $request = $this->requestWithUser($this->userWithRole(1));
        $response = (new EnsureRole())->handle($request, $this->next(), '1', '2');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_customer_rejected_by_role_2(): void
    {
        $request = $this->requestWithUser($this->userWithRole(0));
        $response = (new EnsureRole())->handle($request, $this->next(), '2');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_staff_rejected_by_admin_only_route(): void
    {
        $request = $this->requestWithUser($this->userWithRole(1));
        $response = (new EnsureRole())->handle($request, $this->next(), '2');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_missing_user_returns_401(): void
    {
        $request = $this->requestWithUser(null);
        $response = (new EnsureRole())->handle($request, $this->next(), '2');

        $this->assertSame(401, $response->getStatusCode());
    }
}
