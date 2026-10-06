<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $customer = $request->user('customer');
        if ($customer->name === '') {
            return redirect()->route('portal.welcome');
        }

        return view('portal.dashboard', [
            'customer' => $customer,
            'requests' => $customer->serviceRequests()->with('latestQuote', 'technician')->latest('id')->get(),
            'quotes' => $customer->quotes()->whereIn('status', Quote::CUSTOMER_VISIBLE)->with('invoice', 'serviceJob')->latest('id')->get(),
            'payments' => $customer->payments()->where('status', 'paid')->with('quote')->latest('paid_at')->get(),
        ]);
    }
}
