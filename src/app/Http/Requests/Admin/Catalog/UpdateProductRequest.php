<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\CatalogSlugs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->product();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products')->ignore($product)],
            'type' => ['required', Rule::enum(ProductType::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'roaster_id' => ['nullable', 'integer', 'exists:roasters,id'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:65000'],
            'specs' => ['array'],
            'specs.*' => ['nullable', 'string', 'max:500'],
            'is_featured' => ['boolean'],
            'sort' => ['integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'filter_value_ids' => ['array'],
            'filter_value_ids.*' => ['integer', 'exists:filter_values,id'],
            'collection_ids' => ['array'],
            'collection_ids.*' => ['integer', 'exists:collections,id'],
            'variants' => ['array'],
            'variants.*.id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'variants.*.label' => ['required', 'string', 'max:100'],
            'variants.*.weight_g' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.location_ids' => ['array'],
            'variants.*.location_ids.*' => ['integer', 'exists:locations,id'],
        ];
    }

    /**
     * Check the address is free under the chosen root, and that a product
     * going on sale has something to sell.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $root = Category::findOrFail($this->integer('category_id'))->root();
                if ($conflict = CatalogSlugs::conflict($root, $this->string('slug')->value(), $this->product())) {
                    $validator->errors()->add('slug', $conflict);
                }

                if ($this->input('status') === ProductStatus::Active->value && ! $this->hasPricedVariant()) {
                    $validator->errors()->add('status', __('Link retailCRM offers with a price and keep at least one variant shown before putting the product on sale.'));
                }
            },
        ];
    }

    /**
     * Get the product being edited.
     */
    public function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }

    /**
     * Determine whether a variant left shown by this form has a price.
     */
    private function hasPricedVariant(): bool
    {
        /** @var list<array{id: int, is_active?: bool}> $variants */
        $variants = $this->input('variants', []);
        $shown = collect($variants)->where('is_active', true)->pluck('id');

        return $this->product()->variants()->whereIn('id', $shown)->where('price', '>', 0)->exists();
    }
}
