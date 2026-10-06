<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Portal;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\Staff;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('services/{service:slug}', [SiteController::class, 'service'])->name('site.service');
Route::get('media/{dir}/{file}', [MediaController::class, 'public'])->name('media.public')
    ->where(['dir' => 'products|services|logo', 'file' => '[A-Za-z0-9._-]+']);
Route::get('share/quotes/{quote}', [Staff\QuoteShareController::class, 'pdf'])->name('quotes.public-pdf')->middleware('signed');
Route::post('webhooks/razorpay', [Portal\PaymentController::class, 'webhook'])->name('webhooks.razorpay');

// Site photos: any signed-in staff member or customer.
Route::middleware('auth:web,customer')->group(function () {
    Route::get('photos/{file}', [MediaController::class, 'photo'])->name('media.photo')->where('file', '[A-Za-z0-9._-]+');
    Route::post('photos', [MediaController::class, 'upload'])->name('photos.store')->middleware('throttle:60,1');
});

/*
|--------------------------------------------------------------------------
| Customer portal (OTP sign-in)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:customer')->group(function () {
    Route::get('login', [Portal\AuthController::class, 'show'])->name('customer.login');
    Route::post('login', [Portal\AuthController::class, 'sendCode'])->middleware('throttle:10,1')->name('customer.login.send');
    Route::get('login/verify', [Portal\AuthController::class, 'showVerify'])->name('customer.login.verify');
    Route::post('login/verify', [Portal\AuthController::class, 'verify'])->middleware('throttle:10,1');
});

Route::middleware('auth:customer')->prefix('account')->name('portal.')->group(function () {
    Route::post('logout', [Portal\AuthController::class, 'logout'])->name('logout');
    Route::get('welcome', [Portal\AuthController::class, 'showProfile'])->name('welcome');
    Route::get('/', Portal\DashboardController::class)->name('dashboard');
    Route::get('profile', [Portal\AuthController::class, 'showProfile'])->name('profile');
    Route::put('profile', [Portal\AuthController::class, 'updateProfile'])->name('profile.update');

    Route::get('request', [Portal\RequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [Portal\RequestController::class, 'store'])->name('requests.store')->middleware('throttle:10,1');
    Route::get('requests/{serviceRequest}', [Portal\RequestController::class, 'show'])->name('requests.show');
    Route::post('requests/{serviceRequest}/cancel', [Portal\RequestController::class, 'cancel'])->name('requests.cancel');

    Route::get('quotes/{quote}', [Portal\QuoteController::class, 'show'])->name('quotes.show');
    Route::get('quotes/{quote}/pdf', [Portal\QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::post('quotes/{quote}/accept', [Portal\QuoteController::class, 'accept'])->name('quotes.accept');
    Route::post('quotes/{quote}/reject', [Portal\QuoteController::class, 'reject'])->name('quotes.reject');
    Route::get('invoices/{invoice}/pdf', [Portal\QuoteController::class, 'invoice'])->name('invoices.pdf');

    Route::post('quotes/{quote}/pay', [Portal\PaymentController::class, 'start'])->name('payments.start')->middleware('throttle:20,1');
    Route::post('payments/{payment}/verify', [Portal\PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('payments/{payment}/failed', [Portal\PaymentController::class, 'failed'])->name('payments.failed');
    Route::get('payments/{payment}/test-checkout', [Portal\PaymentController::class, 'testCheckout'])->name('payments.test');
    Route::post('payments/{payment}/test-checkout', [Portal\PaymentController::class, 'testComplete']);
    Route::get('payments/{payment}/receipt', [Portal\PaymentController::class, 'receipt'])->name('payments.receipt');
});

/*
|--------------------------------------------------------------------------
| Staff panel
|--------------------------------------------------------------------------
*/
Route::prefix('staff')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [Staff\AuthController::class, 'show'])->name('staff.login');
        Route::post('login', [Staff\AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth:web')->group(function () {
        Route::post('logout', [Staff\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Staff\DashboardController::class)->name('dashboard');

        // Visits (service requests): technicians see the ones assigned to them.
        Route::get('requests', [Staff\ServiceRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/{serviceRequest}', [Staff\ServiceRequestController::class, 'show'])->name('requests.show');
        Route::post('requests/{serviceRequest}/assign', [Staff\ServiceRequestController::class, 'assign'])->name('requests.assign')->middleware('role:admin,sales');
        Route::post('requests/{serviceRequest}/cancel', [Staff\ServiceRequestController::class, 'cancel'])->name('requests.cancel')->middleware('role:admin,sales');
        Route::put('requests/{serviceRequest}/notes', [Staff\ServiceRequestController::class, 'notes'])->name('requests.notes');

        // Quotes: technicians can quote their own visits (checked in the controller).
        Route::resource('quotes', Staff\QuoteController::class)->except('destroy');
        Route::get('quotes/{quote}/pdf', [Staff\QuoteShareController::class, 'download'])->name('quotes.pdf');
        Route::post('quotes/{quote}/status', [Staff\QuoteController::class, 'status'])->name('quotes.status');
        Route::post('quotes/{quote}/revise', [Staff\QuoteController::class, 'revise'])->name('quotes.revise');
        Route::get('quotes/{quote}/whatsapp', [Staff\QuoteShareController::class, 'whatsapp'])->name('quotes.whatsapp');
        Route::post('quotes/{quote}/email', [Staff\QuoteShareController::class, 'email'])->name('quotes.email');

        Route::get('jobs', [Staff\ServiceJobController::class, 'index'])->name('jobs.index');
        Route::get('jobs/{job}', [Staff\ServiceJobController::class, 'show'])->name('jobs.show');
        Route::put('jobs/{job}', [Staff\ServiceJobController::class, 'update'])->name('jobs.update');

        Route::middleware('role:admin,sales')->group(function () {
            Route::get('customers/search', [Staff\CustomerController::class, 'search'])->name('customers.search');
            Route::resource('customers', Staff\CustomerController::class)->except('destroy');
            Route::post('customers/{customer}/sites', [Staff\CustomerController::class, 'storeSite'])->name('customers.sites.store');
            Route::put('sites/{site}', [Staff\CustomerController::class, 'updateSite'])->name('sites.update');

            Route::post('quotes/{quote}/convert', [Staff\QuoteController::class, 'convert'])->name('quotes.convert');
            Route::post('quotes/{quote}/payments', [Staff\PaymentController::class, 'store'])->name('payments.store');
            Route::get('payments', [Staff\PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}/receipt', [Staff\PaymentController::class, 'receipt'])->name('payments.receipt');

            Route::get('invoices', [Staff\InvoiceController::class, 'index'])->name('invoices.index');
            Route::post('quotes/{quote}/invoice', [Staff\InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/{invoice}/pdf', [Staff\InvoiceController::class, 'pdf'])->name('invoices.pdf');

            Route::get('reports', Staff\ReportController::class)->name('reports');
        });

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::post('quotes/{quote}/approve', [Staff\QuoteController::class, 'approve'])->name('quotes.approve');

            Route::resource('services', Admin\ServiceController::class)->except(['show', 'destroy']);
            Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
            Route::post('products/{product}/duplicate', [Admin\ProductController::class, 'duplicate'])->name('products.duplicate');

            Route::get('rates/{table}', [Admin\RateCardController::class, 'index'])->name('rates.index');
            Route::post('rates/{table}', [Admin\RateCardController::class, 'store'])->name('rates.store');
            Route::put('rates/{table}/{id}', [Admin\RateCardController::class, 'update'])->name('rates.update');
            Route::put('combinations', [Admin\RateCardController::class, 'combinations'])->name('rates.combinations');

            Route::get('company', [Admin\CompanyController::class, 'edit'])->name('company.edit');
            Route::put('company', [Admin\CompanyController::class, 'update'])->name('company.update');
            Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');

            Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);
            Route::get('audit', Admin\AuditLogController::class)->name('audit');
        });
    });
});
