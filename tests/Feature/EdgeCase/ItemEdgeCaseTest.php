<?php

namespace Tests\Feature\EdgeCase;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Edge Case Tests for Item Model
 * 
 * Covers:
 * - Photo handling edge cases
 * - Stock status boundary conditions
 * - Price edge cases
 * - Item code validation
 * - Unicode in item names
 * - Empty and null states
 */
class ItemEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('public');
    }

    /** @test */
    public function it_returns_default_photo_url_when_photo_is_null()
    {
        $item = Item::factory()->create(['photo' => null]);

        $this->assertFalse($item->hasPhoto());
        $this->assertEquals(asset(Item::DEFAULT_PHOTO_URL), $item->photoUrl());
        $this->assertNull($item->photoThumbnail());
    }

    /** @test */
    public function it_returns_default_photo_url_when_file_does_not_exist()
    {
        $item = Item::factory()->create(['photo' => 'nonexistent.jpg']);

        $this->assertFalse($item->hasPhoto());
        $this->assertEquals(asset(Item::DEFAULT_PHOTO_URL), $item->photoUrl());
    }

    /** @test */
    public function it_detects_empty_stock_status()
    {
        $item = Item::factory()->create(['stock' => 0, 'min_stock' => 10]);

        $this->assertEquals('empty', $item->getStockStatus());
    }

    /** @test */
    public function it_detects_low_stock_status()
    {
        $item = Item::factory()->create(['stock' => 5, 'min_stock' => 10]);

        $this->assertTrue($item->isLowStock());
        $this->assertEquals('low', $item->getStockStatus());
    }

    /** @test */
    public function it_detects_low_stock_at_exact_min_stock()
    {
        $item = Item::factory()->create(['stock' => 10, 'min_stock' => 10]);

        $this->assertTrue($item->isLowStock());
        $this->assertEquals('low', $item->getStockStatus());
    }

    /** @test */
    public function it_detects_good_stock_status()
    {
        $item = Item::factory()->create(['stock' => 100, 'min_stock' => 10]);

        $this->assertFalse($item->isLowStock());
        $this->assertEquals('good', $item->getStockStatus());
    }

    /** @test */
    public function it_detects_normal_stock_status()
    {
        $item = Item::factory()->create(['stock' => 15, 'min_stock' => 10]);

        $this->assertFalse($item->isLowStock());
        $this->assertEquals('normal', $item->getStockStatus());
    }

    /** @test */
    public function it_handles_zero_min_stock()
    {
        $item = Item::factory()->create(['stock' => 5, 'min_stock' => 0]);

        $this->assertFalse($item->isLowStock());
    }

    /** @test */
    public function it_handles_negative_min_stock()
    {
        $item = Item::factory()->create(['stock' => 5, 'min_stock' => -1]);

        // When min_stock is not positive, isLowStock should return false
        $this->assertFalse($item->isLowStock());
    }

    /** @test */
    public function it_accepts_zero_price()
    {
        $item = Item::factory()->create(['unit_price' => 0]);

        $this->assertEquals(0, $item->unit_price);
    }

    /** @test */
    public function it_accepts_very_small_price()
    {
        $item = Item::factory()->create(['unit_price' => 0.01]);

        $this->assertEquals(0.01, $item->unit_price);
    }

    /** @test */
    public function it_accepts_maximum_price()
    {
        $item = Item::factory()->create(['unit_price' => Item::MAX_UNIT_PRICE]);

        $this->assertEquals(Item::MAX_UNIT_PRICE, $item->unit_price);
    }

    /** @test */
    public function it_handles_empty_item_code()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => '',
            'name' => 'Test Item',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('item_code');
    }

    /** @test */
    public function it_handles_single_character_item_code()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'A',
            'name' => 'Test Item',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['item_code' => 'A']);
    }

    /** @test */
    public function it_handles_max_length_item_code()
    {
        $longCode = str_repeat('A', 255);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => $longCode,
            'name' => 'Test Item',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['item_code' => $longCode]);
    }

    /** @test */
    public function it_rejects_exceeding_max_length_item_code()
    {
        $tooLongCode = str_repeat('A', 256);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => $tooLongCode,
            'name' => 'Test Item',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('item_code');
    }

    /** @test */
    public function it_handles_unicode_emoji_in_item_name()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'EMOJI-001',
            'name' => '🎉 Party Supplies 🎊',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['name' => '🎉 Party Supplies 🎊']);
    }

    /** @test */
    public function it_handles_unicode_chinese_in_item_name()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'CN-001',
            'name' => '中文产品名称',
            'description' => '这是中文描述',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => '件',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'name' => '中文产品名称',
            'unit' => '件',
        ]);
    }

    /** @test */
    public function it_handles_unicode_arabic_in_item_name()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'AR-001',
            'name' => 'منتج عربي',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'قطعة',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['name' => 'منتج عربي']);
    }

    /** @test */
    public function it_handles_mixed_unicode_scripts()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'MIX-001',
            'name' => 'English 中文 العربية 日本語 🎉',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'name' => 'English 中文 العربية 日本語 🎉',
        ]);
    }

    /** @test */
    public function it_handles_empty_description()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'TEST-001',
            'name' => 'Test Item',
            'description' => null,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'item_code' => 'TEST-001',
            'description' => null,
        ]);
    }

    /** @test */
    public function it_handles_max_length_description()
    {
        $longDescription = str_repeat('A', 60000);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'TEST-002',
            'name' => 'Test Item',
            'description' => $longDescription,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'item_code' => 'TEST-002',
            'description' => $longDescription,
        ]);
    }

    /** @test */
    public function it_rejects_exceeding_max_length_description()
    {
        $tooLongDescription = str_repeat('A', 60001);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'TEST-003',
            'name' => 'Test Item',
            'description' => $tooLongDescription,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('description');
    }

    /** @test */
    public function it_handles_single_character_name()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'TEST-004',
            'name' => 'A',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['name' => 'A']);
    }

    /** @test */
    public function it_handles_max_length_name()
    {
        $longName = str_repeat('A', 255);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'TEST-005',
            'name' => $longName,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['name' => $longName]);
    }

    /** @test */
    public function it_handles_special_characters_in_name()
    {
        $specialName = "Item with special chars: !@#$%^&*()_+-=[]{}|;':\",./<>?";

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'SPEC-001',
            'name' => $specialName,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['name' => $specialName]);
    }

    /** @test */
    public function it_handles_whitespace_in_name()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'WS-001',
            'name' => '  Item with   multiple   spaces  ',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', [
            'item_code' => 'WS-001',
        ]);
    }

    /** @test */
    public function it_handles_newlines_in_description()
    {
        $descriptionWithNewlines = "Line 1\nLine 2\r\nLine 3\rLine 4";

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'NL-001',
            'name' => 'Test Item',
            'description' => $descriptionWithNewlines,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('items', ['item_code' => 'NL-001']);
    }

    /** @test */
    public function it_handles_html_in_name()
    {
        $htmlName = '<script>alert("XSS")</script> Item Name';

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'HTML-001',
            'name' => $htmlName,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        // Name should be stored as-is (escaping happens in view)
        $this->assertDatabaseHas('items', ['name' => $htmlName]);
    }

    /** @test */
    public function it_handles_sql_injection_attempt_in_name()
    {
        $sqlName = "'; DROP TABLE items; --";

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'SQL-001',
            'name' => $sqlName,
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect();
        // Laravel's parameter binding protects against SQL injection
        $this->assertDatabaseHas('items', ['name' => $sqlName]);
        
        // Table should still exist
        $this->assertDatabaseHas('items', ['item_code' => 'SQL-001']);
    }

    /** @test */
    public function it_handles_duplicate_item_code()
    {
        Item::factory()->create(['item_code' => 'DUP-001']);

        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'DUP-001',
            'name' => 'Duplicate Code Item',
            'unit_price' => 1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('item_code');
    }

    /** @test */
    public function it_handles_negative_stock()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'NEG-001',
            'name' => 'Negative Stock Item',
            'unit_price' => 1000,
            'stock' => -10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('stock');
    }

    /** @test */
    public function it_handles_negative_price()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'NEGP-001',
            'name' => 'Negative Price Item',
            'unit_price' => -1000,
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('unit_price');
    }

    /** @test */
    public function it_handles_float_stock_input()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'FLOAT-001',
            'name' => 'Float Stock Item',
            'unit_price' => 1000,
            'stock' => 10.5,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        // Stock should be cast to integer
        $response->assertRedirect();
        $item = Item::where('item_code', 'FLOAT-001')->first();
        $this->assertIsInt($item->stock);
    }

    /** @test */
    public function it_rejects_non_numeric_stock()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'STR-001',
            'name' => 'String Stock Item',
            'unit_price' => 1000,
            'stock' => 'abc',
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('stock');
    }

    /** @test */
    public function it_rejects_non_numeric_price()
    {
        $response = $this->actingAs($this->admin)->post(route('inventory.items.store'), [
            'item_code' => 'STRP-001',
            'name' => 'String Price Item',
            'unit_price' => 'abc',
            'stock' => 10,
            'min_stock' => 5,
            'unit' => 'pcs',
        ]);

        $response->assertSessionHasErrors('unit_price');
    }
}
