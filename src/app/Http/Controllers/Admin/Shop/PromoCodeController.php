<?php

namespace App\Http\Controllers\Admin\Shop;

use App\Enums\PromoType;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PromoCodeController extends Controller
{
    /**
     * List promo codes.
     */
    public function index(): Response
    {
        return Inertia::render('admin/promo-codes/Index', [
            'promoCodes' => PromoCode::latest('id')->get()->map(fn (PromoCode $promo) => [
                'id' => $promo->id,
                'code' => $promo->code,
                'value_label' => $promo->valueLabel(),
                'note' => $promo->note,
                'used_count' => $promo->used_count,
                'max_uses' => $promo->max_uses,
                'period' => $this->period($promo),
                'is_active' => $promo->is_active,
                'problem' => $promo->is_active ? $promo->problemFor(PHP_FLOAT_MAX) : null,
            ]),
        ]);
    }

    /**
     * Show the form for a new promo code.
     */
    public function create(): Response
    {
        return Inertia::render('admin/promo-codes/Form', ['promoCode' => null, 'types' => PromoType::options()]);
    }

    /**
     * Store a new promo code.
     */
    public function store(Request $request): RedirectResponse
    {
        PromoCode::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code created.')]);

        return to_route('admin.promo-codes.index');
    }

    /**
     * Show the form for editing a promo code.
     */
    public function edit(PromoCode $promoCode): Response
    {
        return Inertia::render('admin/promo-codes/Form', [
            'promoCode' => [
                ...$promoCode->only(['id', 'code', 'max_uses', 'used_count', 'is_active', 'note']),
                'type' => $promoCode->type->value,
                'value' => (float) $promoCode->value,
                'min_subtotal' => $promoCode->min_subtotal === null ? null : (float) $promoCode->min_subtotal,
                'starts_at' => $promoCode->starts_at?->format('Y-m-d'),
                'ends_at' => $promoCode->ends_at?->format('Y-m-d'),
            ],
            'types' => PromoType::options(),
        ]);
    }

    /**
     * Update a promo code.
     */
    public function update(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $promoCode->update($this->validated($request, $promoCode));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code saved.')]);

        return back();
    }

    /**
     * Delete a promo code that was never used.
     */
    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        if ($promoCode->used_count > 0 || $promoCode->orders()->exists()) {
            throw ValidationException::withMessages(['promo' => __('This code is in orders already. Switch it off instead.')]);
        }

        $promoCode->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code deleted.')]);

        return to_route('admin.promo-codes.index');
    }

    private function period(PromoCode $promo): ?string
    {
        return match (true) {
            $promo->starts_at && $promo->ends_at => $promo->starts_at->format('d.m.Y').' – '.$promo->ends_at->format('d.m.Y'),
            $promo->starts_at !== null => (string) __('from :date', ['date' => $promo->starts_at->format('d.m.Y')]),
            $promo->ends_at !== null => (string) __('until :date', ['date' => $promo->ends_at->format('d.m.Y')]),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PromoCode $promoCode = null): array
    {
        $request->merge(['code' => PromoCode::normalize((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[\p{Lu}0-9_-]+$/u', Rule::unique('promo_codes')->ignore($promoCode)],
            'type' => ['required', Rule::enum(PromoType::class)],
            'value' => ['required', 'numeric', 'min:1', $request->input('type') === PromoType::Percent->value ? 'max:100' : 'max:1000000'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['code.regex' => __('Use letters, digits, dashes and underscores only.')]);

        // The end date counts in full.
        $data['ends_at'] = isset($data['ends_at']) ? now()->parse($data['ends_at'])->endOfDay() : null;

        return $data;
    }
}
