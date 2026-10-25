<?php

namespace App\Http\Requests\Shop;

use App\Enums\ProductType;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddToCartRequest extends FormRequest
{
    private ?ProductVariant $variant = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variant' => ['required', 'integer'],
            'grind' => ['nullable', 'string', Rule::in(array_keys(ProductType::grinds()))],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $variant = ProductVariant::with('product.activeVariants')->find($this->integer('variant'));

                if (! $variant?->is_active || ! $variant->product->isPurchasable() || (float) $variant->price <= 0) {
                    $validator->errors()->add('variant', __('This item cannot be ordered right now.'));

                    return;
                }

                $this->variant = $variant;
            },
        ];
    }

    public function variant(): ProductVariant
    {
        return $this->variant;
    }

    /**
     * The grind for coffee (whole beans unless the shopper chose one); none
     * for everything else.
     */
    public function grind(): ?string
    {
        if (! $this->variant->product->type->hasGrind()) {
            return null;
        }

        return $this->filled('grind') ? $this->string('grind')->value() : 'whole';
    }
}
