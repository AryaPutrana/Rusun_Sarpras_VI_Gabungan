@extends('layouts.app')

@section('title', 'Tambah Lokasi Monitoring')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0 fw-bold">Tambah Lokasi Monitoring</h4>
            <a href="{{ route('lokasi.index') }}" class="text-decoration-none">&larr; Kembali</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('lokasi.store') }}">
                    @csrf
                    @include('locations._form', ['lokasi' => null])
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection