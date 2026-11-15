<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentAccountRequest extends FormRequest
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
            'bank_name' => ['nullable', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'qris_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'current_password' => ['required', 'string'],
        ];
    }
}
