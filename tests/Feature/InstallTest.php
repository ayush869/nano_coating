<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_a_fresh_install_has_one_admin_and_the_formula_catalog_only(): void
    {
        $this->assertSame(['admin@gmail.com'], User::pluck('email')->all());
        $this->assertTrue(User::first()->isAdmin());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Quote::count());
        $this->assertGreaterThan(0, Product::count());

        $service = Service::first();
        Storage::disk('public')->assertExists($service->image_path);
        $this->get('/')->assertOk()->assertSee($service->name);
        $this->get(route('site.service', $service))->assertOk();
    }

    public function test_admin_logs_in_with_the_default_password(): void
    {
        $this->post('/staff/login', ['email' => 'admin@gmail.com', 'password' => '123456'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('admin.products.index'))->assertOk();
    }

    public function test_staff_login_ignores_a_customer_page_it_was_sent_from(): void
    {
        $this->get(route('portal.requests.create'))->assertRedirect();

        $this->post('/staff/login', ['email' => 'admin@gmail.com', 'password' => '123456'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_staff_login_returns_to_the_staff_page_that_asked_for_it(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect();

        $this->post('/staff/login', ['email' => 'admin@gmail.com', 'password' => '123456'])
            ->assertRedirect(route('admin.products.index'));
    }
}
