<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOtsBookingRequest extends FormRequest
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
            'court_ulid' => ['required', 'string', 'size:26'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:25'],
            'payment_mode' => ['required', 'string', Rule::in([Booking::PAYMENT_MODE_DP, Booking::PAYMENT_MODE_FULL])],
            'payment_method' => ['required', 'string', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_QRIS])],
            'direct_check_in' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
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
            'ends_at.after' => 'Jam selesai harus setelah jam mulai.',
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'customer_name.max' => 'Nama pelanggan maksimal 100 karakter.',
            'customer_phone.required' => 'Nomor telepon pelanggan wajib diisi.',
            'payment_mode.required' => 'Jenis pembayaran wajib dipilih.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
        ];
    }
}
