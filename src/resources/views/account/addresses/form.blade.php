<x-layouts.account :title="$address ? __('Edit address') : __('New address')">
    <section class="card">
        <form method="POST" action="{{ $address ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="stack">
            @csrf
            @if ($address)
                @method('PUT')
            @endif

            <x-form.input name="label" :label="__('Address name')" :value="$address?->label" required maxlength="50" :hint="__('For example Home or Work.')" />
            <x-form.input name="city" :label="__('City')" :value="$address?->city ?? config('shop.courier_city')" required maxlength="100" autocomplete="address-level2"
                :hint="__('Our courier delivers in :city; CDEK delivery across Russia is coming soon.', ['city' => config('shop.courier_city')])" />
            <x-form.input name="street" :label="__('Street')" :value="$address?->street" required maxlength="255" autocomplete="address-line1" />
            <div class="form-row">
                <x-form.input name="house" :label="__('House')" :value="$address?->house" required maxlength="30" />
                <x-form.input name="apartment" :label="__('Apt. / office')" :value="$address?->apartment" maxlength="30" autocomplete="address-line2" />
            </div>
            <div class="form-row">
                <x-form.input name="entrance" :label="__('Entrance')" :value="$address?->entrance" maxlength="10" />
                <x-form.input name="floor" :label="__('Floor')" :value="$address?->floor" maxlength="10" />
                <x-form.input name="intercom" :label="__('Intercom')" :value="$address?->intercom" maxlength="30" />
            </div>
            <x-form.input name="courier_note" :label="__('Note for the courier')" :value="$address?->courier_note" maxlength="500" />

            <div class="form-actions">
                <button type="submit" class="button">{{ __('Save') }}</button>
                <a href="{{ route('account.addresses.index') }}" class="button button--secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</x-layouts.account>
