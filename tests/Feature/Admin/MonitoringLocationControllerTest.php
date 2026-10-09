<?php

namespace Tests\Feature\Admin;

use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringLocationControllerTest extends TestCase
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
    public function admin_can_access_monitoring_location_pages()
    {
        $response = $this->actingAs($this->admin)->get(route('lokasi.index'));
        $response->assertStatus(200);

        $response = $this->actingAs($this->admin)->get(route('lokasi.create'));
        $response->assertStatus(200);

        $location = MonitoringLocation::factory()->create();
        $response = $this->actingAs($this->admin)->get(route('lokasi.edit', $location));
        $response->assertStatus(200);
    }

    /** @test */
    public function petugas_cannot_access_monitoring_location_pages()
    {
        $response = $this->actingAs($this->petugas)->get(route('lokasi.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->petugas)->get(route('lokasi.create'));
        $response->assertStatus(403);

        $location = MonitoringLocation::factory()->create();
        $response = $this->actingAs($this->petugas)->get(route('lokasi.edit', $location));
        $response->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_access_monitoring_location_management()
    {
        $response = $this->get(route('lokasi.index'));
        $response->assertRedirect(route('login'));
    }

    // ==================== CREATE LOCATION TESTS ====================

    /** @test */
    public function admin_can_create_monitoring_location_with_aktif_status()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Monitoring A',
            'keterangan' => 'Keterangan lokasi monitoring A',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil ditambahkan.');

        $this->assertDatabaseHas('monitoring_locations', [
            'nama_lokasi' => 'Lokasi Monitoring A',
            'keterangan' => 'Keterangan lokasi monitoring A',
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function admin_can_create_monitoring_location_with_nonaktif_status()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Monitoring B',
            'keterangan' => 'Keterangan lokasi monitoring B',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);

        $response->assertRedirect(route('lokasi.index'));

        $this->assertDatabaseHas('monitoring_locations', [
            'nama_lokasi' => 'Lokasi Monitoring B',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_create_monitoring_location_without_keterangan()
    {
        $locationData = [
            'nama_lokasi' => 'Lokasi Monitoring C',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);

        $response->assertRedirect(route('lokasi.index'));

        $this->assertDatabaseHas('monitoring_locations', [
            'nama_lokasi' => 'Lokasi Monitoring C',
            'status' => 'aktif',
            'keterangan' => null,
        ]);
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function cannot_create_monitoring_location_without_required_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), []);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function cannot_create_monitoring_location_with_duplicate_name()
    {
        MonitoringLocation::factory()->create(['nama_lokasi' => 'Existing Location']);

        $locationData = [
            'nama_lokasi' => 'Existing Location',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    /** @test */
    public function cannot_create_monitoring_location_with_invalid_status()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);
        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function cannot_create_monitoring_location_with_name_exceeding_max_length()
    {
        $locationData = [
            'nama_lokasi' => str_repeat('a', 256), // 256 characters, max is 255
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    // ==================== UPDATE LOCATION TESTS ====================

    /** @test */
    public function admin_can_update_monitoring_location()
    {
        $location = MonitoringLocation::factory()->create([
            'nama_lokasi' => 'Old Location Name',
            'keterangan' => 'Old keterangan',
            'status' => 'aktif',
        ]);

        $updateData = [
            'nama_lokasi' => 'Updated Location Name',
            'keterangan' => 'Updated keterangan',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), $updateData);

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil diperbarui.');

        $this->assertDatabaseHas('monitoring_locations', [
            'id' => $location->id,
            'nama_lokasi' => 'Updated Location Name',
            'keterangan' => 'Updated keterangan',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_change_monitoring_location_status_from_aktif_to_nonaktif()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'aktif']);

        $updateData = [
            'nama_lokasi' => $location->nama_lokasi,
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), $updateData);

        $response->assertRedirect(route('lokasi.index'));

        $this->assertDatabaseHas('monitoring_locations', [
            'id' => $location->id,
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function admin_can_change_monitoring_location_status_from_nonaktif_to_aktif()
    {
        $location = MonitoringLocation::factory()->create(['status' => 'nonaktif']);

        $updateData = [
            'nama_lokasi' => $location->nama_lokasi,
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), $updateData);

        $response->assertRedirect(route('lokasi.index'));

        $this->assertDatabaseHas('monitoring_locations', [
            'id' => $location->id,
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function cannot_update_monitoring_location_name_to_duplicate()
    {
        $location1 = MonitoringLocation::factory()->create(['nama_lokasi' => 'Location 1']);
        $location2 = MonitoringLocation::factory()->create(['nama_lokasi' => 'Location 2']);

        $updateData = [
            'nama_lokasi' => 'Location 2',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location1), $updateData);
        $response->assertSessionHasErrors(['nama_lokasi']);
    }

    /** @test */
    public function can_update_monitoring_location_with_same_name()
    {
        $location = MonitoringLocation::factory()->create(['nama_lokasi' => 'Test Location']);

        $updateData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Updated keterangan',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), $updateData);
        $response->assertRedirect(route('lokasi.index'));
    }

    // ==================== DELETE LOCATION TESTS ====================

    /** @test */
    public function admin_can_delete_monitoring_location_without_related_data()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('lokasi.destroy', $location));

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil dihapus.');

        $this->assertDatabaseMissing('monitoring_locations', ['id' => $location->id]);
    }

    /** @test */
    public function cannot_delete_monitoring_location_with_water_monitorings()
    {
        $location = MonitoringLocation::factory()->create();
        // Create related water monitoring data (simulated)
        // Note: This requires WaterMonitoring factory
        $this->markTestIncomplete('Requires WaterMonitoring factory to test cascade prevention');
    }

    // ==================== STATUS TESTS ====================

    /** @test */
    public function monitoring_location_status_constants_are_correct()
    {
        $expectedStatuses = [
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
        ];

        $this->assertEquals($expectedStatuses, MonitoringLocation::STATUS);
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

            $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);
            $response->assertRedirect(route('lokasi.index'));
        }
    }

    // ==================== INDEX PAGE TESTS ====================

    /** @test */
    public function monitoring_location_index_shows_all_locations()
    {
        MonitoringLocation::factory()->create(['nama_lokasi' => 'Location A']);
        MonitoringLocation::factory()->create(['nama_lokasi' => 'Location B']);
        MonitoringLocation::factory()->create(['nama_lokasi' => 'Location C']);

        $response = $this->actingAs($this->admin)->get(route('lokasi.index'));

        $response->assertStatus(200);
        $response->assertSee('Location A');
        $response->assertSee('Location B');
        $response->assertSee('Location C');
    }

    /** @test */
    public function monitoring_location_index_is_paginated()
    {
        // Create 15 locations (pagination is 10 per page)
        MonitoringLocation::factory()->count(15)->create();

        $response = $this->actingAs($this->admin)->get(route('lokasi.index'));

        $response->assertStatus(200);
        // Check that pagination links exist
        $response->assertViewHas('locations', function ($locations) {
            return $locations instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                && $locations->perPage() === 10;
        });
    }
}
