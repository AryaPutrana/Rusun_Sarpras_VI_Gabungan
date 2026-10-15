<?php

namespace Tests\Feature\Admin;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLocationControllerTest extends TestCase
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
    public function admin_can_access_finance_location_pages()
    {
        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.create'));
        $response->assertStatus(200);

        $location = FinanceLocation::factory()->create();
        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.edit', $location));
        $response->assertStatus(200);
    }

    /** @test */
    public function petugas_cannot_access_finance_location_pages()
    {
        $response = $this->actingAs($this->petugas)->get(route('lokasiBulanan.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->petugas)->get(route('lokasiBulanan.create'));
        $response->assertStatus(403);

        $location = FinanceLocation::factory()->create();
        $response = $this->actingAs($this->petugas)->get(route('lokasiBulanan.edit', $location));
        $response->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_access_finance_location_management()
    {
        $response = $this->get(route('lokasiBulanan.index'));
        $response->assertRedirect(route('login'));
    }

    // ==================== CREATE LOCATION TESTS ====================

    /** @test */
    public function admin_can_create_finance_location_with_aktif_status()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Bulanan A',
            'keterangan' => 'Keterangan lokasi bulanan A',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil ditambahkan.');

        $this->assertDatabaseHas('finance_locations', [
            'nama_lokasi' => 'Lokasi Bulanan A',
            'keterangan' => 'Keterangan lokasi bulanan A',
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function admin_can_create_finance_location_with_nonaktif_status()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Bulanan B',
            'keterangan' => 'Keterangan lokasi bulanan B',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertRedirect(route('lokasiBulanan.index'));

        $this->assertDatabaseHas('finance_locations', [
            'nama_lokasi' => 'Lokasi Bulanan B',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_create_finance_location_without_keterangan()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Bulanan C',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertRedirect(route('lokasiBulanan.index'));

        $this->assertDatabaseHas('finance_locations', [
            'nama_lokasi' => 'Lokasi Bulanan C',
            'status' => 'aktif',
            'keterangan' => null,
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function cannot_create_finance_location_without_required_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), []);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function cannot_create_finance_location_with_duplicate_name()
    {
        FinanceLocation::factory()->create(['nama_lokasi' => 'Existing Location']);

        $locationData = [
            'nama_lokasi' => 'Existing Location',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    /** @test */
    public function cannot_create_finance_location_with_invalid_status()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);
        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function cannot_create_finance_location_with_name_exceeding_max_length()
    {
        $locationData = [
            'nama_lokasi' => str_repeat('a', 256), // 256 characters, max is 255
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    // ==================== UPDATE LOCATION TESTS ====================

    /** @test */
    public function admin_can_update_finance_location()
    {
        $location = FinanceLocation::factory()->create([
            'nama_lokasi' => 'Old Location Name',
            'keterangan' => 'Old keterangan',
            'status' => 'aktif',
        ]);

        $updateData = [
            'nama_lokasi' => 'Updated Location Name',
            'keterangan' => 'Updated keterangan',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), $updateData);

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil diperbarui.');

        $this->assertDatabaseHas('finance_locations', [
            'id' => $location->id,
            'nama_lokasi' => 'Updated Location Name',
            'keterangan' => 'Updated keterangan',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_change_finance_location_status_from_aktif_to_nonaktif()
    {
        $location = FinanceLocation::factory()->create(['status' => 'aktif']);

        $updateData = [
            'nama_lokasi' => $location->nama_lokasi,
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), $updateData);

        $response->assertRedirect(route('lokasiBulanan.index'));

        $this->assertDatabaseHas('finance_locations', [
            'id' => $location->id,
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_change_finance_location_status_from_nonaktif_to_aktif()
    {
        $location = FinanceLocation::factory()->create(['status' => 'nonaktif']);

        $updateData = [
            'nama_lokasi' => $location->nama_lokasi,
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), $updateData);

        $response->assertRedirect(route('lokasiBulanan.index'));

        $this->assertDatabaseHas('finance_locations', [
            'id' => $location->id,
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function cannot_update_finance_location_name_to_duplicate()
    {
        $location1 = FinanceLocation::factory()->create(['nama_lokasi' => 'Location 1']);
        $location2 = FinanceLocation::factory()->create(['nama_lokasi' => 'Location 2']);

        $updateData = [
            'nama_lokasi' => 'Location 2',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location1), $updateData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    /** @test */
    public function can_update_finance_location_with_same_name()
    {
        $location = FinanceLocation::factory()->create(['nama_lokasi' => 'Test Location']);

        $updateData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Updated keterangan',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), $updateData);
        $response->assertRedirect(route('lokasiBulanan.index'));
    }

    // ==================== DELETE LOCATION TESTS ====================

    /** @test */
    public function admin_can_delete_finance_location_without_related_data()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('lokasiBulanan.destroy', $location));

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil dihapus.');

        $this->assertDatabaseMissing('finance_locations', ['id' => $location->id]);
    }

    /** @test */
    public function cannot_delete_finance_location_with_finance_reports()
    {
        $location = FinanceLocation::factory()->create();
        
        // Create finance report linked to this location
        FinanceReport::factory()->create([
            'location_id' => $location->id,
        ]);
        
        $response = $this->actingAs($this->admin)
            ->delete(route('lokasiBulanan.destroy', $location));
        
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('finance_locations', ['id' => $location->id]);
    }

    // ==================== STATUS TESTS ====================

    /** @test */
    public function finance_location_status_constants_are_correct()
    {
        $expectedStatuses = [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];

        $this->assertEquals($expectedStatuses, FinanceLocation::STATUS);
    }

    /** @test */
    public function only_valid_statuses_are_accepted()
    {
        $validStatuses = ['aktif', 'nonaktif'];

        foreach ($validStatuses as $status) {
            $locationData = [
                'nama_lokasi' => "Location with {$status}",
                'status' => $status,
            ];

            $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);
            $response->assertRedirect(route('lokasiBulanan.index'));
        }
    }

    // ==================== INDEX PAGE TESTS ====================

    /** @test */
    public function finance_location_index_shows_all_locations()
    {
        FinanceLocation::factory()->create(['nama_lokasi' => 'Location A']);
        FinanceLocation::factory()->create(['nama_lokasi' => 'Location B']);
        FinanceLocation::factory()->create(['nama_lokasi' => 'Location C']);

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.index'));

        $response->assertStatus(200);
        $response->assertSee('Location A');
        $response->assertSee('Location B');
        $response->assertSee('Location C');
    }

    /** @test */
    public function finance_location_index_is_paginated()
    {
        // Create 15 locations (pagination is 10 per page)
        FinanceLocation::factory()->count(15)->create();

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.index'));

        $response->assertStatus(200);
        // Check that pagination links exist
        $response->assertViewHas('locations', function ($locations) {
            return $locations instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                && $locations->perPage() === 10;
        });
    }

    // ==================== RELATIONSHIP TESTS ====================

    /** @test */
    public function finance_location_has_finance_reports_relationship()
    {
        $location = FinanceLocation::factory()->create();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $location->financeReports()
        );
    }
}
