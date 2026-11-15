<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVenueSettingsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:25'],
            'timezone' => ['required', 'string', Rule::in(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])],
            'hold_minutes' => ['required', 'integer', 'between:5,120'],
            'dp_percent' => ['required', 'integer', 'between:0,100'],
            'cancel_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'reupload_minutes' => ['required', 'integer', 'between:5,1440'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
