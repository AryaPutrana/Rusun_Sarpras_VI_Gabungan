@extends('layouts.app')

@section('title', 'Tambah Barang - SI Inventory Sarpras')
@section('page-title', 'Tambah Barang')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-plus-circle"></i> Form Tambah Barang
            </div>
            <div class="card-body">
                <form action="{{ route('inventory.items.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="item_code" class="form-label">ID Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('item_code') is-invalid @enderror" 
                               id="item_code" name="item_code" value="{{ old('item_code') }}" 
                               placeholder="Contoh: BRG-0001" required>
                        @error('item_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name') }}" 
                               placeholder="Contoh: Cat Tembok 5 Kg" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="photo" class="form-label">Foto Barang <span class="text-danger">*</span></label>
                        <input type="file" class="form-control @error('photo') is-invalid @enderror" 
                               id="photo" name="photo" accept="image/jpeg,image/jpg,image/png,image/webp" required>
                        <small class="text-muted">Format: JPG, JPEG, PNG, WEBP (Max: 5MB)</small>
                        @error('photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="unit_price" class="form-label">Harga Satuan <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control @error('unit_price') is-invalid @enderror" 
                                   id="unit_price" name="unit_price" value="{{ old('unit_price') }}" 
                                   placeholder="125000" min="0" step="0.01" required>
                        </div>
                        @error('unit_price')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="stock" class="form-label">Jumlah / Stok <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('stock') is-invalid @enderror" 
                               id="stock" name="stock" value="{{ old('stock') }}" 
                               placeholder="20" min="0" required>
                        @error('stock')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="min_stock" class="form-label">Minimum Stok</label>
                        <input type="number" class="form-control @error('min_stock') is-invalid @enderror" 
                               id="min_stock" name="min_stock" value="{{ old('min_stock', 0) }}" 
                               placeholder="0" min="0">
                        <small class="text-muted">Sistem akan memberikan peringatan jika stok <= minimum stok ini</small>
                        @error('min_stock')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="unit" class="form-label">Satuan <span class="text-danger">*</span></label>
                        <select class="form-select @error('unit') is-invalid @enderror" id="unit" name="unit" required>
                            <option value="">-- Pilih Satuan --</option>
                            <option value="pcs" {{ old('unit') == 'pcs' ? 'selected' : '' }}>pcs</option>
                            <option value="unit" {{ old('unit') == 'unit' ? 'selected' : '' }}>unit</option>
                            <option value="box" {{ old('unit') == 'box' ? 'selected' : '' }}>box</option>
                            <option value="botol" {{ old('unit') == 'botol' ? 'selected' : '' }}>botol</option>
                            <option value="kaleng" {{ old('unit') == 'kaleng' ? 'selected' : '' }}>kaleng</option>
                            <option value="meter" {{ old('unit') == 'meter' ? 'selected' : '' }}>meter</option>
                            <option value="liter" {{ old('unit') == 'liter' ? 'selected' : '' }}>liter</option>
                            <option value="kg" {{ old('unit') == 'kg' ? 'selected' : '' }}>kg</option>
                            <option value="roll" {{ old('unit') == 'roll' ? 'selected' : '' }}>roll</option>
                        </select>
                        @error('unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Keterangan</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" name="description" rows="3" 
                                  placeholder="Keterangan tambahan (opsional)">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('inventory.items.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
