<?php

namespace Database\Seeders;

use App\Models\Player;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlayerSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (range(1, 10) as $number) {
                Player::query()->create([
                    'name' => fake()->name(),
                    'phone' => fake()->phoneNumber(),
                    'email' => fake()->unique()->safeEmail(),
                ]);
            }
        });
    }
}
