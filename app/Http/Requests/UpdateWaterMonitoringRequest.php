<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateWaterMonitoringRequest extends StoreWaterMonitoringRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $monitoring = $this->route('monitoring');

        return [
            'location_id' => [
                'required',
                Rule::exists('monitoring_locations', 'id')->where(function ($query) use ($monitoring) {
                    $query->where('status', 'aktif')
                        ->orWhere('id', $monitoring->location_id);
                }),
                Rule::unique('water_monitorings')->ignore($monitoring->id)->where(function ($query) {
                    return $query->where('tanggal', $this->tanggal)
                        ->where('sesi', $this->sesi);
                }),
            ],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'sesi' => ['required', Rule::in(array_keys(\App\Models\WaterMonitoring::SESI))],
            'kondisi' => ['required', Rule::in(array_keys(\App\Models\WaterMonitoring::KONDISI))],
            'keterangan' => ['required', 'string'],
            'foto' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }
}
