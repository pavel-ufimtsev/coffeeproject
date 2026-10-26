<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\WholesaleRequest;
use App\Mail\ContactMessage;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Coffee for coffee shops, restaurants and offices: the page and its
 * request form, which reaches the shop like a contact message.
 */
class WholesaleController extends Controller
{
    public function show(): View
    {
        return view('shop.wholesale', [
            'businesses' => WholesaleRequest::BUSINESSES,
            'volumes' => WholesaleRequest::VOLUMES,
        ]);
    }

    public function store(WholesaleRequest $request): JsonResponse|RedirectResponse
    {
        Mail::to(config('shop.contact_email'))->queue(new ContactMessage([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => Phone::format($request->input('phone')),
            'topic' => 'wholesale',
            'message' => $request->input('message'),
            'details' => array_filter([
                __('Company') => $request->input('company'),
                __('City') => $request->input('city'),
                __('Business') => __(WholesaleRequest::BUSINESSES[$request->input('business')]),
                __('Volume') => __(WholesaleRequest::VOLUMES[$request->input('volume')]),
            ]),
        ]));

        $thanks = __('Thank you, :name! We will get back to you with prices and coffee suggestions soon.', ['name' => $request->input('name')]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $thanks]);
        }

        return to_route('wholesale')->with('contact_sent', $thanks)->withFragment('request');
    }
}
