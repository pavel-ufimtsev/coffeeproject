<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ContactRequest;
use App\Mail\ContactMessage;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The contacts page and its form. The form script posts in the background
 * and shows the thank-you in place; without JavaScript it posts and comes
 * back to the page.
 */
class ContactController extends Controller
{
    public function show(): View
    {
        return view('shop.contacts', ['topics' => ContactRequest::TOPICS]);
    }

    public function store(ContactRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'topic', 'message']);
        $data['phone'] = $request->filled('phone') ? Phone::format($request->input('phone')) : null;

        Mail::to(config('shop.contact_email'))->queue(new ContactMessage($data));

        $thanks = __('Thank you, :name! We have your message and will reply to :email soon.', [
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $thanks]);
        }

        return to_route('contacts')->with('contact_sent', $thanks);
    }
}
