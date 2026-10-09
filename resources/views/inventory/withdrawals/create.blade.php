@extends('layouts.app')

@section('title', 'Pengambilan Barang - SI Inventory Sarpras')
@section('page-title', 'Pengambilan Barang')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-box-arrow-right"></i> Form Pengambilan Barang
            </div>
            <div class="card-body">
                <form action="{{ route('inventory.withdrawals.store') }}" method="POST" id="withdrawalForm">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Barang <span class="text-danger">*</span></label>

                        @error('items')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle" id="itemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:45%">Nama Barang</th>
                                        <th style="width:12%">Harga</th>
                                        <th style="width:10%">Stok</th>
                                        <th style="width:13%">Jumlah</th>
                                        <th style="width:15%">Subtotal</th>
                                        <th style="width:5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody"></tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRow">
                            <i class="bi bi-plus-circle"></i> Tambah Barang
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Memilih barang yang sama akan menggabungkan jumlahnya ke satu baris.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="taken_by" class="form-label">Pengambil <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('taken_by') is-invalid @enderror" 
                                   id="taken_by" name="taken_by" value="{{ old('taken_by') }}" 
                                   placeholder="Nama pengambil" required>
                            @error('taken_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="rusun_id" class="form-label">Rusun <span class="text-danger">*</span></label>
                            <select class="form-select @error('rusun_id') is-invalid @enderror" id="rusun_id" name="rusun_id" required>
                                <option value="">-- Pilih Rusun --</option>
                                @foreach($rusuns as $rusun)
                                    <option value="{{ $rusun->id }}" {{ old('rusun_id') == $rusun->id ? 'selected' : '' }}>
                                        {{ $rusun->name }} ({{ $rusun->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('rusun_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Nilai Pengambilan</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control bg-light fw-bold" id="total_display" readonly value="0">
                        </div>
                        <small class="text-muted" id="total_quantity_info"></small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="taken_at" class="form-label">Tanggal dan Waktu Pengambilan <span class="text-danger">*</span></label>
                            <input type="datetime-local" 
                                   class="form-control @error('taken_at') is-invalid @enderror" 
                                   id="taken_at" 
                                   name="taken_at" 
                                   value="{{ old('taken_at', now()->format('Y-m-d\TH:i')) }}" 
                                   required>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Tanggal dan waktu otomatis terisi (realtime hari ini)
                            </small>
                            @error('taken_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Keterangan</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3" 
                                      placeholder="Keterangan tambahan (opsional)">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('inventory.withdrawals.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Pengambilan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Katalog barang (disusun di controller) agar tidak perlu render ulang tiap baris.
    const itemsCatalog = {!! $itemsCatalogJson !!};

    // Jumlah barang yang stoknya habis, ditampilkan sebagai keterangan di dropdown.
    const noStockCount = {!! $noStockCountJson !!};

    // Data dari validasi sebelumnya supaya user tidak perlu mengetik ulang.
    const oldItems = {!! json_encode(old('items', []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

    function formatNumber(num) {
        return Number(num).toLocaleString('id-ID');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    function buildOptions(selectedId) {
        let options = '<option value="">-- Pilih Barang --</option>';

        const emptyStockNotice = noStockCount > 0
            ? '<option value="" disabled>(' + noStockCount + ' barang lain stoknya habis)</option>'
            : '';

        options += emptyStockNotice;

        Object.keys(itemsCatalog).forEach(function (id) {
            const item = itemsCatalog[id];
            options += '<option value="' + id + '"'
                + ' data-code="' + escapeHtml(item.code) + '"'
                + ' data-price="' + item.price + '"'
                + ' data-stock="' + item.stock + '"'
                + ' data-unit="' + escapeHtml(item.unit) + '"'
                + (String(id) === String(selectedId) ? ' selected' : '') + '>'
                + escapeHtml(item.code) + ' - ' + escapeHtml(item.name)
                + ' (Stok: ' + item.stock + ')</option>';
        });

        return options;
    }

    function recalcTotal() {
        let total = 0;
        let totalQty = 0;

        document.querySelectorAll('#itemsTableBody tr').forEach(function (row) {
            const select = row.querySelector('.js-item-select');
            const quantity = parseInt(row.querySelector('.js-quantity').value, 10) || 0;
            const item = itemsCatalog[select.value];

            if (!item) {
                return;
            }

            total += quantity * item.price;
            totalQty += quantity;
        });

        document.getElementById('total_display').value = formatNumber(total);
        document.getElementById('total_quantity_info').textContent =
            'Total ' + totalQty + ' unit dari ' + countFilledRows() + ' jenis barang';
    }

    function countFilledRows() {
        return Array.from(document.querySelectorAll('#itemsTableBody .js-item-select'))
            .filter(function (select) {
                return select.value !== '';
            }).length;
    }

/**
     * Nomor urut baris selalu berurutan (0,1,2,...) supaya field name
     * items[0][...], items[1][...] tidak pernah bolong. Baris menyimpan
     * index lamanya di atribut data-index supaya tidak perlu reinstall listener.
     */
    function reindexRows() {
        const rows = Array.from(document.querySelectorAll('#itemsTableBody tr'));

        rows.forEach(function (tr, position) {
            tr.dataset.index = String(position);
            tr.querySelector('.js-item-select').name = 'items[' + position + '][item_id]';
            tr.querySelector('.js-quantity').name = 'items[' + position + '][quantity]';
        });
    }

    function addRow(itemId, quantity) {
        const tbody = document.getElementById('itemsTableBody');
        const position = document.querySelectorAll('#itemsTableBody tr').length;

        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td>'
            + '<select class="form-select form-select-sm js-item-select" required>'
            + buildOptions(itemId)
            + '</select>'
            + '<div class="invalid-feedback js-item-error"></div>'
            + '</td>'
            + '<td class="js-price text-nowrap">-</td>'
            + '<td class="js-stock text-nowrap">-</td>'
            + '<td>'
            + '<input type="number" class="form-control form-control-sm js-quantity" '
            + 'value="' + (quantity === undefined || quantity === null ? '' : escapeHtml(quantity))
            + '" min="1" placeholder="Jumlah" required>'
            + '</td>'
            + '<td class="text-end js-subtotal">-</td>'
            + '<td class="text-center">'
            + '<button type="button" class="btn btn-outline-danger btn-sm js-remove-row" title="Hapus baris">'
            + '<i class="bi bi-trash"></i>'
            + '</button>'
            + '</td>';

        tbody.appendChild(tr);
        reindexRows();

        updateRow(tr);
        recalcTotal();

        return position;
    }

    function updatePriceColumn(tr) {
        const item = itemsCatalog[tr.querySelector('.js-item-select').value];
        tr.querySelector('.js-price').textContent = item ? 'Rp' + formatNumber(item.price) : '-';
    }

    function updateRow(tr) {
        const select = tr.querySelector('.js-item-select');
        const quantityInput = tr.querySelector('.js-quantity');
        const subtotalCell = tr.querySelector('.js-subtotal');
        const stockCell = tr.querySelector('.js-stock');

        const item = itemsCatalog[select.value];
        const quantity = parseInt(quantityInput.value, 10) || 0;

        if (item) {
            stockCell.textContent = item.stock + ' ' + item.unit;

            const overStock = quantity > item.stock;
            quantityInput.classList.toggle('is-invalid', overStock && quantity > 0);
            subtotalCell.classList.toggle('text-danger', overStock);

            subtotalCell.textContent = 'Rp' + formatNumber(quantity * item.price);
        } else {
            stockCell.textContent = '-';
            subtotalCell.textContent = '-';
            subtotalCell.classList.remove('text-danger');
            quantityInput.classList.remove('is-invalid');
        }
    }

    /**
     * Memilih barang yang sudah dipakai baris lain akan menggabungkan jumlah
     * ke baris tersebut, bukan membuat duplikat.
     */
    function onItemChanged(tr, select) {
        const selectedId = select.value;

        if (selectedId === '') {
            tr.querySelector('.js-item-error').textContent = '';
            updatePriceColumn(tr);
            updateRow(tr);
            recalcTotal();
            return;
        }

        const duplicate = findRowWithItem(selectedId, tr);

        if (duplicate) {
            const currentQuantity = parseInt(tr.querySelector('.js-quantity').value, 10) || 0;
            const duplicateInput = duplicate.querySelector('.js-quantity');
            const existingQuantity = parseInt(duplicateInput.value, 10) || 0;

            duplicateInput.value = existingQuantity + currentQuantity;

            select.value = '';
            duplicate.querySelector('.js-item-error').textContent = 'Barang digabung ke baris ini.';

            updatePriceColumn(tr);
            updateRow(tr);
            updateRow(duplicate);
            recalcTotal();
            return;
        }

        tr.querySelector('.js-item-error').textContent = '';
        updatePriceColumn(tr);
        updateRow(tr);
        recalcTotal();
    }

    function findRowWithItem(itemId, excludeRow) {
        const selects = Array.from(document.querySelectorAll('#itemsTableBody .js-item-select'));

        for (let i = 0; i < selects.length; i++) {
            if (selects[i].value === itemId && selects[i] !== excludeRow.querySelector('.js-item-select')) {
                return selects[i].closest('tr');
            }
        }

        return null;
    }

    function removeRow(tr) {
        const rows = document.querySelectorAll('#itemsTableBody tr');

        // Minimal satu baris selalu ada, supaya form tidak pernah kosong.
        if (rows.length <= 1) {
            tr.querySelector('.js-item-select').value = '';
            tr.querySelector('.js-quantity').value = '';
            tr.querySelector('.js-item-error').textContent = '';
            updatePriceColumn(tr);
            updateRow(tr);
            recalcTotal();
            return;
        }

        tr.remove();
        reindexRows();

        document.querySelectorAll('#itemsTableBody tr').forEach(function (row) {
            updateRow(row);
        });
        recalcTotal();
    }

    // Delegasi event: satu listener untuk seluruh tabel, jadi aman saat baris dihapus.
    document.getElementById('itemsTableBody').addEventListener('change', function(e) {
        if (e.target.classList.contains('js-item-select')) {
            onItemChanged(e.target.closest('tr'), e.target);
        }
    });

    document.getElementById('itemsTableBody').addEventListener('input', function(e) {
        if (e.target.classList.contains('js-quantity')) {
            updateRow(e.target.closest('tr'));
            recalcTotal();
        }
    });

    document.getElementById('itemsTableBody').addEventListener('click', function(e) {
        const button = e.target.closest('.js-remove-row');
        if (button) {
            removeRow(button.closest('tr'));
        }
    });

    document.getElementById('addItemRow').addEventListener('click', function() {
        addRow('', '');
    });

    // Isi ulang dari input sebelumnya (validasi gagal) supaya tidak kehilangan data.
    if (oldItems && oldItems.length > 0) {
        oldItems.forEach(function (line) {
            addRow(line.item_id ?? '', line.quantity ?? '');
        });
    } else {
        addRow('', '');
    }

    // Pre-select dari halaman detail barang (?item=id)
    @if($selectedItemId)
        if (document.querySelectorAll('#itemsTableBody tr').length === 1) {
            const firstRow = document.querySelector('#itemsTableBody tr');
            const firstSelect = firstRow.querySelector('.js-item-select');

            if (firstSelect.querySelector('option[value="{{ $selectedItemId }}"]')) {
                firstSelect.value = '{{ $selectedItemId }}';
                onItemChanged(firstRow, firstSelect);
            }
        }
    @endif

    // Form validation before submit
    $('#withdrawalForm').on('submit', function(e) {
        let hasInvalid = false;
        let hasEmpty = false;

        document.querySelectorAll('#itemsTableBody tr').forEach(function (row) {
            const select = row.querySelector('.js-item-select');
            const quantityInput = row.querySelector('.js-quantity');
            const quantity = parseInt(quantityInput.value, 10) || 0;
            const item = itemsCatalog[select.value];

            if (!item || quantity < 1) {
                hasEmpty = true;
                return;
            }

            if (quantity > item.stock) {
                hasInvalid = true;
                quantityInput.classList.add('is-invalid');
            }
        });

        if (hasEmpty) {
            e.preventDefault();
            alert('Setiap baris harus punya barang dan jumlah minimal 1.');
            return false;
        }

        if (hasInvalid) {
            e.preventDefault();
            alert('Ada jumlah yang melebihi stok tersedia. Periksa kembali kolom jumlah.');
            return false;
        }
    });
});
</script>
@endpush
