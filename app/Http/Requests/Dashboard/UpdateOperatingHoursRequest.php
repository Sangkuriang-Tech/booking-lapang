<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.opens_min' => ['required', 'integer', 'between:0,1439'],
            'hours.*.closes_min' => ['required', 'integer', 'between:1,2880'],
            'hours.*.is_closed' => ['required', 'boolean'],
        ];
    }
}
