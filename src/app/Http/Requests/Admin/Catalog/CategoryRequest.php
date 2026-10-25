<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Models\Category;
use App\Services\Catalog\CatalogSlugs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CategoryRequest extends FormRequest
{
    use LandingTextRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * New categories go directly under a root: addresses are /{root}/{slug},
     * so the tree is two levels deep. Root slugs are URL prefixes and cannot change.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->category();

        return [
            'name' => ['required', 'string', 'max:255'],
            ...($category?->isRoot() ? [] : ['slug' => $this->slugRules()]),
            ...($category ? [] : ['parent_id' => ['required', 'integer', 'exists:categories,id']]),
            ...$this->landingTextRules(),
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->category()?->isRoot()) {
                    return;
                }

                $parent = $this->category()->parent ?? Category::findOrFail($this->integer('parent_id'));

                if (! $parent->isRoot()) {
                    $validator->errors()->add('parent_id', __('Categories can only be added directly under Coffee, Tea or Accessories.'));
                } elseif ($conflict = CatalogSlugs::conflict($parent, $this->string('slug')->value(), $this->category())) {
                    $validator->errors()->add('slug', $conflict);
                }
            },
        ];
    }

    private function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
