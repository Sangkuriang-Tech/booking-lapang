<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'settlement_method' => [
                'nullable',
                'string',
                Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_QRIS]),
            ],
            'settlement_amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token QR wajib diisi.',
            'settlement_method.in' => 'Metode pelunasan harus tunai, transfer, atau QRIS.',
            'settlement_amount.integer' => 'Nominal pelunasan harus berupa angka bulat.',
            'settlement_amount.min' => 'Nominal pelunasan minimal Rp1.',
        ];
    }
}
