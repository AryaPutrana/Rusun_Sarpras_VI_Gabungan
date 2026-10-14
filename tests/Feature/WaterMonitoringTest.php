<?php

namespace Tests\Feature;

use App\Models\MonitoringLocation;
use App\Models\User;
use App\Models\WaterMonitoring;
use App\Support\PhotoStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WaterMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $petugas1;
    protected User $petugas2;
    protected MonitoringLocation $activeLocation;
    protected MonitoringLocation $inactiveLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup users
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas1 = User::factory()->create(['role' => 'petugas']);
        $this->petugas2 = User::factory()->create(['role' => 'petugas']);

        // Setup locations
        $this->activeLocation = MonitoringLocation::factory()->create([
            'nama_lokasi' => 'Lokasi Aktif',
            'status' => 'aktif',
        ]);
        $this->inactiveLocation = MonitoringLocation::factory()->create([
            'nama_lokasi' => 'Lokasi Nonaktif',
            'status' => 'nonaktif',
        ]);

        // Setup storage
        Storage::fake(PhotoStorage::EVIDENCE);
    }

    // ==================== CREATE TESTS ====================

    /** @test */
    public function petugas_can_view_create_form()
    {
        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.create'));

        $response->assertStatus(200);
        $response->assertViewIs('monitoring.create');
        $response->assertViewHas('locations');
        $response->assertViewHas('sesiOptions', WaterMonitoring::SESI);
        $response->assertViewHas('kondisiOptions', WaterMonitoring::KONDISI);
    }

    /** @test */
    public function create_form_only_shows_active_locations()
    {
        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.create'));

        $locations = $response->viewData('locations');
        
        $this->assertTrue($locations->contains($this->activeLocation));
        $this->assertFalse($locations->contains($this->inactiveLocation));
    }

    /** @test */
    public function petugas_can_create_monitoring_record_with_photo()
    {
        $photo = UploadedFile::fake()->image('test.jpg', 800, 600)->size(1024);

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Air mengalir lancar',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Pemeriksaan berhasil disimpan.');

        $this->assertDatabaseHas('water_monitorings', [
            'user_id' => $this->petugas1->id,
            'location_id' => $this->activeLocation->id,
            'sesi' => 'pagi',
            'waktu' => '08:00:00',
            'kondisi' => 'normal',
            'keterangan' => 'Air mengalir lancar',
        ]);

        $monitoring = WaterMonitoring::first();
        $this->assertEquals(today()->format('Y-m-d'), $monitoring->tanggal->format('Y-m-d'));
        $this->assertNotNull($monitoring->foto);
        Storage::disk(PhotoStorage::EVIDENCE)->assertExists($monitoring->foto);
    }

    /** @test */
    public function create_shows_warning_for_abnormal_conditions()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'siang',
            'kondisi' => 'debit_turun',
            'keterangan' => 'Debit air menurun drastis',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('PERLU PERHATIAN', session('error'));
    }

    /** @test */
    public function waktu_is_auto_set_based_on_sesi()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $testCases = [
            'pagi' => '08:00:00',
            'siang' => '12:00:00',
            'sore' => '16:00:00',
        ];

        foreach ($testCases as $sesi => $expectedWaktu) {
            $data = [
                'location_id' => $this->activeLocation->id,
                'tanggal' => today()->subDays(array_search($sesi, array_keys($testCases)))->format('Y-m-d'),
                'sesi' => $sesi,
                'kondisi' => 'normal',
                'keterangan' => "Test {$sesi}",
                'foto' => UploadedFile::fake()->image("test_{$sesi}.jpg"),
            ];

            $this->actingAs($this->petugas1)
                ->post(route('monitoring.store'), $data);

            $this->assertDatabaseHas('water_monitorings', [
                'sesi' => $sesi,
                'waktu' => $expectedWaktu,
            ]);
        }
    }

    // ==================== VALIDATION TESTS ====================

    /** @test */
    public function create_requires_all_fields()
    {
        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), []);

        $response->assertSessionHasErrors([
            'location_id',
            'tanggal',
            'sesi',
            'kondisi',
            'keterangan',
            'foto',
        ]);
    }

    /** @test */
    public function create_requires_photo_upload()
    {
        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Test tanpa foto',
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['foto']);
    }

    /** @test */
    public function create_rejects_invalid_photo_types()
    {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1024);

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Test dengan PDF',
            'foto' => $invalidFile,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['foto']);
    }

    /** @test */
    public function create_rejects_oversized_photos()
    {
        $largePhoto = UploadedFile::fake()->image('large.jpg')->size(6000); // 6MB

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Test foto terlalu besar',
            'foto' => $largePhoto,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['foto']);
    }

    /** @test */
    public function create_rejects_future_dates()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->addDay()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Test tanggal masa depan',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['tanggal']);
    }

    /** @test */
    public function create_rejects_inactive_locations()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->inactiveLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Test lokasi nonaktif',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['location_id']);
    }

    /** @test */
    public function create_rejects_duplicate_location_date_sesi()
    {
        $today = today();
        
        $existing = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'location_id' => $this->activeLocation->id,
            'tanggal' => $today,
            'sesi' => 'pagi',
        ]);
        
        // Verify existing record was created
        $this->assertDatabaseHas('water_monitorings', [
            'location_id' => $this->activeLocation->id,
            'sesi' => 'pagi',
        ]);

        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => $today->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Duplikat',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        // Should redirect back with validation error
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['location_id']);
        
        // Ensure only one record exists
        $this->assertEquals(1, WaterMonitoring::where('location_id', $this->activeLocation->id)
            ->whereDate('tanggal', $today)
            ->where('sesi', 'pagi')
            ->count());
    }

    /** @test */
    public function create_accepts_same_location_different_sesi()
    {
        $existing = WaterMonitoring::factory()->create([
            'location_id' => $this->activeLocation->id,
            'tanggal' => today(),
            'sesi' => 'pagi',
        ]);

        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'siang', // Different sesi
            'kondisi' => 'normal',
            'keterangan' => 'Sesi berbeda',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('water_monitorings', 2);
    }

    /** @test */
    public function create_rejects_invalid_sesi()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'malam', // Invalid
            'kondisi' => 'normal',
            'keterangan' => 'Sesi invalid',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['sesi']);
    }

    /** @test */
    public function create_rejects_invalid_kondisi()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'rusak_parah', // Invalid
            'keterangan' => 'Kondisi invalid',
            'foto' => $photo,
        ];

        $response = $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $response->assertSessionHasErrors(['kondisi']);
    }

    // ==================== READ/INDEX TESTS ====================

    /** @test */
    public function admin_can_see_all_monitoring_records()
    {
        $monitoring1 = WaterMonitoring::factory()->create(['user_id' => $this->petugas1->id]);
        $monitoring2 = WaterMonitoring::factory()->create(['user_id' => $this->petugas2->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.index'));

        $response->assertStatus(200);
        $response->assertViewIs('monitoring.index');
        
        // Check that both records are visible in the data
        $monitorings = $response->viewData('monitorings');
        $this->assertTrue($monitorings->contains('id', $monitoring1->id));
        $this->assertTrue($monitorings->contains('id', $monitoring2->id));
    }

    /** @test */
    public function petugas_only_sees_own_monitoring_records()
    {
        $ownMonitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'keterangan' => 'Milik petugas1',
        ]);
        $otherMonitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
            'keterangan' => 'Milik petugas2',
        ]);

        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.index'));

        $response->assertStatus(200);
        $response->assertSee('Milik petugas1');
        $response->assertDontSee('Milik petugas2');
    }

    /** @test */
    public function index_can_filter_by_date()
    {
        $today = WaterMonitoring::factory()->create([
            'tanggal' => today(),
            'keterangan' => 'Today record',
        ]);
        $yesterday = WaterMonitoring::factory()->create([
            'tanggal' => today()->subDay(),
            'keterangan' => 'Yesterday record',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.index', ['tanggal' => today()->format('Y-m-d')]));

        $response->assertStatus(200);
        $response->assertSee('Today record');
        $response->assertDontSee('Yesterday record');
    }

    /** @test */
    public function index_can_filter_by_location()
    {
        $location1 = MonitoringLocation::factory()->create(['nama_lokasi' => 'Lokasi A']);
        $location2 = MonitoringLocation::factory()->create(['nama_lokasi' => 'Lokasi B']);

        $monitoring1 = WaterMonitoring::factory()->create([
            'location_id' => $location1->id,
            'keterangan' => 'At location A',
        ]);
        $monitoring2 = WaterMonitoring::factory()->create([
            'location_id' => $location2->id,
            'keterangan' => 'At location B',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.index', ['lokasi' => $location1->id]));

        $response->assertStatus(200);
        $response->assertSee('At location A');
        $response->assertDontSee('At location B');
    }

    /** @test */
    public function index_is_paginated()
    {
        WaterMonitoring::factory()->count(15)->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.index'));

        $response->assertStatus(200);
        $this->assertCount(10, $response->viewData('monitorings'));
    }

    // ==================== SHOW TESTS ====================

    /** @test */
    public function petugas_can_view_own_monitoring_detail()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'keterangan' => 'Detail test',
        ]);

        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.show', $monitoring));

        $response->assertStatus(200);
        $response->assertViewIs('monitoring.show');
        $response->assertSee('Detail test');
    }

    /** @test */
    public function petugas_cannot_view_others_monitoring_detail()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
        ]);

        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.show', $monitoring));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_any_monitoring_detail()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.show', $monitoring));

        $response->assertStatus(200);
    }

    // ==================== UPDATE TESTS ====================

    /** @test */
    public function petugas_can_view_edit_form_for_own_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.edit', $monitoring));

        $response->assertStatus(200);
        $response->assertViewIs('monitoring.edit');
        $response->assertViewHas('monitoring', $monitoring);
    }

    /** @test */
    public function petugas_cannot_edit_others_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
        ]);

        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.edit', $monitoring));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_edit_any_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.edit', $monitoring));

        $response->assertStatus(200);
    }

    /** @test */
    public function petugas_can_update_own_monitoring_without_changing_photo()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'kondisi' => 'normal',
            'keterangan' => 'Original keterangan',
        ]);

        $oldPhoto = $monitoring->foto;

        $data = [
            'location_id' => $monitoring->location_id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'debit_turun',
            'keterangan' => 'Updated keterangan',
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertRedirect(route('dashboard'));
        
        $monitoring->refresh();
        $this->assertEquals('debit_turun', $monitoring->kondisi);
        $this->assertEquals('Updated keterangan', $monitoring->keterangan);
        $this->assertEquals($oldPhoto, $monitoring->foto);
    }

    /** @test */
    public function update_can_replace_photo()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $oldPhoto = $monitoring->foto;
        Storage::disk(PhotoStorage::EVIDENCE)->put($oldPhoto, 'old content');

        $newPhoto = UploadedFile::fake()->image('new.jpg');

        $data = [
            'location_id' => $monitoring->location_id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => $monitoring->kondisi,
            'keterangan' => $monitoring->keterangan,
            'foto' => $newPhoto,
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertRedirect(route('dashboard'));
        
        $monitoring->refresh();
        $this->assertNotEquals($oldPhoto, $monitoring->foto);
        Storage::disk(PhotoStorage::EVIDENCE)->assertExists($monitoring->foto);
        Storage::disk(PhotoStorage::EVIDENCE)->assertMissing($oldPhoto);
    }

    /** @test */
    public function update_photo_is_optional()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $data = [
            'location_id' => $monitoring->location_id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'normal',
            'keterangan' => 'Updated without photo',
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function update_allows_inactive_location_if_currently_assigned()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'location_id' => $this->inactiveLocation->id,
        ]);

        $data = [
            'location_id' => $this->inactiveLocation->id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'normal',
            'keterangan' => 'Updated with inactive location',
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function update_unique_constraint_ignores_self()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
            'location_id' => $this->activeLocation->id,
            'tanggal' => today(),
            'sesi' => 'pagi',
        ]);

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => 'Same location, date, sesi',
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function petugas_cannot_update_others_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
        ]);

        $data = [
            'location_id' => $monitoring->location_id,
            'tanggal' => $monitoring->tanggal->format('Y-m-d'),
            'sesi' => $monitoring->sesi,
            'kondisi' => 'normal',
            'keterangan' => 'Unauthorized update',
        ];

        $response = $this->actingAs($this->petugas1)
            ->put(route('monitoring.update', $monitoring), $data);

        $response->assertStatus(403);
    }

    // ==================== DELETE TESTS ====================

    /** @test */
    public function petugas_can_delete_own_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $photoPath = $monitoring->foto;
        Storage::disk(PhotoStorage::EVIDENCE)->put($photoPath, 'content');

        $response = $this->actingAs($this->petugas1)
            ->delete(route('monitoring.destroy', $monitoring));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Data pemeriksaan berhasil dihapus.');
        
        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
        Storage::disk(PhotoStorage::EVIDENCE)->assertMissing($photoPath);
    }

    /** @test */
    public function petugas_cannot_delete_others_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
        ]);

        $response = $this->actingAs($this->petugas1)
            ->delete(route('monitoring.destroy', $monitoring));

        $response->assertStatus(403);
        $this->assertDatabaseHas('water_monitorings', ['id' => $monitoring->id]);
    }

    /** @test */
    public function admin_can_delete_any_monitoring()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('monitoring.destroy', $monitoring));

        $response->assertRedirect();
        $this->assertDatabaseMissing('water_monitorings', ['id' => $monitoring->id]);
    }

    // ==================== PHOTO ACCESS TESTS ====================

    /** @test */
    public function petugas_can_view_own_monitoring_photo()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        Storage::disk(PhotoStorage::EVIDENCE)->put($monitoring->foto, 'photo content');

        $response = $this->actingAs($this->petugas1)
            ->get(route('foto.monitoring', $monitoring));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        // Laravel's file response may set different cache headers in test vs production
        $this->assertNotNull($response->headers->get('Cache-Control'));
    }

    /** @test */
    public function petugas_cannot_view_others_monitoring_photo()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas2->id,
        ]);

        Storage::disk(PhotoStorage::EVIDENCE)->put($monitoring->foto, 'photo content');

        $response = $this->actingAs($this->petugas1)
            ->get(route('foto.monitoring', $monitoring));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_any_monitoring_photo()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        Storage::disk(PhotoStorage::EVIDENCE)->put($monitoring->foto, 'photo content');

        $response = $this->actingAs($this->admin)
            ->get(route('foto.monitoring', $monitoring));

        $response->assertStatus(200);
    }

    /** @test */
    public function photo_endpoint_returns_404_for_missing_file()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        // Don't create the file

        $response = $this->actingAs($this->petugas1)
            ->get(route('foto.monitoring', $monitoring));

        $response->assertStatus(404);
    }

    /** @test */
    public function foto_url_contains_version_token_when_photo_exists()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        Storage::disk(PhotoStorage::EVIDENCE)->put($monitoring->foto, 'photo content');

        $url = $monitoring->fotoUrl('foto.monitoring');

        $this->assertStringContainsString(
            '?v='.rawurlencode(basename($monitoring->foto)),
            $url
        );
    }

    /** @test */
    public function foto_url_version_changes_when_photo_replaced()
    {
        $monitoring = WaterMonitoring::factory()->create([
            'user_id' => $this->petugas1->id,
        ]);

        Storage::disk(PhotoStorage::EVIDENCE)->put($monitoring->foto, 'old photo');
        $oldUrl = $monitoring->fotoUrl('foto.monitoring');

        $newPath = 'monitoring/'.uniqid().'.png';
        Storage::disk(PhotoStorage::EVIDENCE)->put($newPath, 'new photo');
        $monitoring->forceFill(['foto' => $newPath])->save();

        $newUrl = $monitoring->fresh()->fotoUrl('foto.monitoring');

        $this->assertStringContainsString('?v=', $oldUrl);
        $this->assertStringContainsString('?v=', $newUrl);
        $this->assertNotSame($oldUrl, $newUrl);
    }

    // ==================== EDGE CASE TESTS ====================

    /** @test */
    public function xss_in_keterangan_is_escaped()
    {
        $xssPayload = '<script>alert("XSS")</script>';
        
        $photo = UploadedFile::fake()->image('test.jpg');

        $data = [
            'location_id' => $this->activeLocation->id,
            'tanggal' => today()->format('Y-m-d'),
            'sesi' => 'pagi',
            'kondisi' => 'normal',
            'keterangan' => $xssPayload,
            'foto' => $photo,
        ];

        $this->actingAs($this->petugas1)
            ->post(route('monitoring.store'), $data);

        $monitoring = WaterMonitoring::first();
        
        $response = $this->actingAs($this->petugas1)
            ->get(route('monitoring.show', $monitoring));

        $response->assertStatus(200);
        // Verify the escaped version is shown, not the raw script tag
        $response->assertSee('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("XSS")</script>', false);
    }

    /** @test */
    public function guest_cannot_access_monitoring_routes()
    {
        $monitoring = WaterMonitoring::factory()->create();

        $routes = [
            ['get', route('monitoring.index')],
            ['get', route('monitoring.create')],
            ['post', route('monitoring.store')],
            ['get', route('monitoring.edit', $monitoring)],
            ['put', route('monitoring.update', $monitoring)],
            ['delete', route('monitoring.destroy', $monitoring)],
            ['get', route('monitoring.show', $monitoring)],
            ['get', route('foto.monitoring', $monitoring)],
        ];

        foreach ($routes as [$method, $url]) {
            $response = $this->$method($url);
            $response->assertRedirect(route('login'));
        }
    }

    /** @test */
    public function monitoring_records_are_sorted_by_date_and_time_desc()
    {
        $old = WaterMonitoring::factory()->create([
            'tanggal' => today()->subDays(2),
            'sesi' => 'pagi',
            'keterangan' => 'Old record',
        ]);

        $recent = WaterMonitoring::factory()->create([
            'tanggal' => today(),
            'sesi' => 'sore',
            'keterangan' => 'Recent record',
        ]);

        $middle = WaterMonitoring::factory()->create([
            'tanggal' => today()->subDay(),
            'sesi' => 'siang',
            'keterangan' => 'Middle record',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('monitoring.index'));

        $monitorings = $response->viewData('monitorings');
        
        $this->assertEquals($recent->id, $monitorings[0]->id);
        $this->assertEquals($middle->id, $monitorings[1]->id);
        $this->assertEquals($old->id, $monitorings[2]->id);
    }
}
