@extends('layouts.app')

@section('title', 'Detail Pengambilan - SI Inventory Sarpras')
@section('page-title', 'Detail Pengambilan')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Detail Pengambilan Barang
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <th width="35%">Pengambil</th>
                                <td><strong>{{ $withdrawal->taken_by }}</strong></td>
                            </tr>
                            <tr>
                                <th>Rusun</th>
                                <td><span class="badge bg-info fs-6">{{ $withdrawal->rusun->name }} ({{ $withdrawal->rusun->code }})</span></td>
                            </tr>
                            <tr>
                                <th>Jumlah Barang</th>
                                <td>{{ $withdrawal->total_quantity }} unit ({{ $withdrawal->items->count() }} jenis)</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <th width="35%">Tanggal & Waktu</th>
                                <td>{{ $withdrawal->taken_at ? $withdrawal->taken_at->translatedFormat('d F Y H:i') : '-' }}</td>
                            </tr>
                            <tr>
                                <th>Dicatat pada</th>
                                <td>{{ $withdrawal->created_at ? $withdrawal->created_at->translatedFormat('d F Y H:i') : '-' }}</td>
                            </tr>
                            <tr>
                                <th>Keterangan</th>
                                <td>{{ $withdrawal->description ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <hr>

                <h6 class="mb-3">Rincian Barang</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width:5%">No</th>
                                <th style="width:15%">ID Barang</th>
                                <th>Nama Barang</th>
                                <th style="width:15%" class="text-end">Harga Satuan</th>
                                <th style="width:12%" class="text-end">Jumlah</th>
                                <th style="width:20%" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($withdrawal->items as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $item->item->item_code }}</strong></td>
                                    <td>
                                        <a href="{{ route('inventory.items.show', $item->item->id) }}" class="text-decoration-none">
                                            {{ $item->item->name }}
                                        </a>
                                    </td>
                                    <td class="text-end">Rp{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-end">{{ $item->quantity }} {{ $item->item->unit }}</td>
                                    <td class="text-end">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="4" class="text-end">TOTAL</th>
                                <th class="text-end">{{ $withdrawal->total_quantity }}</th>
                                <th class="text-end">Rp{{ number_format($withdrawal->total_value, 0, ',', '.') }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('inventory.withdrawals.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    @if($withdrawal->items->isNotEmpty())
                        
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
