<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountDeleteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Show the customer's account overview.
     */
    public function index(Request $request): View
    {
        return view('account.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Show the customer's account settings.
     */
    public function edit(Request $request): View
    {
        return view('account.settings', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Delete the customer's account.
     */
    public function destroy(AccountDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', __('Your account has been deleted.'));
    }
}
