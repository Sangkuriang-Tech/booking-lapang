<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'price_monthly' => 150000,
                'max_venues' => 1,
                'max_courts_per_venue' => 4,
                'max_staff_total' => 3,
                'max_payment_accounts_per_venue' => 1,
                'proof_retention_days' => 30,
                'can_export_reports' => false,
                'is_active' => true,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'price_monthly' => 350000,
                'max_venues' => 2,
                'max_courts_per_venue' => 12,
                'max_staff_total' => 10,
                'max_payment_accounts_per_venue' => 3,
                'proof_retention_days' => 90,
                'can_export_reports' => true,
                'is_active' => true,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'price_monthly' => 750000,
                'max_venues' => null,
                'max_courts_per_venue' => null,
                'max_staff_total' => 25,
                'max_payment_accounts_per_venue' => null,
                'proof_retention_days' => 180,
                'can_export_reports' => true,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }
}
