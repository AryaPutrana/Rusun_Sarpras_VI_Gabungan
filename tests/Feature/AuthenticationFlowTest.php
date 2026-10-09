<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@sarpras.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Petugas User',
            'email' => 'petugas@sarpras.com',
            'password' => Hash::make('password123'),
            'role' => 'petugas',
        ]);

        User::create([
            'name' => 'Invalid Role User',
            'email' => 'invalid@sarpras.com',
            'password' => Hash::make('password123'),
            'role' => 'invalidrole',
        ]);
    }

    /** @test */
    public function login_page_is_accessible()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('email');
        $response->assertSee('password');
        $response->assertSee('_token');
    }

    /** @test */
    public function csrf_protection_is_enabled()
    {
        $response = $this->post('/login', [
            'email' => 'petugas@sarpras.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(419); // Token mismatch exception
    }

    /** @test */
    public function login_validates_required_fields()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    /** @test */
    public function login_validates_email_format()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /** @test */
    public function login_fails_for_invalid_role_users()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'invalid@sarpras.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertStringContainsString('tidak memiliki akses', session('errors')->first('email'));
        $this->assertGuest();
    }

    /** @test */
    public function successful_login_redirects_to_dashboard()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(User::where('email', 'petugas@sarpras.com')->first());
    }

    /** @test */
    public function login_regenerates_session()
    {
        $oldSessionId = session()->getId();

        $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'password123',
        ]);

        $newSessionId = session()->getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }

    /** @test */
    public function remember_me_functionality_works()
    {
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(User::where('email', 'petugas@sarpras.com')->first());
        
        // Check for remember cookie
        $response->assertCookie('remember_web_' . sha1(User::class));
    }

    /** @test */
    public function rate_limiting_blocks_after_five_failed_attempts()
    {
        // Clear any existing rate limits
        RateLimiter::clear('petugas@sarpras.com|127.0.0.1');

        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                '_token' => csrf_token(),
                'email' => 'petugas@sarpras.com',
                'password' => 'wrongpassword',
            ]);
        }

        // 6th attempt should be blocked
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));
    }

    /** @test */
    public function successful_login_clears_rate_limiting()
    {
        // Make 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', [
                '_token' => csrf_token(),
                'email' => 'petugas@sarpras.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Successful login should clear the counter
        $response = $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');

        // Now we can make more attempts without being locked
        $this->post('/login', [
            '_token' => csrf_token(),
            'email' => 'petugas@sarpras.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertGuest(); // Should not be locked out
    }

    /** @test */
    public function authenticated_users_redirected_from_login_page()
    {
        $user = User::where('email', 'petugas@sarpras.com')->first();
        $this->actingAs($user);

        $response = $this->get('/login');

        $response->assertRedirect('/dashboard');
    }

    /** @test */
    public function guest_middleware_protects_dashboard()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /** @test */
    public function role_middleware_allows_petugas_access()
    {
        $user = User::where('email', 'petugas@sarpras.com')->first();
        $this->actingAs($user);

        $response = $this->get('/monitoring');

        $response->assertStatus(200);
    }

    /** @test */
    public function role_middleware_allows_admin_access()
    {
        $user = User::where('email', 'admin@sarpras.com')->first();
        $this->actingAs($user);

        $response = $this->get('/monitoring');

        $response->assertStatus(200);
    }

    /** @test */
    public function logout_invalidates_session()
    {
        $user = User::where('email', 'petugas@sarpras.com')->first();
        $this->actingAs($user);

        $this->assertAuthenticated();

        $response = $this->post('/logout', [
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    /** @test */
    public function logout_regenerates_csrf_token()
    {
        $user = User::where('email', 'petugas@sarpras.com')->first();
        $this->actingAs($user);

        $oldToken = csrf_token();

        $this->post('/logout', [
            '_token' => $oldToken,
        ]);

        $newToken = csrf_token();
        $this->assertNotEquals($oldToken, $newToken);
    }

    /** @test */
    public function session_is_invalidated_after_logout()
    {
        $user = User::where('email', 'petugas@sarpras.com')->first();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);

        $this->post('/logout', [
            '_token' => csrf_token(),
        ]);

        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }
}
