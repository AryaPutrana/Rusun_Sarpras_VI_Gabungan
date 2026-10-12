<?php

namespace App\Http\Controllers;

use App\Models\FinanceReport;
use App\Models\Item;
use App\Models\MonitoringLocation;
use App\Models\WaterMonitoring;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Rentang tanggal yang tersedia sebagai preset cepat.
     *
     * @var array<string, string>
     */
    protected const DATE_RANGE_PRESETS = [
        'hari_ini' => 'Hari Ini',
        '7hari' => '7 Hari Terakhir',
        'bulan_ini' => 'Bulan Ini',
    ];

    /**
     * Tampilkan dashboard monitoring.
     *
     * Admin melihat seluruh data, petugas hanya melihat data miliknya.
     * Statistik (kartu & chart) mengikuti rentang tanggal yang dipilih,
     * sedangkan status terakhir per lokasi dan riwayat terbaru tetap
     * menampilkan data terbaru.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $today = now()->toDateString();

        [$dari, $sampai] = $this->resolveDateRange($request, $today);

        $base = WaterMonitoring::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));

        $monitorings = (clone $base)
            ->with(['user', 'location'])
            ->latest('tanggal')
            ->latest('waktu')
            ->latest('id');

        $rangeBase = (clone $base)->whereBetween('tanggal', [$dari, $sampai]);

        $total = (clone $base)->count();
        $rangeCount = (clone $rangeBase)->count();

        $rangeCounts = (clone $rangeBase)
            ->selectRaw('kondisi, count(*) as jumlah')
            ->groupBy('kondisi')
            ->pluck('jumlah', 'kondisi')
            ->toArray();

        $latestSub = (clone $base)
            ->select('water_monitorings.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY location_id ORDER BY tanggal DESC, waktu DESC) AS rn');

        $latestByLocation = WaterMonitoring::query()
            ->fromSub($latestSub->toBase(), 'latest')
            ->where('rn', 1)
            ->with(['user', 'location'])
            ->get()
            ->keyBy('location_id');

        $warningLocations = (clone $base)
            ->where('kondisi', '!=', 'normal')
            ->with(['user', 'location'])
            ->orderBy('tanggal')
            ->orderBy('waktu')
            ->orderBy('id')
            ->get();

        $activLocationsCount = MonitoringLocation::where('status', 'aktif')->count();
        $locations = MonitoringLocation::where('status', 'aktif')
            ->orderBy('nama_lokasi')
            ->get();

        $chartLabels = array_values(WaterMonitoring::KONDISI);
        $chartData = [];
        foreach (WaterMonitoring::KONDISI as $key => $label) {
            $chartData[] = $rangeCounts[$key] ?? 0;
        }

        $daysInRange = (int) Carbon::parse($sampai)->startOfDay()
            ->diffInDays(Carbon::parse($dari)->startOfDay()) + 1;

        $possibleSessions = $activLocationsCount * $daysInRange * count(WaterMonitoring::SESI);
        $sesiCoverage = $possibleSessions > 0
            ? min(100, (int) round($rangeCount / $possibleSessions * 100))
            : 0;

        $normalPercent = $rangeCount > 0
            ? (int) round(($rangeCounts['normal'] ?? 0) / $rangeCount * 100)
            : 0;

        $kondisiPerLokasi = (clone $rangeBase)
            ->selectRaw('location_id, kondisi, count(*) as jumlah')
            ->groupBy('location_id', 'kondisi')
            ->get()
            ->groupBy('location_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'kondisi'));

        $stackLabels = [];
        $stackNormal = [];
        $stackDebit = [];
        $stackTekanan = [];

        foreach ($locations as $location) {
            $set = $kondisiPerLokasi->get($location->id, collect());

            $stackLabels[] = $location->nama_lokasi;
            $stackNormal[] = $set->get('normal', 0);
            $stackDebit[] = $set->get('debit_turun', 0);
            $stackTekanan[] = $set->get('tekanan_air_kecil', 0);
        }

        $sesiPerLokasi = (clone $rangeBase)
            ->selectRaw('location_id, sesi, count(*) as jumlah')
            ->groupBy('location_id', 'sesi')
            ->get()
            ->groupBy('location_id')
            ->map(fn ($rows) => $rows->pluck('jumlah', 'sesi'));

        $financeData = $this->financeData($request);
        $inventoryData = $this->inventoryData();

        return view('dashboard', [
            'isAdmin' => $user->isAdmin(),
            'total' => $total,
            'rangeCount' => $rangeCount,
            'dateDari' => $dari,
            'dateSampai' => $sampai,
            'rangeLabel' => $this->rangeLabel($request, $dari, $sampai),
            'activeLocationsCount' => $activLocationsCount,
            'warningLocations' => $warningLocations,
            'latestByLocation' => $latestByLocation,
            'locations' => $locations,
            'rangeCounts' => $rangeCounts,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'recent' => (clone $monitorings)->limit(10)->get(),
            'daysInRange' => $daysInRange,
            'sesiCoverage' => $sesiCoverage,
            'normalPercent' => $normalPercent,
            'stackLabels' => $stackLabels,
            'stackNormal' => $stackNormal,
            'stackDebit' => $stackDebit,
            'stackTekanan' => $stackTekanan,
            'sesiPerLokasi' => $sesiPerLokasi,
            ... $financeData,
            ... $inventoryData,
        ]);
    }

    /**
     * Render hanya section Laporan Bulanan untuk AJAX.
     *
     * Dipakai form filter bulan pada dashboard agar pergantian bulan
     * berjalan tanpa me-refresh seluruh halaman.
     */
    public function financeSection(Request $request): View
    {
        return view('partials.finance_section', array_merge(
            $this->financeData($request),
            ['isAdmin' => $request->user()->isAdmin()]
        ));
    }

    /**
     * Data ringkasan laporan bulanan (input keuangan) pada bulan terpilih.
     *
     * Admin melihat semua petugas, petugas hanya data miliknya sendiri.
     * Dipakai oleh dashboard utama dan endpoint AJAX section laporan bulanan.
     *
     * @return array<string, mixed>
     */
    protected function financeData(Request $request): array
    {
        $user = $request->user();

        $financeBulan = $this->resolveBulan($request);
        $financeDari = Carbon::createFromFormat('Y-m', $financeBulan)->startOfMonth();
        $financeSampai = Carbon::createFromFormat('Y-m', $financeBulan)->endOfMonth();

        $financeBase = FinanceReport::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->id));

        $financeTotal = (clone $financeBase)
            ->whereBetween('tanggal', [$financeDari, $financeSampai])
            ->count();

        $financeCounts = [];

        foreach (array_keys(FinanceReport::KONDISI) as $kondisi) {
            $financeCounts[$kondisi] = (clone $financeBase)
                ->whereBetween('tanggal', [$financeDari, $financeSampai])
                ->where('kondisi', $kondisi)
                ->count();
        }

        $financeRecent = (clone $financeBase)
            ->whereBetween('tanggal', [$financeDari, $financeSampai])
            ->with(['user', 'location'])
            ->latest('tanggal')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'financeBulan' => $financeBulan,
            'financeBulanLabel' => $financeDari->locale('id')->translatedFormat('F Y'),
            'financeKondisiLabels' => FinanceReport::KONDISI,
            'financeTotal' => $financeTotal,
            'financeCounts' => $financeCounts,
            'financeRecent' => $financeRecent,
        ];
    }

    /**
     * Tentukan rentang [tanggal awal, tanggal akhir] berdasarkan request.
     *
     * Prioritas: preset -> input dari/sampai manual -> hari ini.
     * Tanggal akhir dibatasi tidak melebihi hari ini.
     *
     * @return array{0: string, 1: string}
     */
    protected function resolveDateRange(Request $request, string $today): array
    {
        $preset = $request->string('preset')->toString();

        if (array_key_exists($preset, self::DATE_RANGE_PRESETS)) {
            return match ($preset) {
                '7hari' => [now()->subDays(6)->toDateString(), $today],
                'bulan_ini' => [Carbon::now()->startOfMonth()->toDateString(), $today],
                default => [$today, $today],
            };
        }

        $dari = $this->parseDate($request->string('dari')->toString());
        $sampai = $this->parseDate($request->string('sampai')->toString());

        if ($dari && $sampai && $dari <= $sampai) {
            $sampai = min($sampai, $today);
            $dari = min($dari, $sampai);

            if ($dari <= $sampai) {
                return [$dari, $sampai];
            }
        }

        return [$today, $today];
    }

    /**
     * Label tampilan untuk rentang tanggal yang sedang aktif.
     */
    protected function rangeLabel(Request $request, string $dari, string $sampai): string
    {
        $preset = $request->string('preset')->toString();

        if (array_key_exists($preset, self::DATE_RANGE_PRESETS)) {
            return self::DATE_RANGE_PRESETS[$preset];
        }

        $format = fn (string $date) => \Carbon\Carbon::parse($date)->format('d/m/Y');

        return $dari === $sampai ? $format($dari) : $format($dari).' – '.$format($sampai);
    }

    /**
     * Validasi tanggal format Y-m-d dan kembalikan string normal, atau null bila tidak valid.
     */
    protected function parseDate(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', [$matches[1], $matches[2], $matches[3]]);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Tentukan bulan (format Y-m) untuk section Laporan Bulanan di dashboard.
     *
     * Prioritas: parameter bulan -> bulan berjalan.
     */
    protected function resolveBulan(Request $request): string
    {
        $bulan = $request->string('bulan')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return $bulan;
        }

        return now()->format('Y-m');
    }

    /**
     * Data ringkasan inventory material untuk dashboard.
     *
     * @return array<string, mixed>
     */
    protected function inventoryData(): array
    {
        $totalItems = Item::count();
        $totalWithdrawals = Withdrawal::count();
        
        $withdrawalsThisMonth = Withdrawal::whereMonth('taken_at', Carbon::now()->month)
            ->whereYear('taken_at', Carbon::now()->year)
            ->count();
        
        $totalValue = Withdrawal::sum('total_value');
        
        $recentWithdrawals = Withdrawal::with(['rusun', 'items.item'])
            ->withCount('items')
            ->orderBy('taken_at', 'desc')
            ->limit(5)
            ->get();
        
        // Low stock items (stock <= min_stock)
        // Migration add_min_stock_to_items_table already run successfully
        $lowStockItems = Item::whereColumn('stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->limit(5)
            ->get();

        return [
            'totalItems' => $totalItems,
            'totalWithdrawals' => $totalWithdrawals,
            'withdrawalsThisMonth' => $withdrawalsThisMonth,
            'totalValue' => $totalValue,
            'recentWithdrawals' => $recentWithdrawals,
            'lowStockItems' => $lowStockItems,
        ];
    }
}
