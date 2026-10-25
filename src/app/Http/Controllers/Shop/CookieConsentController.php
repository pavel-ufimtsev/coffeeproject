<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Save the cookie banner's answer. The banner script posts here in the
 * background; without JavaScript the form posts and comes back to the page.
 */
class CookieConsentController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $request->validate(['choice' => ['required', Rule::in([CookieConsent::ALL, CookieConsent::NECESSARY])]]);

        $cookie = CookieConsent::cookie($request->string('choice')->value());

        if ($request->expectsJson()) {
            return response()->noContent()->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }
}
