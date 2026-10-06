<?php

namespace Tests\Feature;

use App\Models\Coating;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Services\DocumentNumber;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

class QuoteLifecycleTest extends FlowTestCase
{
    #[Test]
    public function numbers_run_per_financial_year(): void
    {
        $this->assertSame('2026-27', DocumentNumber::financialYear(Carbon::parse('2026-04-01')));
        $this->assertSame('2025-26', DocumentNumber::financialYear(Carbon::parse('2026-03-31')));
        $this->assertSame('2099-00', DocumentNumber::financialYear(Carbon::parse('2099-12-01')));

        $this->assertSame('QT/2026-27/0001', DocumentNumber::next('QT', Carbon::parse('2026-10-06')));
        $this->assertSame('QT/2026-27/0002', DocumentNumber::next('QT', Carbon::parse('2027-01-15')));
        $this->assertSame('QT/2027-28/0001', DocumentNumber::next('QT', Carbon::parse('2027-04-01')));
    }

    #[Test]
    public function revisions_keep_the_number_with_a_suffix(): void
    {
        $q = $this->sentQuote([$this->sofaLine()]);
        $this->post(route('quotes.revise', $q))->assertRedirect();
        $r1 = Quote::where('revision', 1)->first();

        $this->assertSame($q->number.'-R1', $r1->display_number);
        $this->assertSame([Quote::DRAFT, Quote::SUPERSEDED], [$r1->status, $q->fresh()->status]);
        $this->assertSame(1422.0, $r1->lines[0]->amount);
        $this->assertSame($q->lines[0]->product_snapshot, $r1->lines[0]->product_snapshot);
    }

    #[Test]
    public function paid_quotes_cannot_be_revised(): void
    {
        $q = $this->sentQuote([$this->sofaLine()]);
        $this->post(route('payments.store', $q), ['amount' => 500, 'mode' => 'cash'])->assertSessionHas('ok');

        $this->post(route('quotes.revise', $q->fresh()))->assertSessionHas('error');
        $this->assertSame(0, Quote::where('revision', 1)->count());
    }

    #[Test]
    public function editing_a_draft_keeps_snapshotted_rates_for_unchanged_lines(): void
    {
        $quote = $this->makeQuote([$this->sofaLine(), $this->glassTopLine()]);
        [$sofa, $glass] = $quote->lines;

        Coating::where('name', 'Fabric protection')->update(['rate_per_sqft' => 30]);

        $stone = $this->glassTopLine();
        $stone['product_id'] = \App\Models\Product::where('name', 'Countertop / stone table top')->value('id');
        $stone['coating_id'] = Coating::where('name', 'Stone protection')->value('id');
        $stone['material_id'] = Material::where('name', 'Marble')->value('id');

        $this->put(route('quotes.update', $quote), $this->payload([
            $this->sofaLine() + ['id' => $sofa->id],
            $stone + ['id' => $glass->id],
        ]))->assertSessionHasNoErrors();

        $lines = $quote->fresh()->lines;
        $this->assertSame(18.0, $lines[0]->coating_rate, 'unchanged line keeps its rate');
        $this->assertSame(28.0, $lines[1]->coating_rate, 'changed product uses today\'s rate');

        $this->put(route('quotes.update', $quote), $this->payload([$this->sofaLine() + ['id' => $lines[0]->id]], ['refresh_rates' => true]));
        $this->assertSame(30.0, $quote->fresh()->lines[0]->coating_rate);
    }

    #[Test]
    public function discount_over_sales_limit_needs_admin_approval(): void
    {
        $quote = $this->makeQuote([$this->sofaLine()], ['discount_type' => 'percent', 'discount_value' => 15]);
        $this->assertTrue($quote->awaitingApproval());

        $this->post(route('quotes.status', $quote), ['status' => 'sent'])->assertSessionHas('error');
        $this->post(route('admin.quotes.approve', $quote))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.quotes.approve', $quote));
        $this->actingAs($this->sales)->post(route('quotes.status', $quote), ['status' => 'sent']);
        $this->assertSame(Quote::SENT, $quote->fresh()->status);
    }

