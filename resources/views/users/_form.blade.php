<div class="mb-3">
    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" id="name" name="name"
           value="{{ old('name', $pengguna->name ?? '') }}"
           class="form-control @error('name') is-invalid @enderror"
           required autofocus>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
    <input type="email" id="email" name="email"
           value="{{ old('email', $pengguna->email ?? '') }}"
           class="form-control @error('email') is-invalid @enderror"
           required autocomplete="username">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
    @isset($pengguna)
        @php
            $roleLabel = \App\Models\User::ROLES[$pengguna->role] ?? ucfirst($pengguna->role);
            $roleBadge = $pengguna->isAdmin() ? 'danger' : 'primary';
        @endphp
        <div>
            <span class="badge bg-{{ $roleBadge }}">{{ $roleLabel }}</span>
            <span class="text-muted small">(Role tidak dapat diubah setelah pengguna dibuat.)</span>
        </div>
    @else
        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
            <option value="">-- Pilih Role --</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endisset
</div>

<hr>

<div class="mb-3">
    <label for="password" class="form-label">
        Password
        @isset($pengguna)
            <span class="text-muted small">(kosongkan jika tidak diubah)</span>
        @else
            <span class="text-danger">*</span>
        @endisset
    </label>
    <input type="password" id="password" name="password"
           class="form-control @error('password') is-invalid @enderror"
           {!! isset($pengguna) ? '' : 'required' !!}
           autocomplete="new-password">
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">
        Konfirmasi Password
        <span class="text-danger">*</span>
    </label>
    <input type="password" id="password_confirmation" name="password_confirmation"
           class="form-control"
           {!! isset($pengguna) ? '' : 'required' !!}
           autocomplete="new-password">
</div>