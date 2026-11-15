<?php

namespace App\Http\Requests\Dashboard;

use App\Models\PriceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceRuleRequest extends FormRequest
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
            'day_type' => ['required', 'string', Rule::in([PriceRule::DAY_TYPE_WEEKDAY, PriceRule::DAY_TYPE_WEEKEND])],
            'start_min' => ['required', 'integer', 'between:0,1439'],
            'end_min' => ['required', 'integer', 'between:1,2880', 'gt:start_min'],
            'price_per_hour' => ['required', 'integer', 'min:1000'],
        ];
    }
}
