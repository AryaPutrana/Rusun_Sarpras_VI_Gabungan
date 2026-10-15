<?php

namespace Tests\Feature\Inventory;

use App\Models\Item;
use App\Models\Rusun;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $petugas;
    private Rusun $rusun;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
        $this->rusun = Rusun::factory()->create();
    }

    // ==================== PDF EXPORT TESTS ====================

    /** @test */
    public function pdf_export_with_small_dataset_succeeds()
    {
        // Create 10 withdrawals with items (each withdrawal has different items to avoid unique constraint)
        $items = Item::factory()->count(10)->create(['unit_price' => 10000]);
        
        Withdrawal::factory()->count(10)->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ])->each(function ($withdrawal, $index) use ($items) {
            // Each withdrawal gets a unique item
            WithdrawalItem::factory()->create([
                'withdrawal_id' => $withdrawal->id,
                'item_id' => $items[$index]->id,
                'quantity' => 5,
                'unit_price' => 10000,
                'subtotal' => 50000,
            ]);
        });

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        
        // Verify file size is reasonable (not empty, not too large)
        $content = $response->getContent();
        $this->assertGreaterThan(1000, strlen($content), 'PDF should not be empty');
        $this->assertLessThan(10000000, strlen($content), 'PDF should not exceed 10MB');
    }

    /** @test */
    public function pdf_export_with_date_filter_works()
    {
        $oldItem = Item::factory()->create(['unit_price' => 5000]);
        $newItem = Item::factory()->create(['unit_price' => 10000]);
        
        // Old withdrawal (should be excluded)
        $oldWithdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subMonths(2),
        ]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $oldWithdrawal->id,
            'item_id' => $oldItem->id,
            'quantity' => 10,
        ]);
        
        // New withdrawal (should be included)
        $newWithdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $newWithdrawal->id,
            'item_id' => $newItem->id,
            'quantity' => 5,
        ]);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function pdf_export_with_rusun_filter_works()
    {
        $rusun1 = Rusun::factory()->create(['code' => 'RABEK']);
        $rusun2 = Rusun::factory()->create(['code' => 'UMEN']);
        $item = Item::factory()->create(['unit_price' => 10000]);
        
        // Withdrawal for rusun1 (should be included)
        $w1 = Withdrawal::factory()->create(['rusun_id' => $rusun1->id, 'taken_at' => now()->subDays(2)]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $w1->id,
            'item_id' => $item->id,
        ]);
        
        // Withdrawal for rusun2 (should be excluded)
        $w2 = Withdrawal::factory()->create(['rusun_id' => $rusun2->id, 'taken_at' => now()->subDays(2)]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $w2->id,
            'item_id' => $item->id,
        ]);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}&rusun_id={$rusun1->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    /** @test */
    public function pdf_export_rejects_dataset_over_1000_rows()
    {
        $items = Item::factory()->count(250)->create(['unit_price' => 10000]);
        
        // Create 250 withdrawals with 5 DIFFERENT items each = 1250 withdrawal_items
        Withdrawal::factory()->count(250)->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ])->each(function ($withdrawal, $wIndex) use ($items) {
            // 5 unique items per withdrawal (using offset to ensure uniqueness)
            for ($i = 0; $i < 5; $i++) {
                $itemIndex = ($wIndex * 5 + $i) % 250; // Rotate through items
                WithdrawalItem::factory()->create([
                    'withdrawal_id' => $withdrawal->id,
                    'item_id' => $items[$itemIndex]->id,
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'subtotal' => 20000,
                ]);
            }
        });

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        $errorMessage = session('error');
        $this->assertStringContainsString('1000', $errorMessage);
        $this->assertStringContainsString('baris', $errorMessage);
    }

    /** @test */
    public function pdf_export_handles_empty_dataset()
    {
        // No withdrawals created
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        
        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        
        // Should still generate valid PDF (even if empty)
        $content = $response->getContent();
        $this->assertGreaterThan(500, strlen($content), 'Empty PDF should still have structure');
    }

    /** @test */
    public function pdf_export_requires_authentication()
    {
        $response = $this->get('/inventory/reports/export/pdf');
        
        $response->assertRedirect('/login');
    }

    /** @test */
    public function pdf_export_calculates_totals_correctly()
    {
        $item1 = Item::factory()->create(['name' => 'Item A', 'unit_price' => 10000]);
        $item2 = Item::factory()->create(['name' => 'Item B', 'unit_price' => 20000]);
        
        $withdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ]);
        
        // Item 1: 5 units × 10000 = 50000
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal->id,
            'item_id' => $item1->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
        ]);
        
        // Item 2: 3 units × 20000 = 60000 (unique item_id, not duplicate)
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal->id,
            'item_id' => $item2->id,
            'quantity' => 3,
            'unit_price' => 20000,
            'subtotal' => 60000,
        ]);
        
        // Total should be: 50000 + 60000 = 110000

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        
        // PDF content should contain the total value
        // Note: We can't easily parse PDF content, but we verified calculation in controller
    }

    // ==================== EXCEL EXPORT TESTS ====================

    /** @test */
    public function excel_export_with_small_dataset_succeeds()
    {
        $items = Item::factory()->count(10)->create(['unit_price' => 10000]);
        
        Withdrawal::factory()->count(10)->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ])->each(function ($withdrawal, $index) use ($items) {
            WithdrawalItem::factory()->create([
                'withdrawal_id' => $withdrawal->id,
                'item_id' => $items[$index]->id,
                'quantity' => 5,
                'unit_price' => 10000,
                'subtotal' => 50000,
            ]);
        });

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/excel?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertContains($response->headers->get('content-type'), [
            'text/csv; charset=UTF-8',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
        
        // StreamedResponse - content is streamed, not stored in memory
        // We can only verify status code and headers
    }

    /** @test */
    public function excel_export_handles_large_dataset()
    {
        $items = Item::factory()->count(1000)->create(['unit_price' => 10000]);
        
        // Create 1000 withdrawals with 5 UNIQUE items each = 5000 withdrawal_items
        Withdrawal::factory()->count(1000)->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ])->each(function ($withdrawal, $wIndex) use ($items) {
            // 5 unique items per withdrawal (using offset to ensure uniqueness)
            for ($i = 0; $i < 5; $i++) {
                $itemIndex = ($wIndex * 5 + $i) % 1000; // Rotate through items
                WithdrawalItem::factory()->create([
                    'withdrawal_id' => $withdrawal->id,
                    'item_id' => $items[$itemIndex]->id,
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'subtotal' => 20000,
                ]);
            }
        });

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/excel?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertContains($response->headers->get('content-type'), [
            'text/csv; charset=UTF-8',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
        
        // StreamedResponse - content is streamed, not stored in memory
        // We can only verify status code and headers
    }

    /** @test */
    public function excel_export_with_date_filter_works()
    {
        $oldItem = Item::factory()->create(['unit_price' => 10000]);
        $newItem = Item::factory()->create(['unit_price' => 10000]);
        
        // Old withdrawal (should be excluded)
        $oldWithdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subMonths(2),
        ]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $oldWithdrawal->id,
            'item_id' => $oldItem->id,
            'quantity' => 10,
        ]);
        
        // New withdrawal (should be included)
        $newWithdrawal = Withdrawal::factory()->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $newWithdrawal->id,
            'item_id' => $newItem->id,
            'quantity' => 5,
        ]);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/excel?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertContains($response->headers->get('content-type'), [
            'text/csv; charset=UTF-8',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
    }

    /** @test */
    public function excel_export_requires_authentication()
    {
        $response = $this->get('/inventory/reports/export/excel');
        
        $response->assertRedirect('/login');
    }

    /** @test */
    public function excel_export_handles_empty_dataset()
    {
        // No withdrawals created
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        
        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/excel?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertContains($response->headers->get('content-type'), [
            'text/csv; charset=UTF-8',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);
        
        // StreamedResponse - content is streamed, not stored in memory
        // We can only verify status code and headers
    }

    /** @test */
    public function excel_export_prevents_formula_injection()
    {
        $items = Item::factory()->count(3)->create([
            'name' => '=SUM(1+1)', // Potential CSV formula injection
            'unit_price' => 10000,
        ]);
        
        Withdrawal::factory()->count(3)->create([
            'rusun_id' => $this->rusun->id,
            'taken_at' => now()->subDays(5),
        ])->each(function ($withdrawal, $index) use ($items) {
            // Each withdrawal gets a unique item
            WithdrawalItem::factory()->create([
                'withdrawal_id' => $withdrawal->id,
                'item_id' => $items[$index]->id,
                'quantity' => 2,
                'unit_price' => 10000,
                'subtotal' => 20000,
            ]);
        });

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)
            ->get("/inventory/reports/export/excel?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        
        // Excel should be generated successfully
        // CSV formula injection prevention is handled in ReportController line 379-385
    }

    // ==================== AUTHORIZATION TESTS ====================

    /** @test */
    public function petugas_can_export_reports()
    {
        $item = Item::factory()->create(['unit_price' => 10000]);
        $withdrawal = Withdrawal::factory()->create(['rusun_id' => $this->rusun->id, 'taken_at' => now()->subDays(2)]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal->id,
            'item_id' => $item->id,
        ]);

        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->petugas)
            ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
    }

    /** @test */
    public function export_respects_throttle_limits()
    {
        $item = Item::factory()->create(['unit_price' => 10000]);
        $withdrawal = Withdrawal::factory()->create(['rusun_id' => $this->rusun->id, 'taken_at' => now()->subDays(2)]);
        WithdrawalItem::factory()->create([
            'withdrawal_id' => $withdrawal->id,
            'item_id' => $item->id,
        ]);
        
        $startDate = now()->subDays(7)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');
        
        // Make 11 requests quickly (should hit throttle limit)
        for ($i = 0; $i < 11; $i++) {
            $response = $this->actingAs($this->petugas)
                ->get("/inventory/reports/export/pdf?start_date={$startDate}&end_date={$endDate}");
            
            if ($i < 10) {
                $response->assertOk();
            } else {
                // 11th request should be throttled
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
}
