<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\DocumentNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        Quote::expireOverdue();

        // Default range: current financial year.
        $fyStart = Carbon::create((int) substr(DocumentNumber::financialYear(now()), 0, 4), 4, 1);
        $from = Carbon::parse($request->query('from', $fyStart->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        // Latest revision of each quote only.
        $base = fn () => Quote::current()->whereBetween('date', [$from, $to]);

        $byStatus = $base()->selectRaw('status, count(*) as n, sum(total) as value')->groupBy('status')->get()->toBase()->keyBy('status');

        $decided = $byStatus->only([Quote::SENT, Quote::ACCEPTED, Quote::REJECTED, Quote::EXPIRED, Quote::CONVERTED, Quote::INVOICED])->sum('n');
        $won = $byStatus->only(Quote::WON);
        $wonCount = $won->sum('n');

        $nonDraft = $base()->where('status', '!=', Quote::DRAFT);
        $avg = (clone $nonDraft)->avg('total');

        $wonIds = $base()->whereIn('status', Quote::WON)->pluck('id');

        // Line values before discount / GST.
        $byCoating = QuoteLine::whereIn('quote_id', $wonIds)
            ->selectRaw("coalesce(coating_name, 'Running-feet work') as label, sum(amount) as value, count(*) as n")
            ->groupBy('label')->orderByDesc('value')->get();

        $quoteIds = $base()->pluck('id');
        $byService = QuoteLine::whereIn('quote_lines.quote_id', $quoteIds)
            ->leftJoin('products', 'products.id', '=', 'quote_lines.product_id')
            ->leftJoin('services', 'services.id', '=', 'products.service_id')
            ->selectRaw("coalesce(services.name, 'Other') as label, count(*) as n, sum(quote_lines.amount) as value")
            ->groupBy('label')->orderByDesc('n')->get();
        $topItems = QuoteLine::whereIn('quote_id', $quoteIds)
            ->selectRaw('product_name as label, count(*) as n, sum(amount) as value')
            ->groupBy('label')->orderByDesc('n')->limit(10)->get();

        return view('staff.reports.index', [
            'from' => $from,
            'to' => $to,
            'byStatus' => $byStatus,
            'quoteCount' => $byStatus->sum('n'),
            'conversion' => $decided > 0 ? $wonCount / $decided * 100 : null,
            'wonCount' => $wonCount,
            'decided' => $decided,
            'wonValue' => $won->sum('value'),
            'average' => $avg,
            'byCoating' => $byCoating,
            'byService' => $byService,
            'topItems' => $topItems,
        ]);
    }
}
