<?php

namespace App\Http\Requests;

use App\Models\FinanceReport;
use Illuminate\Validation\Rule;

class UpdateFinanceReportRequest extends StoreFinanceReportRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'location_id' => ['required', 'integer', 'exists:finance_locations,id'],
            'kondisi' => ['required', Rule::in(array_keys(FinanceReport::KONDISI))],
            'keterangan' => ['required', 'string'],
            'foto' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }
}
