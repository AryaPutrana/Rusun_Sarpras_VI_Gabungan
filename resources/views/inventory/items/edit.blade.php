@extends('layouts.app')

@section('title', 'Edit Barang - SI Inventory Sarpras')
@section('page-title', 'Edit Barang')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-pencil"></i> Form Edit Barang
            </div>
            <div class="card-body">
                <form action="{{ route('inventory.items.update', $item->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="item_code" class="form-label">ID Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('item_code') is-invalid @enderror" 
                               id="item_code" name="item_code" value="{{ old('item_code', $item->item_code) }}" 
                               placeholder="Contoh: BRG-0001" required>
                        @error('item_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                               id="name" name="name" value="{{ old('name', $item->name) }}" 
                               placeholder="Contoh: Cat Tembok 5 Kg" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="photo" class="form-label">Foto Barang</label>
                        <div class="mb-2">
                            <img src="{{ $item->photoUrl() }}" alt="{{ $item->name }}" class="img-thumbnail" style="max-width: 200px;">
                            <p class="text-muted small">
                                Foto saat ini
                                @unless($item->hasPhoto())
                                    <span class="text-danger">(foto tidak ditemukan, menampilkan placeholder)</span>
                                @endunless
                            </p>
                        </div>
                        <input type="file" class="form-control @error('photo') is-invalid @enderror" 
                               id="photo" name="photo" accept="image/jpeg,image/jpg,image/png,image/webp">
                        <small class="text-muted">Format: JPG, JPEG, PNG, WEBP (Max: 5MB). Kosongkan jika tidak ingin mengubah foto.</small>
                        @error('photo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="unit_price" class="form-label">Harga Satuan <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control @error('unit_price') is-invalid @enderror" 
                                   id="unit_price" name="unit_price" value="{{ old('unit_price', $item->unit_price) }}" 
                                   placeholder="Harga per unit" min="0" step="0.01" required>
                        </div>
                        @error('unit_price')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="stock_display" class="form-label">Jumlah / Stok Saat Ini</label>
                        <input type="number" class="form-control bg-light" 
                               id="stock_display" value="{{ $item->stock }}" readonly>
                        <small class="text-muted">Stok saat ini: <strong>{{ $item->stock }}</strong> {{ $item->unit }}</small>
                    </div>

                    <div class="mb-3">
                        <label for="add_stock" class="form-label">
                            <i class="bi bi-plus-circle"></i> Tambah Stok
                        </label>
                        <input type="number" class="form-control @error('add_stock') is-invalid @enderror" 
                               id="add_stock" name="add_stock" value="{{ old('add_stock', 0) }}" 
                               placeholder="Jumlah yang ingin ditambahkan" min="0">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Kosongkan atau isi 0 jika tidak menambah stok. 
                            Contoh: isi 10 untuk menambah 10 unit ({{ $item->stock }} + 10 = {{ $item->stock + 10 }})
                        </small>
                        @error('add_stock')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="subtract_stock" class="form-label">
                            <i class="bi bi-dash-circle text-danger"></i> Kurangi Stok (Koreksi)
                        </label>
                        <input type="number" class="form-control @error('subtract_stock') is-invalid @enderror" 
                               id="subtract_stock" name="subtract_stock" value="{{ old('subtract_stock', 0) }}" 
                               placeholder="Jumlah yang ingin dikurangi" min="0" max="{{ $item->stock }}"
                               onchange="toggleSubtractReason()">
                        <small class="text-muted text-danger">
                            <i class="bi bi-exclamation-triangle"></i> Hati-hati! Untuk koreksi stok yang salah input. 
                            Stok saat ini: <strong>{{ $item->stock }}</strong>. Maksimal kurangi: {{ $item->stock }} unit.
                        </small>
                        @error('subtract_stock')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="subtract_reason_wrapper" style="display: none;">
                        <label for="subtract_reason" class="form-label text-danger">
                            <i class="bi bi-pencil-square"></i> Alasan Pengurangan Stok <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control @error('subtract_reason') is-invalid @enderror" 
                                  id="subtract_reason" name="subtract_reason" 
                                  rows="3" 
                                  placeholder="Contoh: Salah input pengambilan, seharusnya 10 unit tapi tercatat 100 unit"
                                  maxlength="255">{{ old('subtract_reason') }}</textarea>
                        <small class="text-muted">
                            Wajib diisi untuk audit trail. Maksimal 255 karakter.
                        </small>
                        @error('subtract_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <script>
                        function toggleSubtractReason() {
                            const subtractInput = document.getElementById('subtract_stock');
                            const reasonWrapper = document.getElementById('subtract_reason_wrapper');
                            const reasonTextarea = document.getElementById('subtract_reason');
                            
                            if (subtractInput.value > 0) {
                                reasonWrapper.style.display = 'block';
                                reasonTextarea.required = true;
                            } else {
                                reasonWrapper.style.display = 'none';
                                reasonTextarea.required = false;
                            }
                        }
                        
                        // Check on page load
                        document.addEventListener('DOMContentLoaded', toggleSubtractReason);
                    </script>

                    <div class="mb-3">
                        <label for="min_stock" class="form-label">Minimum Stok</label>
                        <input type="number" class="form-control @error('min_stock') is-invalid @enderror" 
                               id="min_stock" name="min_stock" value="{{ old('min_stock', $item->min_stock) }}" 
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
                            <option value="pcs" {{ old('unit', $item->unit) == 'pcs' ? 'selected' : '' }}>pcs</option>
                            <option value="unit" {{ old('unit', $item->unit) == 'unit' ? 'selected' : '' }}>unit</option>
                            <option value="box" {{ old('unit', $item->unit) == 'box' ? 'selected' : '' }}>box</option>
                            <option value="botol" {{ old('unit', $item->unit) == 'botol' ? 'selected' : '' }}>botol</option>
                            <option value="kaleng" {{ old('unit', $item->unit) == 'kaleng' ? 'selected' : '' }}>kaleng</option>
                            <option value="meter" {{ old('unit', $item->unit) == 'meter' ? 'selected' : '' }}>meter</option>
                            <option value="liter" {{ old('unit', $item->unit) == 'liter' ? 'selected' : '' }}>liter</option>
                            <option value="kg" {{ old('unit', $item->unit) == 'kg' ? 'selected' : '' }}>kg</option>
                            <option value="roll" {{ old('unit', $item->unit) == 'roll' ? 'selected' : '' }}>roll</option>
                        </select>
                        @error('unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Keterangan</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  id="description" name="description" rows="3" 
                                  placeholder="Keterangan tambahan (opsional)">{{ old('description', $item->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('inventory.items.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
