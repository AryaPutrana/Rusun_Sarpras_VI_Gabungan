<div class="mb-3">
    <label for="nama_lokasi" class="form-label">Nama Lokasi <span class="text-danger">*</span></label>
    <input type="text" id="nama_lokasi" name="nama_lokasi"
           value="{{ old('nama_lokasi', $lokasi->nama_lokasi ?? '') }}"
           class="form-control @error('nama_lokasi') is-invalid @enderror"
           required autofocus>
    @error('nama_lokasi')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>


<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
        <option value="">-- Pilih Status --</option>
        @foreach ($statusOptions as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $lokasi->status ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="keterangan" class="form-label">Keterangan</label>
    <textarea id="keterangan" name="keterangan" rows="3"
              class="form-control @error('keterangan') is-invalid @enderror">{{ old('keterangan', $lokasi->keterangan ?? '') }}</textarea>
    @error('keterangan')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>