<?php

namespace Tests\Feature\Admin;

use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
    }

    /** @test */
    public function admin_can_view_location_list()
    {
        MonitoringLocation::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('lokasi.index'));

        $response->assertStatus(200);
        $response->assertViewIs('locations.index');
        $response->assertViewHas('locations');
    }

    /** @test */
    public function petugas_cannot_access_location_management()
    {
        $response = $this->actingAs($this->petugas)->get(route('lokasi.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_create_form()
    {
        $response = $this->actingAs($this->admin)->get(route('lokasi.create'));

        $response->assertStatus(200);
        $response->assertViewIs('locations.create');
        $response->assertViewHas('statusOptions');
    }

    /** @test */
    public function admin_can_create_location_with_valid_data()
    {
        $locationData = [
            'nama_lokasi' => 'Kolam Renang A',
            'keterangan' => 'Lokasi untuk monitoring air',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil ditambahkan.');

        $this->assertDatabaseHas('monitoring_locations', [
            'nama_lokasi' => 'Kolam Renang A',
            'keterangan' => 'Lokasi untuk monitoring air',
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function location_creation_requires_all_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), []);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function location_creation_validates_status_values()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Test description',
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasi.store'), $locationData);

        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function admin_can_view_edit_form()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('lokasi.edit', $location));

        $response->assertStatus(200);
        $response->assertViewIs('locations.edit');
        $response->assertViewHas('lokasi', $location);
        $response->assertViewHas('statusOptions');
    }

    /** @test */
    public function admin_can_update_location()
    {
        $location = MonitoringLocation::factory()->create([
            'nama_lokasi' => 'Old Name',
            'keterangan' => 'Old description',
            'status' => 'aktif',
        ]);

        $updateData = [
            'nama_lokasi' => 'Updated Name',
            'keterangan' => 'Updated description',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), $updateData);

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil diperbarui.');

        $this->assertDatabaseHas('monitoring_locations', [
            'id' => $location->id,
            'nama_lokasi' => 'Updated Name',
            'keterangan' => 'Updated description',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function location_update_validates_required_fields()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('lokasi.update', $location), [
            'nama_lokasi' => '',
            'status' => '',
        ]);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function admin_can_delete_location_without_data()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('lokasi.destroy', $location));

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('success', 'Lokasi monitoring berhasil dihapus.');

        $this->assertDatabaseMissing('monitoring_locations', ['id' => $location->id]);
    }

    /** @test */
    public function cannot_delete_location_with_monitoring_records()
    {
        $location = MonitoringLocation::factory()->create();
        
        // Create water monitoring records for this location
        WaterMonitoring::factory()->create([
            'location_id' => $location->id,
            'user_id' => $this->petugas->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('lokasi.destroy', $location));

        $response->assertRedirect(route('lokasi.index'));
        $response->assertSessionHas('error', 'Lokasi tidak dapat dihapus karena memiliki data pemeriksaan.');

        $this->assertDatabaseHas('monitoring_locations', ['id' => $location->id]);
    }

    /** @test */
    public function location_list_shows_monitoring_count()
    {
        $location = MonitoringLocation::factory()->create();
        WaterMonitoring::factory()->count(3)->create([
            'location_id' => $location->id,
            'user_id' => $this->petugas->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('lokasi.index'));

        $response->assertStatus(200);
        
        $locations = $response->viewData('locations');
        $firstLocation = $locations->first();
        
        $this->assertEquals(3, $firstLocation->water_monitorings_count);
    }

    /** @test */
    public function petugas_cannot_create_location()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Test description',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->petugas)->post(route('lokasi.store'), $locationData);

        $response->assertStatus(403);
    }

    /** @test */
    public function petugas_cannot_update_location()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->petugas)->put(route('lokasi.update', $location), [
            'nama_lokasi' => 'Updated',
            'keterangan' => 'Updated',
            'status' => 'aktif',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function petugas_cannot_delete_location()
    {
        $location = MonitoringLocation::factory()->create();

        $response = $this->actingAs($this->petugas)->delete(route('lokasi.destroy', $location));

        $response->assertStatus(403);
    }
}
