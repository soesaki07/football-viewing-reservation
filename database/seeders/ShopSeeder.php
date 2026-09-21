<?php

namespace Database\Seeders;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owners = User::where('role', 'shop_owner')->get();
        foreach ($owners as $owner) {
            Shop::factory()->create([
                'user_id' => $owner->id,
            ]);
        }
    }
}
