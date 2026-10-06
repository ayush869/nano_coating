<?php

namespace Tests\Feature;

use App\Models\Coating;
use App\Models\Product;
use App\Services\PdfRenderer;
use PHPUnit\Framework\Attributes\Test;

/**
 * The original acceptance checks, plus: changing a product's formula never
 * changes a saved quote, and the server ignores browser prices.
 */
class AcceptanceTest extends FlowTestCase
{
    #[Test]
    public function rate_change_applies_to_next_quote_but_not_earlier_ones(): void
    {
        $old = $this->makeQuote([$this->sofaLine()]);
        $this->assertSame(18.0, $old->lines[0]->coating_rate);
        $this->assertSame(1422.0, $old->lines[0]->amount);

        $fabric = Coating::where('name', 'Fabric protection')->first();
        $this->actingAs($this->admin)->put(route('admin.rates.update', ['coatings', $fabric->id]), [
            'name' => $fabric->name, 'rate_per_sqft' => 20, 'sort' => $fabric->sort, 'active' => 1,
        ])->assertSessionHasNoErrors();

        $new = $this->makeQuote([$this->sofaLine()]);
        $this->assertSame(20.0, $new->lines[0]->coating_rate);
        $this->assertSame(1540.5, $new->lines[0]->amount); // 39.5 × (20 + 6) × 1.2 × 1.25

        $old->refresh();
        $this->assertSame(1422.0, $old->lines[0]->amount);
        $this->actingAs($this->sales)->get(route('quotes.show', $old))->assertSee('₹1,422.00');
        $this->assertDatabaseHas('audit_logs', ['table_name' => 'coatings', 'record_id' => $fabric->id, 'action' => 'updated', 'user_id' => $this->admin->id]);
    }

    #[Test]
    public function product_formula_change_never_changes_a_saved_quote(): void
    {
        $old = $this->makeQuote([$this->sofaLine()]);
        $sofa = Product::where('name', 'Sofa (build by parts)')->first();

        // Admin measures real sofas and changes the seat size 4.5 → 5.
        $constants = collect($sofa->constants)->map(fn ($c) => $c['key'] === 'seat_sqft' ? ['value' => 5] + $c : $c)->all();
        $sofa->update(['constants' => $constants]);

        $this->assertSame(39.5, $old->fresh()->lines[0]->area);
        $this->assertSame(1422.0, $old->fresh()->lines[0]->amount);
        $this->assertSame(41.0, $this->makeQuote([$this->sofaLine()])->lines[0]->area); // 3×5 + 2×5 + 2×6 + 2×2
    }

    #[Test]
    public function partial_sofa_prices_differently_from_full_three_seater_without_a_new_product(): void
    {
        $q = $this->makeQuote([
            $this->sofaLine(['seats' => 3, 'backs' => 2, 'arms' => 2]),
            $this->sofaLine(['seats' => 3, 'backs' => 3, 'arms' => 2]),
        ]);

        $this->assertSame(35.5, $q->lines[0]->area);
        $this->assertSame(40.5, $q->lines[1]->area);
        $this->assertNotEquals($q->lines[0]->amount, $q->lines[1]->amount);
    }

    #[Test]
    public function small_item_shows_minimum_item_charge_in_app_and_pdf(): void
    {
        $q = $this->makeQuote([$this->glassTopLine()]);
        $line = $q->lines[0];

        $this->assertSame([360.0, true, 500.0], [$line->raw_amount, $line->min_applied, $line->amount]);
        $this->get(route('quotes.show', $q))->assertOk()->assertSee('Min. item charge')->assertSee('raised to minimum item charge ₹500.00');

        $html = (new PdfRenderer)->html($q);
        $this->assertStringContainsString('Min. item charge', $html);
        $this->assertStringContainsString('calc. ₹360.00', $html);
    }

    #[Test]
    public function pdf_embeds_unicode_font_and_splits_gst_for_maharashtra(): void
    {
        $q = $this->makeQuote([$this->sofaLine(), $this->glassTopLine()]);
        $this->assertTrue($q->intra_state);

        $html = (new PdfRenderer)->html($q);
        $this->assertStringContainsString('NotoSans-Regular.ttf', $html);
        $this->assertStringContainsString('CGST @ 9%', $html);
        $this->assertStringContainsString('SGST @ 9%', $html);
        $this->assertStringNotContainsString('IGST', $html);

        $pdf = (new PdfRenderer)->quote($q)->output();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/FontName\s*\/[A-Z]{6}\+NotoSans/', $pdf);
    }

    #[Test]
    public function inter_state_site_uses_igst(): void
    {
        $q = $this->makeQuote([$this->sofaLine()], ['site_id' => $this->bengaluruSite->id]);

        $this->assertFalse($q->intra_state);
        $this->assertSame([0.0, 0.0, 450.0, 2950.0], [$q->cgst, $q->sgst, $q->igst, $q->total]);
    }

    #[Test]
    public function server_recalculates_and_ignores_browser_prices(): void
    {
        $line = $this->sofaLine() + ['amount' => 1, 'coating_rate' => 0.01, 'snap' => ['coating' => ['id' => 1, 'value' => 0.01]]];
        $q = $this->makeQuote([$line], ['total' => 1, 'subtotal' => 1]);

        $this->assertSame(18.0, $q->lines[0]->coating_rate);
        $this->assertSame(1422.0, $q->subtotal);
        $this->assertSame(2950.0, $q->total);
    }
}
