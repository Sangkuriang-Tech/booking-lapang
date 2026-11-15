<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UploadVenuePhotoRequest extends FormRequest
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
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'alt_text' => ['required', 'string', 'max:160'],
            'is_cover' => ['sometimes', 'boolean'],
        ];
    }
}
