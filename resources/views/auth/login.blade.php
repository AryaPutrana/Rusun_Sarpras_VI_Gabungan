@extends('layouts.app')

@section('title', 'Login')

@section('bodyClass', 'login-page')

@push('styles')
<style>
    body.login-page {
        min-height: 100vh;
        background: linear-gradient(180deg, #1e6fd9 0%, #0d6efd 35%, #0a58ca 70%, #084298 100%);
    }

    body.login-page main {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    body.login-page main > .container {
        max-width: none !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .login-page .login-wrap {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 3rem 1rem;
        overflow: hidden;
    }

    .login-page .login-card {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 420px;
        border: 0;
        border-radius: 1rem;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
    }

    .login-page .wave-bottom {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        line-height: 0;
    }

    .login-page .wave-bottom svg {
        display: block;
        width: 100%;
        height: 130px;
    }

    .login-page .login-logos {
        position: absolute;
        top: 1.5rem;
        right: 0;
        left: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0 1.5rem;
    }

    .login-page .login-logo-group {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .login-page .login-logo {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        line-height: 0;
        background-color: #fff;
        border-radius: 6px;
    }

    .login-page .login-logo img {
        display: block;
        width: auto;
        height: 36px;
    }

    @media (max-width: 575.98px) {
        .login-page .login-logos {
            gap: 0.5rem;
        }

        .login-page .login-logo-group {
            gap: 0.4rem;
        }

        .login-page .login-logo {
            padding: 4px;
        }

        .login-page .login-logo img {
            height: 28px;
        }
    }

    @media (max-height: 700px) and (min-width: 576px) {
        .login-page .login-logos {
            top: 1rem;
        }

        .login-page .login-logo {
            padding: 4px;
        }

        .login-page .login-logo img {
            height: 30px;
        }
    }
</style>
@endpush

@section('content')
<div class="login-wrap">
    <div class="login-logos">
        <div class="login-logo-group">
            <span class="login-logo">
                <img src="{{ asset('images/logo/jaya-raya.png') }}" width="142" height="160"
                     alt="Logo Jaya Raya" decoding="async">
            </span>
            <span class="login-logo">
                <img src="{{ asset('images/logo/dprkp.png') }}" width="160" height="160"
                     alt="Logo DPRKP" decoding="async">
            </span>
        </div>
        <span class="login-logo">
            <img src="{{ asset('images/logo/uprs-vi.png') }}" width="649" height="160"
                 alt="Logo UPRS VI" decoding="async">
        </span>
    </div>

    <div class="card login-card mb-3">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle mb-3"
                      style="width: 56px; height: 56px;">
                    <i class="bi bi-droplet-fill fs-3"></i>
                </span>
                <h5 class="card-title fw-bold mb-1">Sistem Sarana dan Prasarana</h5>
                <p class="text-muted small mb-0">Monitoring Kondisi Air GWT Harian &amp; Bulanan</p>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" id="email" name="email"
                               value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               autofocus autocomplete="username" placeholder="email" required>
                    </div>
                    @error('email')
                        <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" id="password" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               autocomplete="current-password" placeholder="Masukkan password" required>
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                                title="Lihat/sembunyikan password" aria-label="Lihat/sembunyikan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
                    <label for="remember" class="form-check-label">Ingat saya</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Login
                </button>

                <p class="text-center text-muted small mb-0 mt-3">&copy; 2026 Unit Pengelola Rumah Susun VI</p>
            </form>
        </div>
    </div>

    <div class="wave-bottom" aria-hidden="true">
        <svg viewBox="0 0 1440 130" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path fill="#ffffff" opacity="0.25" d="M0,64L60,74.7C120,85,240,107,360,106.7C480,107,600,85,720,74.7C840,64,960,64,1080,69.3C1200,75,1320,85,1380,90.7L1440,96L1440,130L0,130Z"></path>
            <path fill="#ffffff" d="M0,96L60,90.7C120,85,240,75,360,74.7C480,75,600,85,720,96C840,107,960,117,1080,106.7C1200,96,1320,85,1380,80L1440,75L1440,130L0,130Z"></path>
        </svg>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        if (! toggle || ! password) return;

        toggle.addEventListener('click', function () {
            const isHidden = password.type === 'password';
            password.type = isHidden ? 'text' : 'password';
            const icon = toggle.querySelector('i');
            icon.classList.toggle('bi-eye', isHidden);
            icon.classList.toggle('bi-eye-slash', ! isHidden);
        });
    });
</script>
@endpush