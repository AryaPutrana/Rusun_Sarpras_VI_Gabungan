<?php

namespace Tests\Feature\Admin;

use App\Models\FinanceLocation;
use App\Models\FinanceReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLocationTest extends TestCase
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
    public function admin_can_view_finance_location_list()
    {
        FinanceLocation::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.index'));

        $response->assertStatus(200);
        $response->assertViewIs('finance_locations.index');
        $response->assertViewHas('locations');
    }

    /** @test */
    public function petugas_cannot_access_finance_location_management()
    {
        $response = $this->actingAs($this->petugas)->get(route('lokasiBulanan.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_create_form()
    {
        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.create'));

        $response->assertStatus(200);
        $response->assertViewIs('finance_locations.create');
        $response->assertViewHas('statusOptions');
    }

    /** @test */
    public function admin_can_create_finance_location_with_valid_data()
    {
        $locationData = [
            'nama_lokasi' => 'Kantor Cabang A',
            'keterangan' => 'Lokasi untuk laporan bulanan',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil ditambahkan.');

        $this->assertDatabaseHas('finance_locations', [
            'nama_lokasi' => 'Kantor Cabang A',
            'keterangan' => 'Lokasi untuk laporan bulanan',
            'status' => 'aktif',
        ]);
    }

    /** @test */
    public function finance_location_creation_requires_all_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), []);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function finance_location_creation_validates_status_values()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Test description',
            'status' => 'invalid_status',
        ];

        $response = $this->actingAs($this->admin)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertSessionHasErrors(['status']);
    }

    /** @test */
    public function admin_can_view_edit_form()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.edit', $location));

        $response->assertStatus(200);
        $response->assertViewIs('finance_locations.edit');
        $response->assertViewHas('lokasi', $location);
        $response->assertViewHas('statusOptions');
    }

    /** @test */
    public function admin_can_update_finance_location()
    {
        $location = FinanceLocation::factory()->create([
            'nama_lokasi' => 'Old Finance Name',
            'keterangan' => 'Old description',
            'status' => 'aktif',
        ]);

        $updateData = [
            'nama_lokasi' => 'Updated Finance Name',
            'keterangan' => 'Updated description',
            'status' => 'nonaktif',
        ];

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), $updateData);

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil diperbarui.');

        $this->assertDatabaseHas('finance_locations', [
            'id' => $location->id,
            'nama_lokasi' => 'Updated Finance Name',
            'keterangan' => 'Updated description',
            'status' => 'nonaktif',
        ]);
    }

    /** @test */
    public function finance_location_update_validates_required_fields()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->admin)->put(route('lokasiBulanan.update', $location), [
            'nama_lokasi' => '',
            'status' => '',
        ]);

        $response->assertSessionHasErrors(['nama_lokasi', 'status']);
    }

    /** @test */
    public function admin_can_delete_finance_location_without_data()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->admin)->delete(route('lokasiBulanan.destroy', $location));

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('success', 'Lokasi bulanan berhasil dihapus.');

        $this->assertDatabaseMissing('finance_locations', ['id' => $location->id]);
    }

    /** @test */
    public function cannot_delete_finance_location_with_reports()
    {
        $location = FinanceLocation::factory()->create();
        
        // Create finance reports for this location
        FinanceReport::factory()->create([
            'location_id' => $location->id,
            'user_id' => $this->petugas->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('lokasiBulanan.destroy', $location));

        $response->assertRedirect(route('lokasiBulanan.index'));
        $response->assertSessionHas('error', 'Lokasi tidak dapat dihapus karena memiliki data laporan bulanan.');

        $this->assertDatabaseHas('finance_locations', ['id' => $location->id]);
    }

    /** @test */
    public function finance_location_list_shows_report_count()
    {
        $location = FinanceLocation::factory()->create();
        FinanceReport::factory()->count(5)->create([
            'location_id' => $location->id,
            'user_id' => $this->petugas->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('lokasiBulanan.index'));

        $response->assertStatus(200);
        
        $locations = $response->viewData('locations');
        $firstLocation = $locations->first();
        
        $this->assertEquals(5, $firstLocation->finance_reports_count);
    }

    /** @test */
    public function petugas_cannot_create_finance_location()
    {
        $locationData = [
            'nama_lokasi' => 'Test Location',
            'keterangan' => 'Test description',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->petugas)->post(route('lokasiBulanan.store'), $locationData);

        $response->assertStatus(403);
    }

    /** @test */
    public function petugas_cannot_update_finance_location()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->petugas)->put(route('lokasiBulanan.update', $location), [
            'nama_lokasi' => 'Updated',
            'keterangan' => 'Updated',
            'status' => 'aktif',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function petugas_cannot_delete_finance_location()
    {
        $location = FinanceLocation::factory()->create();

        $response = $this->actingAs($this->petugas)->delete(route('lokasiBulanan.destroy', $location));

        $response->assertStatus(403);
    }
}
