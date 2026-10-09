<?php

namespace App\Http\Requests;

use App\Models\MonitoringLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitoringLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama_lokasi' => ['required', 'string', 'max:255', 'unique:monitoring_locations,nama_lokasi'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(MonitoringLocation::STATUS))],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'nama_lokasi.required' => 'Nama lokasi wajib diisi.',
            'nama_lokasi.string' => 'Nama lokasi tidak valid.',
            'nama_lokasi.max' => 'Nama lokasi maksimal 255 karakter.',
            'nama_lokasi.unique' => 'Nama lokasi sudah digunakan.',
            'keterangan.string' => 'Keterangan tidak valid.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }
}
