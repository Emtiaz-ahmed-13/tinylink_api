<?php

namespace Database\Seeders;

use App\Models\Url;
use App\Models\User;
use Illuminate\Database\Seeder;

class UrlSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@tinylink.test',
            'password' => 'password',
        ]);

        Url::factory()->create([
            'user_id' => $demo->id,
            'original_url' => 'https://laravel.com/docs',
            'short_code' => 'docs',
            'click_count' => 5,
        ]);

        Url::factory()->count(4)->create([
            'user_id' => $demo->id,
        ]);

        $other = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@tinylink.test',
            'password' => 'password',
        ]);

        Url::factory()->count(2)->create([
            'user_id' => $other->id,
        ]);
    }
}
