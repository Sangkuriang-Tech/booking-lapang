<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SelectVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isStaff() || $user->isSuperadmin());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'venue_ulid' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'venue_ulid.required' => 'Venue wajib dipilih.',
            'venue_ulid.size' => 'Identitas venue tidak valid.',
        ];
    }
}
