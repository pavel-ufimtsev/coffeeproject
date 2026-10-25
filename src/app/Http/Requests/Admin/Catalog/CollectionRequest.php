<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Models\Category;
use App\Models\ProductCollection;
use App\Services\Catalog\CatalogSlugs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CollectionRequest extends FormRequest
{
    use LandingTextRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $collection = $this->collection();

        return [
            ...($collection ? [] : ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')]]),
            'name' => ['required', 'string', 'max:255'],
            'slug' => [...$this->slugRules(), Rule::unique('collections')->where('category_id', $this->rootCategory()?->id)->ignore($collection)],
            'is_landing' => ['boolean'],
            ...$this->landingTextRules(),
            'product_ids' => ['array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ];
    }

    /**
     * A landing page lives at /{root}/{slug}, so its slug must be free there.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->boolean('is_landing')
                    && $conflict = CatalogSlugs::conflict($this->rootCategory(), $this->string('slug')->value(), $this->collection())) {
                    $validator->errors()->add('slug', $conflict);
                }
            },
        ];
    }

    public function collection(): ?ProductCollection
    {
        $collection = $this->route('collection');

        return $collection instanceof ProductCollection ? $collection : null;
    }

    public function rootCategory(): ?Category
    {
        return $this->collection()->category ?? Category::find($this->integer('category_id'));
    }
}
