<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A clean install: company settings, the rate card the formulas price from,
 * the starter products (formulas) and a single admin login. No demo
 * customers, quotes or payments.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RateCardSeeder::class, CatalogSeeder::class]);

        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            ['name' => 'Admin', 'password' => '123456', 'role' => User::ADMIN, 'active' => true],
        );
    }
}
