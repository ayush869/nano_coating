<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OtpCode;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Notifications\NewServiceRequest;
use App\Notifications\PaymentReceived;
use App\Notifications\QuoteReady;
use App\Notifications\RequestAssigned;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;

/**
 * The whole customer journey: OTP sign-in → request with photos → admin
 * assigns a technician → technician quotes from the request → customer
 * accepts and pays the advance → job → done → invoice → balance paid.
 */
class CustomerJourneyTest extends FlowTestCase
{
    private function loginWithOtp(string $phone): Customer
    {
        $this->post(route('customer.login.send'), ['phone' => '+91 '.$phone])->assertRedirect(route('customer.login.verify'));
        $code = session('otp_dev');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->post('login/verify', ['code' => $code]);

        return Customer::where('phone', $phone)->first();
    }

    #[Test]
    public function otp_login_creates_an_account_and_rejects_wrong_codes(): void
    {
        config(['sms.show_otp' => true]);

        $this->post(route('customer.login.send'), ['phone' => '12345'])->assertSessionHasErrors('phone');

        $this->post(route('customer.login.send'), ['phone' => '9876501234']);
        $this->post('login/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame(1, OtpCode::first()->attempts);

        $this->post('login/verify', ['code' => session('otp_dev') ?? '0'])->assertSessionHasErrors('code'); // flash is gone; wrong code again

        OtpCode::query()->delete();
        $this->travel(2)->minutes();
        $customer = $this->loginWithOtp('9876501234');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertSame('', $customer->name);

        $this->get(route('portal.dashboard'))->assertRedirect(route('portal.welcome'));
        $this->put(route('portal.profile.update'), ['name' => 'New Customer', 'email' => 'new@x.test'])->assertRedirect();
        $this->get(route('portal.dashboard'))->assertOk()->assertSee('Hello, New');
    }

    #[Test]
    public function otp_codes_expire_and_lock_after_too_many_attempts(): void
    {
        config(['sms.show_otp' => true]);
        $this->post(route('customer.login.send'), ['phone' => '9876501234']);
        $code = session('otp_dev');

        for ($i = 0; $i < 5; $i++) {
            $this->post('login/verify', ['code' => '111111']);
        }
        $this->post('login/verify', ['code' => $code])->assertSessionHasErrors(['code' => 'Too many wrong attempts. Ask for a new code.']);
        $this->assertGuest('customer');
    }

    #[Test]
    public function full_journey_from_website_request_to_fully_paid_invoice(): void
    {
        config(['sms.show_otp' => true]);

        // --- Customer: sign in, upload a photo, request a visit.
        $customer = $this->loginWithOtp('9822000000'); // existing customer with two sites
        $this->assertTrue($customer->is($this->customer));

        $photo = $this->postJson(route('photos.store'), ['photo' => UploadedFile::fake()->image('sofa.jpg', 2400, 1800)])
            ->assertOk()->json();
        $this->assertStringStartsWith('photos/', $photo['path']);

        $this->get(route('portal.requests.create', ['product' => Product::where('name', 'Sofa (build by parts)')->value('id')]))
            ->assertOk()->assertSee('Get your estimate');

        $this->post(route('portal.requests.store'), ['payload' => json_encode([
            'site_id' => $this->puneSite->id,
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_slot' => 'evening',
            'notes' => 'Please call first',
            'lines' => [$this->sofaLine() + ['photos' => [$photo['path']]], $this->glassTopLine()],
        ])])->assertSessionHasNoErrors();

        $sr = ServiceRequest::with('items')->first();
        $this->assertSame(ServiceRequest::NEW, $sr->status);
        $this->assertSame([1422.0, 500.0], $sr->items->pluck('estimate_amount')->all());
        $this->assertSame(2950.0, $sr->estimate_total); // 1922 + 300 visit → raised to 2500 + 18%
        $this->assertSame([$photo['path']], $sr->items[0]->photos);
        Notification::assertSentTo($this->admin, NewServiceRequest::class);

        // --- Admin assigns the technician.
        $this->actingAs($this->admin)->post(route('requests.assign', $sr), [
            'technician_id' => $this->tech->id, 'visit_at' => now()->addDays(2)->setTime(17, 0)->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(ServiceRequest::ASSIGNED, $sr->fresh()->status);
        Notification::assertSentTo($this->tech, RequestAssigned::class);
        Notification::assertSentTo($customer, RequestAssigned::class);

        // --- Technician measures and quotes from the request (sofa turned out to have a 3-seat back).
        $this->actingAs($this->tech)->get(route('dashboard'))->assertOk()->assertSee($customer->name);
        $this->get(route('quotes.create', ['request' => $sr->id]))->assertOk()->assertSee('prefilled');

        $lines = \App\Services\QuoteBuilder::linesFromRequest($sr);
        $lines[0]['inputs']['backs'] = 3;
        $this->post(route('quotes.store'), ['payload' => json_encode([
            'service_request_id' => $sr->id, 'site_id' => $sr->site_id, 'visit_charge' => 300, 'lines' => $lines,
            'customer_id' => 999999, // ignored for technicians
        ])])->assertSessionHasNoErrors();
        $quote = Quote::first();
        $this->assertSame($sr->id, $quote->service_request_id);
        $this->assertSame($customer->id, $quote->customer_id);
        $this->assertSame(1602.0, $quote->lines[0]->amount);
        $this->assertSame([$photo['path']], $quote->lines[0]->photos);

        $this->post(route('quotes.status', $quote), ['status' => 'sent'])->assertSessionHas('ok');
        $this->assertSame(ServiceRequest::QUOTED, $sr->fresh()->status);
        Notification::assertSentTo($customer, QuoteReady::class);

        // --- Customer reviews, accepts and pays the 50% advance through the test gateway.
        $this->actingAs($customer, 'customer');
        $this->get(route('portal.quotes.show', $quote))->assertOk()->assertSee('Pay advance (50%)');
        $this->assertNotNull($quote->fresh()->viewed_at);

        $start = $this->postJson(route('portal.payments.start', $quote), ['purpose' => 'advance'])->assertOk()->json();
        $this->assertSame('test', $start['gateway']);
        $payment = Payment::first();
        $total = $quote->fresh()->total;
        $this->assertSame(round($total / 2, 2), $payment->amount);

        $this->get($start['redirect'])->assertOk()->assertSee('Simulate successful payment');
        $this->post($start['redirect'], ['outcome' => 'success', 'method' => 'upi'])->assertRedirect(route('portal.quotes.show', $quote));

        $payment->refresh();
        $quote->refresh();
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertMatchesRegularExpression('#^RCPT/\d{4}-\d{2}/0001$#', $payment->number);
        $this->assertSame(Quote::CONVERTED, $quote->status, 'paying accepts the quote and creates the job');
        $this->assertSame($payment->amount, $quote->amount_paid);
        $job = ServiceJob::first();
        $this->assertSame($this->tech->id, $job->technician_id, 'the visiting technician gets the job');
        Notification::assertSentTo($customer, PaymentReceived::class);
        $this->get(route('portal.payments.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');

        // --- Technician completes the job → invoice is issued automatically.
        $this->actingAs($this->tech)->put(route('jobs.update', $job), ['status' => 'done'])->assertSessionHas('ok');
        $quote->refresh();
        $this->assertSame(Quote::INVOICED, $quote->status);
        $this->assertSame(ServiceRequest::CLOSED, $sr->fresh()->status);
        $invoice = Invoice::first();
        $this->assertNotNull($invoice);

        // --- Customer pays the balance.
        $this->actingAs($customer, 'customer');
        $this->get(route('portal.quotes.show', $quote))->assertOk()->assertSee('Pay balance')->assertSee($invoice->number);
        $start = $this->postJson(route('portal.payments.start', $quote), ['purpose' => 'balance'])->json();
        $this->post($start['redirect'], ['outcome' => 'success']);

        $quote->refresh();
        $this->assertTrue($quote->isFullyPaid());
        $this->assertSame(0.0, $quote->balance());
        $this->postJson(route('portal.payments.start', $quote), ['purpose' => 'balance'])->assertStatus(422);
        $this->get(route('portal.invoices.pdf', $invoice))->assertOk();
    }

    #[Test]
    public function failed_test_payment_changes_nothing(): void
    {
        $quote = $this->sentQuote([$this->sofaLine()]);
        $start = $this->actingAs($this->customer, 'customer')->postJson(route('portal.payments.start', $quote), ['purpose' => 'full'])->json();

        $this->post($start['redirect'], ['outcome' => 'failure'])->assertSessionHas('error');
        $this->assertSame(Payment::FAILED, Payment::first()->status);
        $this->assertSame(Quote::SENT, $quote->fresh()->status);
        $this->assertSame(0.0, $quote->fresh()->amount_paid);
    }

    #[Test]
    public function customer_can_accept_or_reject_and_cannot_see_others_or_drafts(): void
    {
        $draft = $this->makeQuote([$this->sofaLine()]);
        $sent = $this->sentQuote([$this->sofaLine()]);
        $other = Customer::create(['name' => 'Other', 'phone' => '9000000001']);

        $this->actingAs($other, 'customer')->get(route('portal.quotes.show', $sent))->assertNotFound();
        $this->actingAs($this->customer, 'customer')->get(route('portal.quotes.show', $draft))->assertNotFound();

        $this->post(route('portal.quotes.accept', $sent))->assertSessionHasErrors('agree');
        $this->post(route('portal.quotes.accept', $sent), ['agree' => 1])->assertSessionHas('ok');
        $this->assertSame([Quote::ACCEPTED, 'customer'], [$sent->fresh()->status, $sent->fresh()->decided_by]);

        $third = $this->sentQuote([$this->glassTopLine()]);
        $this->actingAs($this->customer, 'customer')->post(route('portal.quotes.reject', $third), ['reason' => 'Too expensive']);
        $this->assertSame([Quote::REJECTED, 'Too expensive'], [$third->fresh()->status, $third->fresh()->rejection_reason]);
    }

    #[Test]
    public function public_pages_render(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('Sofa &amp; upholstery', false);
        $service = \App\Models\Service::where('slug', 'walls')->first();
        $this->get(route('site.service', $service))->assertOk()->assertSee('Wall coating')->assertSee('Typically');
        $this->get(route('customer.login'))->assertOk();
        $this->get(route('portal.dashboard'))->assertRedirect(route('customer.login'));
        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
    }
}
