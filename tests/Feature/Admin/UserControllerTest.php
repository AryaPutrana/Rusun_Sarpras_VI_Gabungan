<?php

namespace Tests\Feature\Admin;

use App\Models\FinanceReport;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
    }

    // ==================== AUTHORIZATION TESTS ====================

    /** @test */
    public function admin_can_access_user_management_pages()
    {
        $response = $this->actingAs($this->admin)->get(route('pengguna.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->admin)->get(route('pengguna.create'));
        $response->assertStatus(200);

        $user = User::factory()->create();
        $response = $this->actingAs($this->admin)->get(route('pengguna.edit', $user));
        $response->assertStatus(200);
    }

    /** @test */
    public function petugas_cannot_access_user_management_pages()
    {
        $response = $this->actingAs($this->petugas)->get(route('pengguna.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->petugas)->get(route('pengguna.create'));
        $response->assertStatus(403);

        $user = User::factory()->create();
        $response = $this->actingAs($this->petugas)->get(route('pengguna.edit', $user));
        $response->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_access_user_management()
    {
        $response = $this->get(route('pengguna.index'));
        $response->assertRedirect(route('login'));
    }

    // ==================== CREATE USER TESTS ====================

    /** @test */
    public function admin_can_create_new_admin_user()
    {
        $userData = [
            'name' => 'New Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil ditambahkan.');

        $this->assertDatabaseHas('users', [
            'name' => 'New Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    /** @test */
    public function admin_can_create_new_petugas_user()
    {
        $userData = [
            'name' => 'New Petugas',
            'email' => 'petugas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertRedirect(route('pengguna.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'New Petugas',
            'email' => 'petugas@example.com',
            'role' => 'petugas',
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function cannot_create_user_without_required_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    /** @test */
    public function cannot_create_user_with_invalid_email()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);
        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function cannot_create_user_with_duplicate_email()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);
        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function cannot_create_user_with_short_password()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);
        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function cannot_create_user_with_mismatched_password_confirmation()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
            'role' => 'admin',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);
        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function cannot_create_user_with_invalid_role()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'invalid_role',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);
        $response->assertSessionHasErrors(['role']);
    }

    // ==================== UPDATE USER TESTS ====================

    /** @test */
    public function admin_can_update_user_basic_info()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => 'petugas',
        ]);

        $updateData = [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil diperbarui.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    /** @test */
    public function admin_can_update_user_password()
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);

        $response->assertRedirect(route('pengguna.index'));

        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
        $this->assertTrue(\Hash::check('newpassword123', $user->password));
    }

    /** @test */
    public function admin_can_update_user_without_changing_password()
    {
        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        $updateData = [
            'name' => 'Updated Name',
            'email' => $user->email,
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);

        $response->assertRedirect(route('pengguna.index'));

        $user->refresh();
        $this->assertEquals($oldPasswordHash, $user->password);
        $this->assertEquals('Updated Name', $user->name);
    }

    /** @test */
    public function cannot_update_user_email_to_existing_email()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $updateData = [
            'name' => $user1->name,
            'email' => 'user2@example.com',
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user1), $updateData);
        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function can_update_user_with_same_email()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'test@example.com',
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);
        $response->assertRedirect(route('pengguna.index'));
    }

    // ==================== DELETE USER TESTS ====================

    /** @test */
    public function admin_can_delete_user_without_related_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $user));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function cannot_delete_user_with_water_monitorings()
    {
        $user = User::factory()->create();
        // Create related water monitoring data (simulated)
        // Note: This requires WaterMonitoring factory
        $this->markTestIncomplete('Requires WaterMonitoring factory to be implemented');
    }

    /** @test */
    public function cannot_delete_user_with_finance_reports()
    {
        $user = User::factory()->create();
        // Create related finance report data (simulated)
        // Note: This requires FinanceReport factory
        $this->markTestIncomplete('Requires FinanceReport factory to be implemented');
    }

    /** @test */
    public function admin_cannot_delete_themselves()
    {
        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $this->admin));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('error', 'Anda tidak dapat menghapus akun sendiri.');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    /** @test */
    public function cannot_delete_last_admin()
    {
        // Delete all admins except one
        User::where('role', 'admin')->where('id', '!=', $this->admin->id)->delete();

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $this->admin));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    /** @test */
    public function can_delete_admin_when_multiple_admins_exist()
    {
        $admin2 = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $admin2));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
    }

    // ==================== ROLE BEHAVIOR TESTS ====================

    /** @test */
    public function user_is_admin_method_works_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $petugas = User::factory()->create(['role' => 'petugas']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($petugas->isAdmin());
    }

    /** @test */
    public function user_is_petugas_method_works_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $petugas = User::factory()->create(['role' => 'petugas']);

        $this->assertFalse($admin->isPetugas());
        $this->assertTrue($petugas->isPetugas());
    }
}
