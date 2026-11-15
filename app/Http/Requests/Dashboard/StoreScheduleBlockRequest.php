<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleBlockRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'court_ulid.required' => 'Lapangan wajib dipilih.',
            'starts_at.required' => 'Waktu mulai blokir wajib diisi.',
            'ends_at.required' => 'Waktu selesai blokir wajib diisi.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'reason.required' => 'Alasan blokir slot wajib diisi.',
            'reason.max' => 'Alasan blokir maksimal 255 karakter.',
        ];
    }
}
