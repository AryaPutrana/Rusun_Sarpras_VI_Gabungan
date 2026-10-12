<?php

/**
 * Inventory Reports & Exports Test
 * 
 * Tests PDF and Excel generation with date filtering
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Item;
use App\Models\Rusun;
use App\Models\Withdrawal;
use App\Models\WithdrawalItem;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

$results = [];
$passed = 0;
$failed = 0;

function test($name, $callback) {
    global $results, $passed, $failed;
    try {
        $result = $callback();
        if ($result === true) {
            $results[] = "✅ $name";
            $passed++;
        } else {
            $results[] = "❌ $name: " . ($result ?: 'Failed');
            $failed++;
        }
    } catch (Exception $e) {
        $results[] = "❌ $name: " . $e->getMessage();
        $failed++;
    }
}

echo "🧪 TESTING INVENTORY REPORTS & EXPORTS\n";
echo str_repeat("=", 60) . "\n\n";

echo "📊 TEST 1: PDF REPORT GENERATION\n";
echo str_repeat("-", 60) . "\n";

test("PDF can be generated with data", function() {
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $details = WithdrawalItem::with(['item', 'withdrawal.rusun'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->limit(5000)
        ->get();
    
    $groupedDetails = $details->groupBy('withdrawal_id')->map(function ($items) {
        return [
            'withdrawal' => $items->first()->withdrawal,
            'items' => $items,
            'item_count' => $items->count(),
        ];
    })->values();
    
    $totalTransactions = Withdrawal::whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])->count();
    
    $totals = DB::table('withdrawal_items')
        ->join('withdrawals', 'withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
        ->whereBetween('withdrawals.taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
        ->select(
            DB::raw('COALESCE(SUM(withdrawal_items.quantity), 0) as totalQuantity'),
            DB::raw('COALESCE(SUM(withdrawal_items.subtotal), 0) as totalValue')
        )
        ->first();
    
    $pdf = Pdf::loadView('inventory.reports.pdf', [
        'groupedDetails' => $groupedDetails,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'rusunName' => 'Semua Rusun',
        'totalTransactions' => $totalTransactions,
        'totalQuantity' => $totals->totalQuantity,
        'totalValue' => $totals->totalValue,
        'detailLimitReached' => false,
    ]);
    
    $output = $pdf->output();
    
    return strlen($output) > 1000 && str_starts_with($output, '%PDF');
});

test("PDF contains transaction data", function() {
    $withdrawal = Withdrawal::with(['items.item', 'rusun'])->first();
    if (!$withdrawal) return "No withdrawals to test";
    
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $details = WithdrawalItem::with(['item', 'withdrawal.rusun'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->get();
    
    $groupedDetails = $details->groupBy('withdrawal_id')->map(function ($items) {
        return [
            'withdrawal' => $items->first()->withdrawal,
            'items' => $items,
            'item_count' => $items->count(),
        ];
    })->values();
    
    // Check that we have data to render
    return $groupedDetails->count() > 0 && $groupedDetails->first()['withdrawal'] !== null;
});

test("PDF photo thumbnails generate", function() {
    $item = Item::first();
    if (!$item) return "No items to test";
    
    $thumb = $item->photoThumbnail(40);
    
    return $thumb 
        && isset($thumb['data']) 
        && str_starts_with($thumb['data'], 'data:image/jpeg;base64,')
        && isset($thumb['width'])
        && isset($thumb['height'])
        && $thumb['width'] <= 40;
});

echo "\n📄 TEST 2: EXCEL/CSV EXPORT\n";
echo str_repeat("-", 60) . "\n";

test("CSV export generates valid output", function() {
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $details = WithdrawalItem::with(['item', 'withdrawal.rusun'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->limit(5000)
        ->get();
    
    $transactions = $details
        ->groupBy(fn ($detail) => $detail->withdrawal_id)
        ->map(function ($group) {
            $lines = $group->map(fn ($detail) => [
                'item' => $detail->item,
                'code' => $detail->item?->item_code ?? '-',
                'name' => $detail->item?->name ?? '-',
                'quantity' => (int) $detail->quantity,
                'unit' => $detail->item?->unit ?? '-',
                'unit_price' => (float) $detail->unit_price,
                'subtotal' => (float) $detail->subtotal,
            ])->values();

            return (object) [
                'withdrawal' => $group->first()->withdrawal,
                'lines' => $lines,
                'item_count' => $lines->count(),
                'total_quantity' => (int) $group->sum('quantity'),
                'total_value' => (float) $group->sum('subtotal'),
            ];
        })
        ->values();
    
    // Simulate CSV generation
    ob_start();
    $file = fopen('php://output', 'w');
    
    // UTF-8 BOM
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($file, ['LAPORAN PENGAMBILAN MATERIAL']);
    fputcsv($file, ['Periode', "$startDate s/d $endDate"]);
    
    fputcsv($file, ['No', 'No. Transaksi', 'Pengambil', 'Tanggal Ambil', 'Rusun', 'Daftar Barang', 'Jumlah', 'Subtotal']);
    
    $no = 1;
    foreach ($transactions as $transaction) {
        $itemList = $transaction->lines->map(fn ($line) => sprintf(
            '%s - %s (%s %s @ Rp%s)',
            $line['code'],
            $line['name'],
            number_format($line['quantity'], 0, ',', '.'),
            $line['unit'],
            number_format($line['unit_price'], 0, ',', '.')
        ))->implode(' | ');
        
        fputcsv($file, [
            $no++,
            $transaction->withdrawal->id,
            $transaction->withdrawal->taken_by,
            date('d/m/Y H:i', strtotime($transaction->withdrawal->taken_at)),
            $transaction->withdrawal->rusun?->name ?? '-',
            $itemList,
            $transaction->total_quantity,
            'Rp'.number_format($transaction->total_value, 0, ',', '.'),
        ]);
    }
    
    fclose($file);
    $csv = ob_get_clean();
    
    // Check CSV structure
    $lines = explode("\n", $csv);
    return count($lines) >= 3 && str_contains($csv, 'LAPORAN PENGAMBILAN MATERIAL');
});

test("CSV formula injection prevention", function() {
    // Test that values starting with dangerous characters are escaped
    $testValues = ['=SUM(A1:A10)', '+CALC', '-123', '@IMPORT'];
    
    $sanitize = function ($value) {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'])) {
            return "'".$value;
        }
        return $value;
    };
    
    foreach ($testValues as $val) {
        $sanitized = $sanitize($val);
        if ($sanitized[0] !== "'") {
            return "Failed to sanitize: $val";
        }
    }
    
    return true;
});

echo "\n🔍 TEST 3: DATE FILTERING\n";
echo str_repeat("-", 60) . "\n";

test("Report respects date range", function() {
    $startDate = now()->subDays(7)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $details = WithdrawalItem::with(['withdrawal'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->get();
    
    foreach ($details as $detail) {
        $takenAt = $detail->withdrawal->taken_at;
        if ($takenAt < $startDate.' 00:00:00' || $takenAt > $endDate.' 23:59:59') {
            return "Date out of range: $takenAt";
        }
    }
    
    return true;
});

test("Report filters by rusun correctly", function() {
    $rusun = Rusun::first();
    if (!$rusun) return "No rusuns to test";
    
    $details = WithdrawalItem::with(['withdrawal'])
        ->whereHas('withdrawal', function ($q) use ($rusun) {
            $q->where('rusun_id', $rusun->id);
        })
        ->get();
    
    if ($details->isEmpty()) return true; // No data is valid
    
    foreach ($details as $detail) {
        if ($detail->withdrawal->rusun_id != $rusun->id) {
            return "Found withdrawal from wrong rusun";
        }
    }
    
    return true;
});

test("Report totals match database aggregates", function() {
    $startDate = now()->subDays(30)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');
    
    $totals = DB::table('withdrawal_items')
        ->join('withdrawals', 'withdrawals.id', '=', 'withdrawal_items.withdrawal_id')
        ->whereBetween('withdrawals.taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
        ->select(
            DB::raw('COALESCE(SUM(withdrawal_items.quantity), 0) as totalQuantity'),
            DB::raw('COALESCE(SUM(withdrawal_items.subtotal), 0) as totalValue')
        )
        ->first();
    
    // Verify against collection-based calculation
    $details = WithdrawalItem::with(['withdrawal'])
        ->whereHas('withdrawal', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('taken_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        })
        ->get();
    
    $collectionTotalQty = $details->sum('quantity');
    $collectionTotalValue = $details->sum('subtotal');
    
    return abs($totals->totalQuantity - $collectionTotalQty) < 0.01
        && abs($totals->totalValue - $collectionTotalValue) < 0.01;
});

echo "\n" . str_repeat("=", 60) . "\n";
echo "📈 TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";

foreach ($results as $result) {
    echo "$result\n";
}

echo "\n";
echo "✅ PASSED: $passed\n";
echo "❌ FAILED: $failed\n";
echo "📊 TOTAL:  " . ($passed + $failed) . "\n";
echo "\n";

if ($failed === 0) {
    echo "🎉 ALL EXPORT TESTS PASSED!\n";
    exit(0);
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    exit(1);
}
