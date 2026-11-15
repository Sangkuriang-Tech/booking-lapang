<?php

namespace App\Http\Requests\Booking;

use App\Models\Booking;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCustomer() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'court_ulid' => ['required', 'string', 'size:26'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => [
                'required',
                'string',
                'max:25',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! PhoneNormalizer::isValid($value)) {
                        $fail('Nomor telepon harus berupa nomor Indonesia yang valid (contoh: 081234567890).');
                    }
                },
            ],
            'payment_mode' => ['required', 'string', Rule::in([Booking::PAYMENT_MODE_DP, Booking::PAYMENT_MODE_FULL])],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'court_ulid.required' => 'Lapangan wajib dipilih.',
            'starts_at.required' => 'Jam mulai wajib dipilih.',
            'ends_at.required' => 'Jam selesai wajib dipilih.',
            'ends_at.after' => 'Jam selesai harus lebih besar dari jam mulai.',
            'customer_name.required' => 'Nama pembooking wajib diisi.',
            'customer_name.max' => 'Nama maksimal 100 karakter.',
            'customer_phone.required' => 'Nomor telepon wajib diisi.',
            'payment_mode.required' => 'Metode pembayaran (DP atau Lunas) wajib dipilih.',
            'payment_mode.in' => 'Pilihan pembayaran tidak valid.',
        ];
    }
}
