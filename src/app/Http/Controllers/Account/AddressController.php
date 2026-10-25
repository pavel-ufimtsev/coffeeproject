<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\AddressRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The customer's address book. Checkout reads and updates the same addresses.
 */
class AddressController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.addresses.index', [
            'addresses' => $request->user()->addresses()->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->addresses()->count() >= Address::MAX_PER_USER) {
            return to_route('account.addresses.index')->with('status', __('You can save up to :count addresses. Remove one in your account first.', ['count' => Address::MAX_PER_USER]));
        }

        return view('account.addresses.form', ['address' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->addresses()->count() >= Address::MAX_PER_USER, 422);

        $request->user()->addresses()->create($this->validated($request));

        return to_route('account.addresses.index')->with('status', __('Address saved.'));
    }

    public function edit(Request $request, Address $address): View
    {
        $this->authorizeOwner($request, $address);

        return view('account.addresses.form', ['address' => $address]);
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        $address->update($this->validated($request, $address));

        return to_route('account.addresses.index')->with('status', __('Address saved.'));
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        $address->makeDefault();

        return to_route('account.addresses.index')->with('status', __('“:label” is now your default address.', ['label' => $address->label]));
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);

        $address->delete();

        return to_route('account.addresses.index')->with('status', __('Address removed.'));
    }

    /**
     * Someone else's address is "not found", not "forbidden".
     */
    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Address $address = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:50', Rule::unique('addresses')->where('user_id', $request->user()->id)->ignore($address)],
            ...AddressRules::rules(),
        ], ['label.unique' => __('You already have an address with this name.')], AddressRules::attributes());
    }
}
