<?php

namespace App\Http\Requests\Dashboard;

use App\Models\VenueUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignStaffRequest extends FormRequest
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
            'email' => ['required', 'email:rfc'],
            'role' => ['required', 'string', Rule::in([VenueUser::ROLE_OWNER, VenueUser::ROLE_STAFF])],
        ];
    }
}
