<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    public function run(): void
    {
        $sports = [
            ['slug' => 'futsal', 'name' => 'Futsal'],
            ['slug' => 'mini-soccer', 'name' => 'Mini Soccer'],
            ['slug' => 'padel', 'name' => 'Padel'],
            ['slug' => 'basket', 'name' => 'Basket'],
            ['slug' => 'badminton', 'name' => 'Badminton'],
        ];

        foreach ($sports as $sport) {
            Sport::query()->updateOrCreate(
                ['slug' => $sport['slug']],
                ['name' => $sport['name'], 'is_active' => true],
            );
        }
    }
}
