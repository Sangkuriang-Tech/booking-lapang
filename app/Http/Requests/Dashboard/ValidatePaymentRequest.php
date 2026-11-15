<?php

namespace App\Http\Requests\Dashboard;

use App\Actions\Booking\ValidatePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidatePaymentRequest extends FormRequest
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
            'decision' => [
                'required',
                'string',
                Rule::in([
                    ValidatePayment::DECISION_APPROVE,
                    ValidatePayment::DECISION_REUPLOAD,
                    ValidatePayment::DECISION_REJECT,
                ]),
            ],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('decision'), [
                    ValidatePayment::DECISION_REUPLOAD,
                    ValidatePayment::DECISION_REJECT,
                ], true)),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan validasi wajib dipilih.',
            'decision.in' => 'Keputusan validasi tidak valid.',
            'reason.required' => 'Alasan wajib diisi saat meminta bukti baru atau menolak pembayaran.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
