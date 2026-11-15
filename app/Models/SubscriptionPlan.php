<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'price_monthly',
        'max_venues',
        'max_courts_per_venue',
        'max_staff_total',
        'max_payment_accounts_per_venue',
        'proof_retention_days',
        'can_export_reports',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'max_venues' => 'integer',
            'max_courts_per_venue' => 'integer',
            'max_staff_total' => 'integer',
            'max_payment_accounts_per_venue' => 'integer',
            'proof_retention_days' => 'integer',
            'can_export_reports' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
