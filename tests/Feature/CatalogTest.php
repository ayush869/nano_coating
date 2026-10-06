<?php

namespace Tests\Feature;

use App\Models\Coating;
use App\Models\Product;
use App\Models\Service;
use PHPUnit\Framework\Attributes\Test;

/** Admins create any product with their own inputs and formulas. */
class CatalogTest extends FlowTestCase
{
    private function definition(array $overrides = []): string
    {
        return json_encode($overrides + [
            'measure_unit' => 'sq ft',
            'fields' => [
                ['key' => 'height', 'label' => 'Height', 'type' => 'dimension', 'unit' => 'ft', 'default' => 10, 'customer' => true],
                ['key' => 'width', 'label' => 'Width', 'type' => 'dimension', 'unit' => 'ft', 'default' => 0, 'customer' => true],
                ['key' => 'texture', 'label' => 'Texture', 'type' => 'select', 'default' => 1, 'customer' => true,
                    'options' => [['label' => 'Smooth', 'value' => 1], ['label' => 'Rough', 'value' => 1.3]]],
            ],
            'constants' => [['key' => 'wastage', 'label' => 'Wastage', 'value' => 1.05]],
            'area_formula' => 'height * width',
            'price_formula' => 'area * rate * texture * wastage',
            'uses_coating' => true,
            'apply_min' => true,
        ]);
    }

    #[Test]
    public function admin_creates_a_product_with_a_custom_formula_and_it_prices_quotes(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'service_id' => Service::where('name', 'Walls')->value('id'),
            'name' => 'Feature wall',
            'definition' => $this->definition(),
            'coating_ids' => [Coating::where('name', 'Wall protection')->value('id')],
            'active' => 1,
            'customer_visible' => 1,
        ])->assertSessionHasNoErrors();

        $product = Product::where('name', 'Feature wall')->first();
        $this->assertSame('feature-wall', $product->slug);
        $this->assertCount(1, $product->coatings);

        $q = $this->makeQuote([[
            'product_id' => $product->id, 'qty' => 1,
            'inputs' => ['height' => 120, 'height__unit' => 'in', 'width' => 20, 'texture' => 1.3],
            'coating_id' => Coating::where('name', 'Wall protection')->value('id'),
        ]]);

        // 10 × 20 = 200 sq ft × ₹12 × 1.3 × 1.05 = 3276
        $this->assertSame(200.0, $q->lines[0]->area);
        $this->assertSame(3276.0, $q->lines[0]->amount);
        $this->assertSame('120 in × 20 ft = 200 sq ft', $q->lines[0]->area_text);
        $this->assertSame('Height: 120 in · Width: 20 ft · Texture: Rough', $q->lines[0]->inputSummary());
    }

    #[Test]
    public function product_coating_restriction_is_enforced(): void
    {
        $line = $this->glassTopLine();
        $line['coating_id'] = Coating::where('name', 'Fabric protection')->value('id');

        $this->actingAs($this->sales)->post(route('quotes.store'), $this->payload([$line]))
            ->assertSessionHasErrors(['lines.1.coating_id']);
    }

    #[Test]
    public function invalid_definitions_are_rejected_with_clear_messages(): void
    {
        $base = ['service_id' => Service::value('id'), 'name' => 'Bad', 'active' => 1];
        $this->actingAs($this->admin);

        $this->post(route('admin.products.store'), $base + ['definition' => $this->definition(['area_formula' => 'height * depth'])])
            ->assertSessionHasErrors(['area_formula' => 'Area formula uses unknown name(s): depth.']);

        $this->post(route('admin.products.store'), $base + ['definition' => $this->definition(['price_formula' => 'area * (rate'])])
            ->assertSessionHasErrors('price_formula');

        $def = json_decode($this->definition(), true);
        $def['fields'][0]['key'] = 'rate';
        $this->post(route('admin.products.store'), $base + ['definition' => json_encode($def)])
            ->assertSessionHasErrors(['fields.0.key' => 'Input 1: "rate" is a reserved name.']);

        $def = json_decode($this->definition(), true);
        $def['area_formula'] = 'height / width'; // width defaults to 0
        $this->post(route('admin.products.store'), $base + ['definition' => json_encode($def)])
            ->assertSessionHasErrors(['area_formula' => 'With the default values the formula fails: Division by zero.']);

        $this->assertSame(0, Product::where('name', 'Bad')->count());
    }

    #[Test]
    public function duplicate_makes_an_inactive_variant(): void
    {
        $chair = Product::where('name', 'Curved accent chair')->first();
        $this->actingAs($this->admin)->post(route('admin.products.duplicate', $chair))->assertRedirect();

        $copy = Product::where('name', 'Curved accent chair (copy)')->first();
        $this->assertFalse($copy->active);
        $this->assertSame($chair->area_formula, $copy->area_formula);
        $this->assertNotSame($chair->slug, $copy->slug);
    }

    #[Test]
    public function staff_only_products_are_hidden_from_customers(): void
    {
        $chair = Product::where('name', 'Office chair')->first();
        $chair->update(['customer_visible' => false]);

        $this->actingAs($this->customer, 'customer')->post(route('portal.requests.store'), ['payload' => json_encode([
            'site_id' => $this->puneSite->id,
            'lines' => [['product_id' => $chair->id, 'qty' => 1, 'inputs' => [], 'coating_id' => $chair->default_coating_id]],
        ])])->assertSessionHasErrors('lines.1.product_id');

        $this->get(route('site.service', $chair->service))->assertOk()->assertDontSee('Office chair');
    }

    #[Test]
    public function admin_catalog_pages_render(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Sofa (build by parts)');
        $this->get(route('admin.products.create'))->assertOk()->assertSee('Built from parts');
        $this->get(route('admin.products.create', ['template' => 'wall']))->assertOk();
        $this->get(route('admin.products.edit', Product::first()))->assertOk();
        $this->get(route('admin.services.index'))->assertOk();
        $this->get(route('admin.services.edit', Service::first()))->assertOk();
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('built-in test gateway');
    }
}
