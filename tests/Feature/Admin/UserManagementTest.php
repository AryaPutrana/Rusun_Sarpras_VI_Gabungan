<?php

namespace Tests\Feature\Admin;

use App\Models\FinanceReport;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function admin_can_view_user_list()
    {
        $response = $this->actingAs($this->admin)->get(route('pengguna.index'));

        $response->assertStatus(200);
        $response->assertViewIs('users.index');
        $response->assertViewHas('users');
    }

    /** @test */
    public function petugas_cannot_access_user_management()
    {
        $petugas = User::factory()->create(['role' => 'petugas']);

        $response = $this->actingAs($petugas)->get(route('pengguna.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_user_with_valid_data()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil ditambahkan.');

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'petugas',
        ]);
    }

    /** @test */
    public function user_creation_requires_all_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    /** @test */
    public function user_creation_validates_email_format()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function user_creation_validates_unique_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertSessionHasErrors(['email']);
    }

    /** @test */
    public function user_creation_validates_password_min_length()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function user_creation_validates_password_confirmation()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => 'petugas',
        ];

        $response = $this->actingAs($this->admin)->post(route('pengguna.store'), $userData);

        $response->assertSessionHasErrors(['password']);
    }

    /** @test */
    public function user_creation_validates_role_values()
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

    /** @test */
    public function admin_can_edit_user()
    {
        $user = User::factory()->create(['role' => 'petugas']);

        $response = $this->actingAs($this->admin)->get(route('pengguna.edit', $user));

        $response->assertStatus(200);
        $response->assertViewIs('users.edit');
        $response->assertViewHas('pengguna', $user);
    }

    /** @test */
    public function admin_can_update_user_name_and_email()
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
            'role' => 'petugas', // Role should remain unchanged
        ]);
    }

    /** @test */
    public function admin_can_update_user_password()
    {
        $user = User::factory()->create(['role' => 'petugas']);
        $originalPassword = $user->password;

        $updateData = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);

        $response->assertRedirect(route('pengguna.index'));

        $user->refresh();
        $this->assertNotEquals($originalPassword, $user->password);
    }

    /** @test */
    public function user_update_does_not_change_role()
    {
        $user = User::factory()->create(['role' => 'petugas']);

        // Try to change role (should be ignored)
        $updateData = [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'admin', // This should be ignored
        ];

        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user), $updateData);

        $response->assertRedirect(route('pengguna.index'));

        $user->refresh();
        $this->assertEquals('petugas', $user->role); // Role unchanged
    }

    /** @test */
    public function user_update_validates_email_uniqueness_except_current()
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        // Update user1 with user2's email - should fail
        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user1), [
            'name' => $user1->name,
            'email' => 'user2@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);

        // Update user1 with their own email - should succeed
        $response = $this->actingAs($this->admin)->put(route('pengguna.update', $user1), [
            'name' => 'Updated Name',
            'email' => 'user1@example.com',
        ]);

        $response->assertRedirect(route('pengguna.index'));
    }

    /** @test */
    public function admin_can_delete_user_without_data()
    {
        $user = User::factory()->create(['role' => 'petugas']);

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $user));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
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
        // Delete any other admins that might exist, leaving only $this->admin
        User::where('role', 'admin')->where('id', '!=', $this->admin->id)->delete();
        
        // Only one admin exists ($this->admin)
        $this->assertEquals(1, User::where('role', 'admin')->count());

        $response = $this->actingAs($this->admin)
            ->delete(route('pengguna.destroy', $this->admin));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    /** @test */
    public function can_delete_admin_when_multiple_admins_exist()
    {
        // Delete any other admins that might exist, leaving only $this->admin
        User::where('role', 'admin')->where('id', '!=', $this->admin->id)->delete();
        
        $admin2 = User::factory()->create(['role' => 'admin']);

        $this->assertEquals(2, User::where('role', 'admin')->count());

        $response = $this->actingAs($this->admin)
            ->delete(route('pengguna.destroy', $admin2));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('success', 'Pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $admin2->id]);
    }

    /** @test */
    public function cannot_delete_user_with_monitoring_data()
    {
        $user = User::factory()->create(['role' => 'petugas']);
        
        // Create a water monitoring record for this user
        WaterMonitoring::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $user));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('error', 'Pengguna tidak dapat dihapus karena memiliki data pemeriksaan.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    /** @test */
    public function cannot_delete_user_with_finance_reports()
    {
        $user = User::factory()->create(['role' => 'petugas']);
        
        // Create a finance report for this user
        FinanceReport::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($this->admin)->delete(route('pengguna.destroy', $user));

        $response->assertRedirect(route('pengguna.index'));
        $response->assertSessionHas('error', 'Pengguna tidak dapat dihapus karena memiliki data laporan bulanan.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
