<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SubscriptionPlanSeeder::class,
            SportSeeder::class,
        ]);

        if (app()->environment(['local', 'staging'])) {
            $this->call([
                LocalDevelopmentSeeder::class,
            ]);
        }
    }
}
