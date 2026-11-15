<?php

namespace App\Http\Requests\Booking;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPaymentProofRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in([Payment::METHOD_TRANSFER, Payment::METHOD_QRIS])],
            'proof' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Metode transfer atau QRIS wajib dipilih.',
            'method.in' => 'Metode pembayaran hanya mendukung transfer bank atau QRIS.',
            'proof.required' => 'Bukti pembayaran wajib diunggah.',
            'proof.file' => 'Bukti pembayaran harus berupa file gambar.',
            'proof.max' => 'Bukti bayar maksimal 2 MB (JPG atau PNG).',
            'proof.mimes' => 'Bukti bayar hanya mendukung format JPG atau PNG.',
            'note.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
