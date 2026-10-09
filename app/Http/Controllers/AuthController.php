<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Maksimal percobaan login gagal sebelum akun terkunci sementara.
     */
    protected const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Durasi penguncian sementara (dalam detik).
     */
    protected const LOGIN_DECAY_SECONDS = 60;

    /**
     * Tampilkan halaman login.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Proses autentikasi user.
     *
     * Hanya percobaan yang gagal yang dihitung oleh rate limiter,
     * sehingga percobaan yang benar tidak akan terkunci secara keliru.
     */
    public function login(Request $request): RedirectResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // SECURITY FIX: Validate role BEFORE authentication to prevent
        // invalid role users from receiving a session momentarily
        $user = \App\Models\User::where('email', $credentials['email'])->first();
        
        if ($user && ! in_array($user->role, ['admin', 'petugas'])) {
            $this->incrementLoginAttempts($request);
            
            return back()->withErrors([
                'email' => 'Akun Anda tidak memiliki akses ke sistem.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->clearLoginAttempts($request);

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $this->incrementLoginAttempts($request);

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    /**
     * Keluar dari aplikasi.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Kunci sementara apabila percobaan gagal telah melewati batas.
     */
    protected function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_LOGIN_ATTEMPTS);
    }

    /**
     * Catat satu percobaan login yang gagal.
     */
    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), self::LOGIN_DECAY_SECONDS);
    }

    /**
     * Hapus seluruh catatan percobaan gagal setelah login berhasil.
     */
    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }

    /**
     * Kunci unik untuk membedakan setiap pengguna + perangkat.
     */
    protected function throttleKey(Request $request): string
    {
        $email = $request->input('email');

        return Str::lower(is_string($email) ? $email : '').'|'.$request->ip();
    }

    /**
     * Tanggapan saat pengguna terkunci karena terlalu banyak percobaan gagal.
     *
     * Mengembalikan redirect ke form login dengan pesan yang jelas,
     * bukan halaman 429 mentah dari framework.
     */
    protected function sendLockoutResponse(Request $request): RedirectResponse
    {
        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        $waktu = $seconds >= 60 ? (ceil($seconds / 60).' menit') : ($seconds.' detik');

        return back()->withErrors([
            'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$waktu}.",
        ])->onlyInput('email');
    }
}