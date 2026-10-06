<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Coating;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Material;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RateCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class FlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $sales;
    protected User $tech;
    protected Customer $customer;
    protected Site $puneSite;
    protected Site $bengaluruSite;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        $this->seed([RateCardSeeder::class, CatalogSeeder::class]);

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'password', 'role' => 'admin']);
        $this->sales = User::create(['name' => 'Sales', 'email' => 's@x.test', 'password' => 'password', 'role' => 'sales']);
        $this->tech = User::create(['name' => 'Tech', 'email' => 't@x.test', 'password' => 'password', 'role' => 'technician']);

        $this->customer = Customer::create(['name' => 'Test Customer', 'phone' => '9822000000', 'email' => 'cust@x.test']);
        $this->puneSite = $this->customer->sites()->create(['address' => 'Aundh', 'city' => 'Pune', 'state_code' => '27']);
        $this->bengaluruSite = $this->customer->sites()->create(['address' => 'Whitefield', 'city' => 'Bengaluru', 'state_code' => '29']);
    }

    /**
     * Staff use the "web" guard and customers the "customer" guard. Being
     * explicit matters in tests because one app instance serves every request.
     */
    public function actingAs($user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? ($user instanceof Customer ? 'customer' : 'web'));
    }

    protected function id(string $class, string $name): int
    {
        return $class::where('name', $name)->value('id');
    }

    /** The worked example from the original brief: ₹1,422.00. */
    protected function sofaLine(array $inputs = ['seats' => 3, 'backs' => 2, 'arms' => 2, 'cushions' => 2]): array
    {
        return [
            'product_id' => $this->id(Product::class, 'Sofa (build by parts)'),
            'name' => 'Sofa',
            'qty' => 1,
            'inputs' => $inputs,
            'coating_id' => $this->id(Coating::class, 'Fabric protection'),
            'addon_id' => $this->id(Addon::class, 'Anti-microbial layer'),
            'material_id' => $this->id(Material::class, 'Velvet'),
            'design_id' => $this->id(Design::class, 'Tufted / buttoned'),
        ];
    }

    protected function glassTopLine(): array
    {
        return [
            'product_id' => $this->id(Product::class, 'Glass table top'),
            'name' => 'Glass top', 'qty' => 1,
            'inputs' => ['length' => 6, 'width' => 3, 'sides' => 1],
            'coating_id' => $this->id(Coating::class, 'Glass protection'),
            'material_id' => $this->id(Material::class, 'Glass'),
            'design_id' => $this->id(Design::class, 'Plain'),
        ];
    }

    protected function payload(array $lines, array $extra = []): array
    {
        return ['payload' => json_encode($extra + [
            'customer_id' => $this->customer->id,
            'site_id' => $this->puneSite->id,
            'discount_type' => null,
            'discount_value' => 0,
            'visit_charge' => 0,
            'lines' => $lines,
        ])];
    }

    protected function makeQuote(array $lines, array $extra = [], ?User $as = null): Quote
    {
        $this->actingAs($as ?? $this->sales)->post(route('quotes.store'), $this->payload($lines, $extra))->assertSessionHasNoErrors();

        return Quote::latest('id')->first();
    }

    protected function sentQuote(array $lines): Quote
    {
        $q = $this->makeQuote($lines);
        $this->post(route('quotes.status', $q), ['status' => 'sent']);

        return $q->fresh();
    }
}
