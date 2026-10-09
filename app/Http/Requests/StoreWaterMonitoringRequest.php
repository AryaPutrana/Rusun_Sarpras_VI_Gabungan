<?php

namespace App\Http\Requests;

use App\Models\WaterMonitoring;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaterMonitoringRequest extends FormRequest
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
            'location_id' => [
                'required',
                Rule::exists('monitoring_locations', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
                Rule::unique('water_monitorings')->where(function ($query) {
                    return $query->where('tanggal', $this->tanggal)
                        ->where('sesi', $this->sesi);
                }),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'sesi' => ['required', Rule::in(array_keys(WaterMonitoring::SESI))],
            'kondisi' => ['required', Rule::in(array_keys(WaterMonitoring::KONDISI))],
            'keterangan' => ['required', 'string'],
            'foto' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Lokasi wajib dipilih.',
            'location_id.exists' => 'Lokasi tidak valid atau tidak aktif.',
            'location_id.unique' => 'Pemeriksaan untuk lokasi dan sesi tersebut sudah dilakukan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Tanggal tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh melebihi hari ini.',
            'sesi.required' => 'Sesi pemeriksaan wajib dipilih.',
            'sesi.in' => 'Sesi pemeriksaan tidak valid.',
            'kondisi.required' => 'Kondisi wajib dipilih.',
            'kondisi.in' => 'Kondisi tidak valid.',
            'keterangan.required' => 'Keterangan wajib diisi.',
            'keterangan.string' => 'Keterangan tidak valid.',
            'foto.required' => 'Foto wajib diunggah.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format foto hanya JPG, JPEG, atau PNG.',
            'foto.max' => 'Ukuran foto maksimal 5 MB.',
        ];
    }
}
