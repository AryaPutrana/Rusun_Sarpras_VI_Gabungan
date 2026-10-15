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

                    <!-- Stock Management Section -->
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-box-seam"></i> Kelola Stok Barang
                        </div>
                        <div class="card-body">
                            <!-- Current Stock Display -->
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle-fill"></i> 
                                <strong>Stok Saat Ini: {{ $item->stock }} {{ $item->unit }}</strong>
                            </div>

                            <!-- Mode Selection -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Pilih Mode Update Stok:</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="stock_mode" id="mode_adjustment" value="adjustment" checked onchange="toggleStockMode()">
                                    <label class="btn btn-outline-primary" for="mode_adjustment">
                                        <i class="bi bi-plus-slash-minus"></i> Mode Adjustment (Tambah/Kurangi)
                                    </label>

                                    <input type="radio" class="btn-check" name="stock_mode" id="mode_direct" value="direct" onchange="toggleStockMode()">
                                    <label class="btn btn-outline-success" for="mode_direct">
                                        <i class="bi bi-pencil-square"></i> Mode Edit Langsung
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <strong>Adjustment:</strong> Tambah/kurangi dari stok saat ini. 
                                    <strong>Edit Langsung:</strong> Ganti dengan angka baru.
                                </small>
                            </div>

                            <!-- MODE 1: ADJUSTMENT (Default) -->
                            <div id="adjustment_mode_section">
                                <div class="mb-3">
                                    <label for="add_stock" class="form-label">
                                        <i class="bi bi-plus-circle text-success"></i> Tambah Stok
                                    </label>
                                    <input type="number" class="form-control @error('add_stock') is-invalid @enderror" 
                                           id="add_stock" name="add_stock" value="{{ old('add_stock') }}" 
                                           placeholder="Jumlah yang ingin ditambahkan" min="0">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> Contoh: isi 10 untuk menambah 10 unit 
                                        ({{ $item->stock }} + 10 = <strong>{{ $item->stock + 10 }}</strong>)
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
                                           id="subtract_stock" name="subtract_stock" value="{{ old('subtract_stock') }}" 
                                           placeholder="Jumlah yang ingin dikurangi" min="0" max="{{ $item->stock }}"
                                           onchange="toggleSubtractReason()" oninput="toggleSubtractReason()">
                                    <small class="text-muted text-danger">
                                        <i class="bi bi-exclamation-triangle"></i> Hati-hati! Maksimal kurangi: {{ $item->stock }} unit.
                                    </small>
                                    @error('subtract_stock')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-0" id="subtract_reason_wrapper" style="display: none;">
                                    <label for="subtract_reason" class="form-label text-danger">
                                        <i class="bi bi-pencil-square"></i> Alasan Pengurangan Stok <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control @error('subtract_reason') is-invalid @enderror" 
                                              id="subtract_reason" name="subtract_reason" 
                                              rows="2" 
                                              placeholder="Contoh: Koreksi stok karena salah input"
                                              maxlength="255">{{ old('subtract_reason') }}</textarea>
                                    <small class="text-muted">Wajib diisi untuk audit trail.</small>
                                    @error('subtract_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- MODE 2: DIRECT EDIT -->
                            <div id="direct_mode_section" style="display: none;">
                                <div class="mb-3">
                                    <label for="new_stock" class="form-label">
                                        <i class="bi bi-pencil-square text-success"></i> Stok Baru
                                    </label>
                                    <input type="number" class="form-control @error('new_stock') is-invalid @enderror" 
                                           id="new_stock" name="new_stock" value="{{ old('new_stock', $item->stock) }}" 
                                           placeholder="Masukkan jumlah stok baru" min="0">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> Stok saat ini: <strong>{{ $item->stock }}</strong>. 
                                        Isi dengan angka baru untuk mengganti stok.
                                    </small>
                                    @error('new_stock')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-0">
                                    <label for="stock_change_reason" class="form-label">
                                        <i class="bi bi-pencil-square"></i> Alasan Perubahan Stok <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control @error('stock_change_reason') is-invalid @enderror" 
                                              id="stock_change_reason" name="stock_change_reason" 
                                              rows="2" 
                                              placeholder="Contoh: Update stok setelah stock opname"
                                              maxlength="255">{{ old('stock_change_reason') }}</textarea>
                                    <small class="text-muted">Wajib diisi saat mengubah stok langsung.</small>
                                    @error('stock_change_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function toggleStockMode() {
                            const mode = document.querySelector('input[name="stock_mode"]:checked').value;
                            const adjustmentSection = document.getElementById('adjustment_mode_section');
                            const directSection = document.getElementById('direct_mode_section');
                            
                            if (mode === 'adjustment') {
                                adjustmentSection.style.display = 'block';
                                directSection.style.display = 'none';
                                
                                // Reset direct mode fields requirement
                                const stockChangeReasonInput = document.getElementById('stock_change_reason');
                                stockChangeReasonInput.required = false;
                            } else {
                                adjustmentSection.style.display = 'none';
                                directSection.style.display = 'block';
                                
                                // Reset adjustment mode fields requirement
                                const subtractReasonInput = document.getElementById('subtract_reason');
                                subtractReasonInput.required = false;
                                document.getElementById('subtract_reason_wrapper').style.display = 'none';
                                
                                // Set direct mode as required
                                document.getElementById('stock_change_reason').required = true;
                            }
                        }

                        function toggleSubtractReason() {
                            const subtractInput = document.getElementById('subtract_stock');
                            const reasonWrapper = document.getElementById('subtract_reason_wrapper');
                            const reasonTextarea = document.getElementById('subtract_reason');
                            
                            if (subtractInput.value && subtractInput.value > 0) {
                                reasonWrapper.style.display = 'block';
                                reasonTextarea.required = true;
                            } else {
                                reasonWrapper.style.display = 'none';
                                reasonTextarea.required = false;
                            }
                        }
                        
                        // Initialize on page load - hanya toggle visibility, JANGAN reset values
                        document.addEventListener('DOMContentLoaded', function() {
                            // Langsung set visibility tanpa clear values
                            const mode = document.querySelector('input[name="stock_mode"]:checked').value;
                            const adjustmentSection = document.getElementById('adjustment_mode_section');
                            const directSection = document.getElementById('direct_mode_section');
                            
                            if (mode === 'adjustment') {
                                adjustmentSection.style.display = 'block';
                                directSection.style.display = 'none';
                            } else {
                                adjustmentSection.style.display = 'none';
                                directSection.style.display = 'block';
                            }
                            
                            // Check subtract reason visibility on load
                            toggleSubtractReason();
                        });
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
