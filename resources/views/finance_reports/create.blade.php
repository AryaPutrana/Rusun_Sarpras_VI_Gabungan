@extends('layouts.app')

@section('title', 'Input Keuangan')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0 fw-bold">Input Bulanan Air</h4>
            <a href="{{ route('dashboard') }}" class="text-decoration-none">&larr; Kembali</a>
        </div>
        

        <div class="alert alert-info" role="alert">
            Catat kondisi air untuk laporan keuangan bulanan.
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('keuangan.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="tanggal" class="form-label">Tanggal Input <span class="text-danger">*</span></label>
                        <input type="date" id="tanggal" name="tanggal"
                               value="{{ old('tanggal', now()->format('Y-m-d')) }}"
                               max="{{ now()->format('Y-m-d') }}"
                               class="form-control @error('tanggal') is-invalid @enderror" required autofocus>
                        @error('tanggal')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="location_id" class="form-label">Lokasi <span class="text-danger">*</span></label>
                        <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                            <option value="">-- Pilih Lokasi --</option>
                            @foreach ($locationOptions as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->nama_lokasi }}</option>
                            @endforeach
                        </select>
                        @error('location_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="kondisi" class="form-label">Keterangan Dropdown <span class="text-danger">*</span></label>
                        <select id="kondisi" name="kondisi" class="form-select @error('kondisi') is-invalid @enderror" required>
                            <option value="">-- Pilih Kondisi --</option>
                            @foreach ($kondisiOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('kondisi') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('kondisi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                        <textarea id="keterangan" name="keterangan" rows="3"
                                  class="form-control @error('keterangan') is-invalid @enderror"
                                  required>{{ old('keterangan') }}</textarea>
                        @error('keterangan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="foto" class="form-label">Foto <span class="text-danger">*</span></label>
                        <input type="file" id="foto" name="foto" accept="image/jpeg,image/png" capture="environment"
                               class="form-control @error('foto') is-invalid @enderror" required>
                        <div class="form-text">Format: JPG, JPEG, atau PNG. Maksimal 5 MB.</div>
                        @error('foto')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="alert alert-light border mb-3">
                        <strong>Petugas:</strong> {{ Auth::user()->name }}
                        
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection