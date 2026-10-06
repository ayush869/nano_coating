<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RateCardSeeder::class, CatalogSeeder::class]);

        User::create(['name' => 'Owner', 'email' => 'admin@example.com', 'password' => 'password', 'role' => User::ADMIN, 'phone' => '9800000000']);
        User::create(['name' => 'Priya (Sales)', 'email' => 'sales@example.com', 'password' => 'password', 'role' => User::SALES, 'phone' => '9800000001']);
        User::create(['name' => 'Ravi (Technician)', 'email' => 'tech@example.com', 'password' => 'password', 'role' => User::TECHNICIAN, 'phone' => '9800000002']);
        User::create(['name' => 'Suresh (Technician)', 'email' => 'tech2@example.com', 'password' => 'password', 'role' => User::TECHNICIAN, 'phone' => '9800000003']);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
