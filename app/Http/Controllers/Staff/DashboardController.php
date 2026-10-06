<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        Quote::expireOverdue();

        if ($user->role === User::TECHNICIAN) {
            return view('staff.dashboard-technician', [
                'visits' => ServiceRequest::with('customer', 'site')->where('technician_id', $user->id)
                    ->where('status', ServiceRequest::ASSIGNED)->orderBy('visit_at')->get(),
                'jobs' => ServiceJob::with('quote')->where('technician_id', $user->id)->where('status', '!=', 'done')
                    ->orderByRaw('scheduled_on is null')->orderBy('scheduled_on')->get(),
                'drafts' => Quote::where('created_by', $user->id)->where('status', Quote::DRAFT)->latest('id')->get(),
            ]);
        }

        $counts = Quote::current()->selectRaw('status, count(*) as n, sum(total) as value')
            ->groupBy('status')->get()->toBase()->keyBy('status');

        return view('staff.dashboard', [
            'counts' => $counts,
            'newRequests' => ServiceRequest::with('customer', 'site')->where('status', ServiceRequest::NEW)->latest('id')->get(),
            'recent' => Quote::current()->latest('id')->limit(8)->get(),
            'awaitingApproval' => Quote::where('needs_approval', true)->whereNull('approved_by')->where('status', Quote::DRAFT)->get(),
            'upcomingJobs' => ServiceJob::with('quote', 'technician')->where('status', '!=', 'done')
                ->orderByRaw('scheduled_on is null')->orderBy('scheduled_on')->limit(6)->get(),
            'collected' => Payment::paid()->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'outstanding' => Quote::whereIn('status', [Quote::CONVERTED, Quote::INVOICED])->selectRaw('sum(total - amount_paid) as due')->value('due') ?? 0,
        ]);
    }
}
