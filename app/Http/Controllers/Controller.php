<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Back to the page that asked for a login, but only if it belongs to
     * this part of the site: a staff login never lands on a customer page.
     */
    protected function intendedWithin(string $prefix, string $default): RedirectResponse
    {
        $url = session()->pull('url.intended');
        $base = rtrim(url($prefix), '/');

        return redirect(is_string($url) && ($url === $base || str_starts_with($url, $base.'/') || str_starts_with($url, $base.'?')) ? $url : $default);
    }
}
