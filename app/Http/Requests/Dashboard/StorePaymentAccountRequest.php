<?php

namespace App\Http\Requests\Dashboard;

use App\Models\VenuePaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentAccountRequest extends FormRequest
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
            'kind' => ['required', 'string', Rule::in([VenuePaymentAccount::KIND_BANK, VenuePaymentAccount::KIND_QRIS])],
            'bank_name' => ['required_if:kind,bank', 'nullable', 'string', 'max:60'],
            'account_number' => ['required_if:kind,bank', 'nullable', 'string', 'max:40'],
            'account_holder' => ['required_if:kind,bank', 'nullable', 'string', 'max:100'],
            'qris_image' => ['required_if:kind,qris', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'current_password' => ['required', 'string'],
        ];
    }
}
