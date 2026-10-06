<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Coating;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Material;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TestGateway;
use App\Services\QuoteBuilder;
use App\Services\QuoteWorkflow;
use App\Services\RequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo customers at every stage of the flow, all created through the real
 * services (request → visit → quote → payment → job → invoice).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', User::ADMIN)->first();
        $tech = User::where('email', 'tech@example.com')->first();
        $tech2 = User::where('email', 'tech2@example.com')->first();
        $requests = app(RequestService::class);
        $workflow = app(QuoteWorkflow::class);
        $payments = app(PaymentService::class);

        // 1. New request, waiting for a technician (Pune).
        $anjali = $this->customer('Anjali Deshmukh', '9822011223', 'anjali@example.com',
            ['Home', 'Flat 701, Example Heights, Aundh', 'Pune', '411007', '27']);
        $requests->create($anjali, [
            'site_id' => $anjali->sites[0]->id,
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_slot' => 'morning',
            'notes' => 'Kids spill juice on the sofa a lot. Please call before coming.',
            'photos' => [$this->photo('Living room', [15, 118, 110])],
            'lines' => [
                $this->line('Sofa (build by parts)', ['seats' => 3, 'backs' => 2, 'arms' => 2, 'cushions' => 2],
                    ['Fabric protection', 'Velvet', 'Tufted / buttoned', 'Anti-microbial layer'], 'Living room sofa', [$this->photo('Velvet sofa', [91, 33, 182])]),
                $this->line('Glass table top', ['length' => 6, 'width' => 3, 'sides' => 1], ['Glass protection', 'Glass', 'Plain'], 'Coffee table'),
                $this->line('Dining chair', [], ['Fabric protection', 'Velvet', 'Plain'], 'Dining chairs', [], 6),
            ],
        ]);

        // 2. Visit scheduled with a technician (Bengaluru — inter-state, IGST).
        $karthik = $this->customer('Karthik Rao', '9845098450', 'karthik@example.com',
            ['Villa', '42 Example Layout, Whitefield', 'Bengaluru', '560066', '29']);
        $sr2 = $requests->create($karthik, [
            'site_id' => $karthik->sites[0]->id,
            'preferred_date' => now()->addDay()->toDateString(),
            'preferred_slot' => 'afternoon',
            'lines' => [
                $this->line('Recliner sofa (per seat)', ['seats' => 3], ['Leather protection', 'Leather', 'Recliner', 'UV protection layer'], 'Home theatre recliners'),
                $this->line('Countertop / stone table top', ['length' => 72, 'length__unit' => 'in', 'width' => 36, 'width__unit' => 'in'], ['Stone protection', 'Marble', 'Plain'], 'Marble dining top'),
            ],
        ]);
        $sr2->update(['status' => ServiceRequest::ASSIGNED, 'technician_id' => $tech->id, 'visit_at' => now()->addDay()->setTime(15, 0),
            'assigned_by' => $admin->id, 'assigned_at' => now()]);

        // 3. Visited and quoted: the customer can accept and pay online (Mumbai).
        $meera = $this->customer('Meera Shah', '9930099300', 'meera@example.com',
            ['Home', '12 Example Road, Bandra West', 'Mumbai', '400050', '27']);
        $q3 = $this->visitAndQuote($requests, $meera, $tech, [
            $this->line('Curved accent chair', ['size' => 16], ['Fabric protection', 'Velvet', 'Curved'], 'Wingback chairs', [], 2),
            $this->line('Wall coating', ['height' => 10, 'width' => 14, 'openings' => 21], ['Wall protection', 'Painted wall', 'Plain'], 'Kids room wall'),
            $this->line('Mattress', ['size' => 48, 'cover' => 1], ['Fabric protection', 'Polyester / blend', 'Plain', 'Anti-microbial layer'], 'Master bed'),
        ]);
        $workflow->send($q3);

        // 4. Accepted and advance paid → job scheduled (Pune).
        $rohan = $this->customer('Rohan Kulkarni', '9881098810', 'rohan@example.com',
            ['Office', '5th floor, Example Tech Park, Hinjewadi', 'Pune', '411057', '27']);
        $q4 = $this->visitAndQuote($requests, $rohan, $tech2, [
            $this->line('Office chair', [], ['Fabric protection', 'Polyester / blend', 'Plain'], 'Workstation chairs', [], 24),
            $this->line('Windows & glass partitions', ['height' => 7, 'width' => 4, 'panes' => 6, 'sides' => 2], ['Glass protection', 'Glass', 'Plain'], 'Cabin partitions'),
            $this->line('Glass edge sealing', ['length' => 66], [], 'Partition edges'),
        ]);
        $workflow->send($q4);
        $this->pay($payments, $q4, 'advance');
        $q4->refresh()->serviceJob?->update(['scheduled_on' => now()->addDays(3)->toDateString()]);

        // 5. Work done, invoiced, balance due (Thane).
        $priya = $this->customer('Priya Nair', '9769097690', 'priya@example.com',
            ['Home', 'B-1203, Example Greens, Ghodbunder Road', 'Thane', '400607', '27']);
        $q5 = $this->visitAndQuote($requests, $priya, $tech, [
            $this->line('Sofa (build by parts)', ['seats' => 5, 'backs' => 5, 'arms' => 2, 'chaise' => 1], ['Leather protection', 'Faux leather', 'Plain'], 'L-shaped sectional'),
            $this->line('Rug / carpet', ['length' => 8, 'width' => 5], ['Fabric protection', 'Polyester / blend', 'Plain'], 'Living room rug'),
        ]);
        $workflow->send($q5);
        $this->pay($payments, $q5, 'advance');
        $job = $q5->refresh()->serviceJob;
        $job->update(['status' => 'done', 'scheduled_on' => now()->subDays(2)->toDateString(), 'completed_at' => now()->subDay()]);
        $workflow->jobCompleted($job->fresh());
    }

    private function customer(string $name, string $phone, string $email, array $site): Customer
    {
        $c = Customer::create(['name' => $name, 'phone' => $phone, 'email' => $email, 'phone_verified_at' => now()]);
        [$label, $address, $city, $pin, $state] = $site;
        $c->sites()->create(['label' => $label, 'address' => $address, 'city' => $city, 'pincode' => $pin, 'state_code' => $state]);

        return $c->load('sites');
    }

    private function line(string $product, array $inputs, array $sel, string $name, array $photos = [], int $qty = 1): array
    {
        [$coating, $material, $design, $addon] = array_pad($sel, 4, null);

        return [
            'product_id' => Product::where('name', $product)->value('id'),
            'name' => $name,
            'qty' => $qty,
            'inputs' => $inputs,
            'coating_id' => $coating ? Coating::where('name', $coating)->value('id') : null,
            'material_id' => $material ? Material::where('name', $material)->value('id') : null,
            'design_id' => $design ? Design::where('name', $design)->value('id') : null,
            'addon_id' => $addon ? Addon::where('name', $addon)->value('id') : null,
            'photos' => $photos,
        ];
    }

    /** Customer request → technician visit → quote from the request. */
    private function visitAndQuote(RequestService $requests, Customer $c, User $tech, array $lines)
    {
        $sr = $requests->create($c, ['site_id' => $c->sites[0]->id, 'lines' => $lines, 'preferred_slot' => 'morning']);
        $sr->update(['status' => ServiceRequest::ASSIGNED, 'technician_id' => $tech->id, 'visit_at' => now()->subDay()->setTime(11, 0),
            'assigned_by' => User::where('role', User::ADMIN)->value('id'), 'assigned_at' => now()->subDays(2)]);

        return (new QuoteBuilder)->save([
            'customer_id' => $c->id,
            'site_id' => $sr->site_id,
            'service_request_id' => $sr->id,
            'visit_charge' => 300,
            'notes' => 'Measured on site by '.$tech->name.'.',
            'lines' => QuoteBuilder::linesFromRequest($sr->load('items')),
        ], $tech);
    }

    private function pay(PaymentService $payments, $quote, string $purpose): void
    {
        $payment = $payments->start($quote->refresh(), $purpose);
        $paymentId = 'pay_test_'.Str::random(14);
        $payments->verifyAndComplete($payment, $paymentId, (new TestGateway)->sign($payment->gateway_order_id, $paymentId), 'upi');
    }

    /** A placeholder "site photo" so the demo has images to show. */
    private function photo(string $label, array $rgb): string
    {
        $img = imagecreatetruecolor(800, 600);
        for ($y = 0; $y < 600; $y++) {
            $k = $y / 600;
            $c = imagecolorallocate($img, (int) ($rgb[0] * (1 - $k * 0.5)), (int) ($rgb[1] * (1 - $k * 0.5)), (int) ($rgb[2] * (1 - $k * 0.5)));
            imageline($img, 0, $y, 800, $y, $c);
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 160, 330, 640, 420, imagecolorallocatealpha($img, 0, 0, 0, 80));
        imagestring($img, 5, 400 - strlen('DEMO PHOTO: '.$label) * 4.5, 368, 'DEMO PHOTO: '.$label, $white);

        ob_start();
        imagejpeg($img, null, 80);
        $path = 'photos/demo-'.Str::slug($label).'-'.Str::random(6).'.jpg';
        Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($img);

        return $path;
    }
}
