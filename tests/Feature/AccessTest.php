<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\RequestService;
use PHPUnit\Framework\Attributes\Test;

class AccessTest extends FlowTestCase
{
    private function request(?User $tech = null): ServiceRequest
    {
        $sr = app(RequestService::class)->create($this->customer, ['site_id' => $this->puneSite->id, 'lines' => [$this->sofaLine()]]);
        if ($tech) {
            $sr->update(['technician_id' => $tech->id, 'status' => ServiceRequest::ASSIGNED, 'visit_at' => now()->addDay()]);
        }

        return $sr;
    }

    #[Test]
    public function staff_login_rejects_inactive_users(): void
    {
        $this->post('staff/login', ['email' => 's@x.test', 'password' => 'password'])->assertRedirect(route('dashboard'));
        auth()->logout();
        $this->sales->update(['active' => false]);
        $this->post('staff/login', ['email' => 's@x.test', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    #[Test]
    public function every_staff_page_renders_for_admin(): void
    {
        $sr = $this->request($this->tech);
        $q = $this->makeQuote([$this->sofaLine()]);
        $job = ServiceJob::create(['quote_id' => $q->id]);
        $this->actingAs($this->admin);

        foreach ([
            route('dashboard'), route('requests.index'), route('requests.show', $sr), route('quotes.index'),
            route('quotes.create'), route('quotes.create', ['request' => $sr->id]), route('quotes.show', $q), route('quotes.edit', $q),
            route('customers.index'), route('customers.create'), route('customers.show', $this->customer), route('customers.edit', $this->customer),
            route('customers.search', ['q' => '9822']), route('jobs.index'), route('jobs.show', $job), route('invoices.index'),
            route('payments.index'), route('reports'), route('admin.company.edit'), route('admin.settings.edit'),
            route('admin.users.index'), route('admin.users.create'), route('admin.audit'), route('admin.rates.index', 'coatings'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    #[Test]
    public function sales_cannot_reach_admin_pages(): void
    {
        $this->actingAs($this->sales);
        $this->get(route('admin.products.index'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();
    }

    #[Test]
    public function technicians_only_see_and_quote_their_own_visits(): void
    {
        $other = User::create(['name' => 'Other', 'email' => 'o@x.test', 'password' => 'password', 'role' => 'technician']);
        $mine = $this->request($this->tech);
        $theirs = $this->request($other);
        $unassigned = $this->request();

        $this->actingAs($this->tech);
        $this->get(route('requests.index'))->assertOk()->assertSee($mine->number)->assertDontSee($theirs->number)->assertDontSee($unassigned->number);
        $this->get(route('requests.show', $theirs))->assertForbidden();
        $this->get(route('quotes.create', ['request' => $theirs->id]))->assertForbidden();
        $this->get(route('quotes.create'))->assertForbidden();
        $this->post(route('requests.assign', $mine), ['technician_id' => $this->tech->id, 'visit_at' => now()])->assertForbidden();
        $this->get(route('customers.index'))->assertForbidden();
        $this->get(route('payments.index'))->assertForbidden();

        // Can't sneak a quote onto someone else's visit.
        $this->post(route('quotes.store'), $this->payload([$this->sofaLine()], ['service_request_id' => $theirs->id]))->assertForbidden();

        $salesQuote = $this->actingAs($this->sales)->post(route('quotes.store'), $this->payload([$this->sofaLine()]));
        $this->actingAs($this->tech)->get(route('quotes.show', Quote::latest('id')->first()))->assertForbidden();

        // Technician discount always needs approval.
        $this->post(route('quotes.store'), $this->payload([$this->sofaLine()], ['service_request_id' => $mine->id, 'discount_type' => 'flat', 'discount_value' => 50]));
        $q = Quote::where('service_request_id', $mine->id)->first();
        $this->assertTrue($q->awaitingApproval());
        $this->post(route('quotes.status', $q), ['status' => 'accepted'])->assertSessionHas('error');
    }

    #[Test]
    public function technicians_only_see_their_own_jobs(): void
    {
        $q1 = $this->makeQuote([$this->sofaLine()]);
        $q2 = $this->makeQuote([$this->sofaLine()]);
        $mine = ServiceJob::create(['quote_id' => $q1->id, 'technician_id' => $this->tech->id]);
        $theirs = ServiceJob::create(['quote_id' => $q2->id]);

        $this->actingAs($this->tech);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('jobs.show', $mine))->assertOk();
        $this->get(route('jobs.show', $theirs))->assertForbidden();
    }

    #[Test]
    public function customers_are_separate_from_staff(): void
    {
        $this->actingAs($this->customer, 'customer');
        $this->get(route('dashboard'))->assertRedirect(route('staff.login'));
        $this->get(route('portal.dashboard'))->assertOk();

        $sr = $this->request();
        $other = Customer::create(['name' => 'Other', 'phone' => '9000000002']);
        $this->actingAs($other, 'customer')->get(route('portal.requests.show', $sr))->assertNotFound();
    }

    #[Test]
    public function photos_need_a_signed_in_user(): void
    {
        $path = 'photos/test.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, 'x');

        $this->get(route('media.photo', 'test.jpg'))->assertRedirect();
        $this->actingAs($this->customer, 'customer')->get(route('media.photo', 'test.jpg'))->assertOk();
    }
}
