<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Direct URL access: every application route needs a signed-in user, and a
 * user with no role reaches nothing but the dashboard and their own account.
 */
class RouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Route names that are public by design. */
    private const PUBLIC = [
        'home', 'login', 'login.store', 'password.request', 'password.email', 'password.reset', 'password.update',
        'two-factor.login', 'two-factor.login.store', 'storage.local', 'storage.local.upload', 'well-known.passkeys',
    ];

    /** Routes any signed-in user may open, whatever their role. */
    private const ANY_USER = ['dashboard', 'notifications.read', 'reports.index', 'logout'];

    /**
     * @return list<Route>
     */
    private function appRoutes(): array
    {
        return array_values(array_filter(Router::getRoutes()->getRoutes(), function (Route $route): bool {
            $name = (string) $route->getName();

            return $name !== ''
                && ! in_array($name, self::PUBLIC, true)
                && ! Str::startsWith($name, ['passkey', 'verification', 'password.confirm', 'boost', 'inertia', 'two-factor', 'user-password', 'profile', 'security', 'appearance', 'ignition', 'sanctum'])
                && ! Str::startsWith($route->uri(), ['_', 'up']);
        }));
    }

    private function url(Route $route): string
    {
        return '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
    }

    public function test_guests_are_sent_to_login_from_every_app_route()
    {
        foreach ($this->appRoutes() as $route) {
            $method = $route->methods()[0];
            $response = $this->call($method, $this->url($route), [], [], [], ['HTTP_ACCEPT' => 'text/html']);

            // A route whose parameter constraint rejects the dummy value 404s before auth runs; that reveals nothing.
            $allowed = $route->wheres === [] ? [302, 401, 419] : [302, 401, 404, 419];

            $this->assertContains($response->getStatusCode(), $allowed, "{$method} {$route->uri()} is reachable without signing in.");

            if ($response->getStatusCode() === 302) {
                $this->assertStringContainsString('/login', (string) $response->headers->get('Location'), "{$route->uri()} redirects somewhere other than login.");
            }
        }
    }

    public function test_a_user_without_a_role_is_refused_everywhere_except_their_own_pages()
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $nobody = User::factory()->create();

        foreach ($this->appRoutes() as $route) {
            if (in_array($route->getName(), self::ANY_USER, true) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $status = $this->actingAs($nobody)->get($this->url($route))->getStatusCode();

            $this->assertContains($status, [403, 404], "GET {$route->uri()} answered {$status} for a user with no role.");
        }
    }

    public function test_responses_carry_security_headers()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create()->assignRole(Role::Cashier))
            ->get(route('dashboard'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
