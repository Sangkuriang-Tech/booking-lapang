<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'max_venues' => ['nullable', 'integer', 'min:1'],
            'max_courts_per_venue' => ['nullable', 'integer', 'min:1'],
            'max_staff_total' => ['nullable', 'integer', 'min:1'],
            'max_payment_accounts_per_venue' => ['nullable', 'integer', 'min:1'],
            'proof_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'can_export_reports' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
