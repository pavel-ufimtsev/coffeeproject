@php use App\Models\Address; @endphp
<x-layouts.account :title="__('Addresses')">
    <p class="muted">{{ __('Saved addresses are offered at checkout. The default one is filled in for you.') }}</p>

    @if ($addresses->isEmpty())
        <section class="card">
            <p class="muted">{{ __('No saved addresses yet. The address of your first order is saved here automatically.') }}</p>
        </section>
    @else
        <ul class="address-list">
            @foreach ($addresses as $address)
                <li class="card address-card">
                    <div class="address-card__head">
                        <h2>{{ $address->label }}</h2>
                        @if ($address->is_default)
                            <span class="address-card__badge">{{ __('Default') }}</span>
                        @endif
                    </div>
                    <p>
                        {{ $address->addressLine() }}
                        @if ($address->accessLine())<br><span class="muted">{{ $address->accessLine() }}</span>@endif
                        @if ($address->courier_note)<br><span class="muted">{{ $address->courier_note }}</span>@endif
                    </p>
                    <div class="address-card__actions">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="button button--secondary button--small">{{ __('Edit') }}</a>
                        @unless ($address->is_default)
                            <form method="POST" action="{{ route('account.addresses.default', $address) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="link-button">{{ __('Make default') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm(@js(__('Remove the address “:label”?', ['label' => $address->label])))">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="link-button address-card__remove">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($addresses->count() < Address::MAX_PER_USER)
        <p><a href="{{ route('account.addresses.create') }}" class="button">{{ __('Add address') }}</a></p>
    @endif
</x-layouts.account>
