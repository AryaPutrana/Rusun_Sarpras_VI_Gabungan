<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Informasi Sarpras UPRS VI')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* Animasi halus dropdown navbar (Monitoring Harian & Laporan Bulanan).
           Fade dipasang pada .dropdown-menu, sedangkan slide dipasang pada
           .dropdown-item supaya kotak submenu itu sendiri tidak ikut bergeser.
           Di dalam navbar Bootstrap mematikan Popper (applyStyles: false) dan di
           mode HP .navbar-nav .dropdown-menu menjadi position:static, sehingga
           transform pada .dropdown-menu akan menggeser submenu yang sedang
           mengalir di dalam layout. */
        @keyframes navDropdownFade {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes navRevealIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .navbar .dropdown-menu.show {
            animation: navDropdownFade .28s cubic-bezier(.4, 0, .2, 1);
        }

        .navbar .dropdown-menu.show .dropdown-item {
            animation: navRevealIn .28s cubic-bezier(.4, 0, .2, 1) backwards;
        }

        /* Mode HP (< 992px). Semua aturan di bawah hanya berlaku di mobile. */
        @media (max-width: 991.98px) {
            /* Di HP Bootstrap membuat .dropdown-menu menjadi position:static sehingga
               submenu ikut mengalir di layout dan tinggi navbar melompat seketika.
               Dipaksa display:block + max-height:0 agar bisa ditransisi.
               visibility ikut transitioned supaya menu yang tertutup tidak bisa di-tab. */
            .navbar .navbar-nav .dropdown-menu {
                display: block;
                max-height: 0;
                overflow: hidden;
                visibility: hidden;
                margin-top: 0;
                padding-top: 0;
                padding-bottom: 0;
                transition: max-height .28s cubic-bezier(.4, 0, .2, 1),
                            padding .28s cubic-bezier(.4, 0, .2, 1),
                            visibility .28s;
            }

            .navbar .navbar-nav .dropdown-menu.show {
                max-height: 20rem;
                visibility: visible;
                padding-top: .5rem;
                padding-bottom: .5rem;
            }

            /* Kurva buka/tutup menu hamburger */
            .navbar .collapsing {
                transition: height .32s cubic-bezier(.4, 0, .2, 1);
            }

            /* Isi navbar ikut fade + slide saat menu dibuka */
            .navbar .navbar-collapse.show > .navbar-nav,
            .navbar .navbar-collapse.show > .d-flex {
                animation: navRevealIn .28s cubic-bezier(.4, 0, .2, 1) backwards;
            }

            /* Ikon hamburger berubah menjadi tanda silang saat menu terbuka */
            .navbar-toggler[aria-expanded="true"] .navbar-toggler-icon {
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='white' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M6 6l18 18M24 6L6 24'/%3e%3c/svg%3e");
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .navbar .dropdown-menu.show,
            .navbar .dropdown-menu.show .dropdown-item,
            .navbar .navbar-collapse.show > .navbar-nav,
            .navbar .navbar-collapse.show > .d-flex {
                animation: none;
            }
        }

        @media (prefers-reduced-motion: reduce) and (max-width: 991.98px) {
            .navbar .collapsing,
            .navbar .navbar-nav .dropdown-menu {
                transition: none;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="@yield('bodyClass', 'bg-light')">

@auth
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a href="{{ route('dashboard') }}" class="navbar-brand">Sistem Sarana dan Prasarana</a>
        <button class="navbar-toggler order-first" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}"
                       class="nav-link{{ request()->routeIs('dashboard') ? ' active' : '' }}">Dashboard</a>
                </li>
                @if (Auth::user()->isAdmin())
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Monitoring Harian</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('lokasi.index') }}">Lokasi Monitoring</a></li>
                            <li><a class="dropdown-item" href="{{ route('monitoring.index') }}">Laporan Monitoring</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Laporan Bulanan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('lokasiBulanan.index') }}">Lokasi Bulanan</a></li>
                            <li><a class="dropdown-item" href="{{ route('keuangan.index') }}">Laporan Bulanan</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Inventory Material</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('inventory.items.index') }}">Data Barang</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventory.withdrawals.index') }}">Pengambilan Barang</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventory.reports.index') }}">Laporan Pengambilan</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('pengguna.index') }}" class="nav-link">Kelola Pengguna</a>
                    </li>
                @else
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Monitoring Harian</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('monitoring.create') }}">Input Monitoring</a></li>
                            <li><a class="dropdown-item" href="{{ route('monitoring.index') }}">Histori Monitoring</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Laporan Bulanan</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('keuangan.create') }}">Input Bulanan</a></li>
                            <li><a class="dropdown-item" href="{{ route('keuangan.riwayat') }}">Riwayat Bulanan</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" role="button" aria-expanded="false">Inventory Material</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('inventory.items.index') }}">Data Barang</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventory.withdrawals.index') }}">Pengambilan Barang</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventory.reports.index') }}">Laporan Pengambilan</a></li>
                        </ul>
                    </li>
                @endif
            </ul>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white small">
                    {{ Auth::user()->name }} ({{ ucfirst(Auth::user()->role) }})
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</nav>
@endauth

<main class="py-4">
    <div class="container">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>
</main>

<!-- Modal global untuk melihat foto pemeriksaan (dipakai histori & detail) -->
<div class="modal fade" id="fotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Foto Pemeriksaan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <img id="fotoModalImg" src="" alt="Foto pemeriksaan" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var fotoModal = document.getElementById('fotoModal');
        var fotoImg = document.getElementById('fotoModalImg');
        if (! fotoModal || ! fotoImg) return;

        fotoModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            var src = btn && btn.getAttribute('data-foto-src');
            if (src) fotoImg.src = src;
        });

        fotoModal.addEventListener('hidden.bs.modal', function () {
            fotoImg.src = '';
        });
    });
</script>
@endpush

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
@stack('scripts')
</body>
</html>