    #[Test]
    public function offline_payments_and_manual_conversion(): void
    {
        $q = $this->sentQuote([$this->sofaLine()]);
        $this->post(route('quotes.status', $q), ['status' => 'accepted']);

        // Auto-job happens on payment; turn it off to convert by hand.
        \App\Models\Setting::current()->update(['auto_job_on_payment' => false]);
        $this->post(route('payments.store', $q), ['amount' => 1000, 'mode' => 'upi', 'reference' => 'UTR123'])->assertSessionHas('ok');
        $this->assertSame(Quote::ACCEPTED, $q->fresh()->status);
        $payment = Payment::first();
        $this->assertSame(['advance', 'upi', 'UTR123', $this->sales->id], [$payment->purpose, $payment->gateway, $payment->reference, $payment->recorded_by]);

        $this->post(route('payments.store', $q), ['amount' => 99999, 'mode' => 'cash'])->assertSessionHas('error');

        $this->post(route('quotes.convert', $q), ['technician_id' => $this->tech->id]);
        $this->assertSame(Quote::CONVERTED, $q->fresh()->status);
        $this->post(route('invoices.store', $q))->assertSessionHas('ok');
        $this->post(route('payments.store', $q), ['amount' => $q->fresh()->balance(), 'mode' => 'cash']);

        $this->assertTrue($q->fresh()->isFullyPaid());
        $this->assertSame('balance', Payment::latest('id')->first()->purpose);
        $this->get(route('payments.receipt', $payment))->assertOk();
        $this->get(route('payments.index'))->assertOk()->assertSee('UTR123');
    }

    #[Test]
    public function sent_quotes_past_validity_expire_unless_paid(): void
    {
        $q = $this->sentQuote([$this->sofaLine()]);
        $paid = $this->sentQuote([$this->glassTopLine()]);
        $this->post(route('payments.store', $paid), ['amount' => 100, 'mode' => 'cash']);
        \App\Models\Setting::current()->update(['auto_job_on_payment' => false]);

        $this->travelTo($q->valid_till->copy()->addDay());
        $this->artisan('quotes:expire')->assertSuccessful();
        $this->assertSame(Quote::EXPIRED, $q->fresh()->status);
        $this->assertNotSame(Quote::EXPIRED, $paid->fresh()->status);
    }

    #[Test]
    public function whatsapp_share_sends_and_links_to_the_portal(): void
    {
        $q = $this->makeQuote([$this->sofaLine()]);
        $res = $this->get(route('quotes.whatsapp', $q));

        $this->assertStringStartsWith('https://wa.me/919822000000?text=', $res->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode(route('portal.quotes.show', $q)), $res->headers->get('Location'));
        $this->assertSame(Quote::SENT, $q->fresh()->status);

        auth()->logout();
        $this->get(\App\Services\QuoteWorkflow::publicPdfUrl($q->fresh()))->assertOk();
        $this->get(route('quotes.public-pdf', $q))->assertForbidden();
    }

    #[Test]
    public function new_customer_from_the_staff_builder_and_duplicate_phone_guard(): void
    {
        $this->actingAs($this->sales)->post(route('quotes.store'), ['payload' => json_encode([
            'new_customer' => ['name' => 'Walk-in', 'phone' => '+91 99999 88888'],
            'new_site' => ['address' => 'MG Road', 'city' => 'Bengaluru', 'state_code' => '29'],
            'lines' => [$this->sofaLine()],
        ])])->assertSessionHasNoErrors();
        $this->assertSame('9999988888', Quote::first()->customer->phone);

        $this->post(route('quotes.store'), ['payload' => json_encode([
            'new_customer' => ['name' => 'Dup', 'phone' => '9822000000'],
            'new_site' => ['address' => 'X', 'state_code' => '27'],
            'lines' => [$this->sofaLine()],
        ])])->assertSessionHasErrors('new_customer.phone');
    }
}
