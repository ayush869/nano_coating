<?php

use App\Models\Quote;
use App\Services\PdfRenderer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

Artisan::command('quotes:expire', function () {
    $this->info(Quote::expireOverdue().' quote(s) marked expired.');
})->purpose('Mark sent quotes past their validity date as expired');

Artisan::command('quotes:sample-pdf {number? : Quote number, defaults to the first quote} {--out=docs/sample-quotation.pdf}', function () {
    $quote = $this->argument('number')
        ? Quote::where('number', $this->argument('number'))->latest('revision')->firstOrFail()
        : Quote::orderBy('id')->firstOrFail();

    $out = base_path($this->option('out'));
    File::ensureDirectoryExists(dirname($out));
    File::put($out, (new PdfRenderer)->quote($quote)->output());

    $this->info("Wrote {$quote->display_number} to {$this->option('out')}");
})->purpose('Render a quotation PDF from the database (used for the sample in docs/)');

Schedule::command('quotes:expire')->dailyAt('00:10');
