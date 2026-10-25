<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Enums\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A new product starts as a draft with just enough to find it again; the
     * rest is filled in on the edit page.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'roaster_id' => ['nullable', 'integer', 'exists:roasters,id'],
            'retailcrm_product_id' => ['nullable', 'integer', 'min:1', 'unique:products,retailcrm_product_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'retailcrm_product_id.unique' => __('Another product is already linked to this retailCRM product.'),
        ];
    }
}
