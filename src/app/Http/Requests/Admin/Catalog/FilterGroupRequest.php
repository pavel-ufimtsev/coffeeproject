<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Models\FilterGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterGroupRequest extends FormRequest
{
    use LandingTextRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The slug is the query parameter (?brew=espresso), unique within the root category.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->route('filter_group');
        $categoryId = $group instanceof FilterGroup ? $group->category_id : $this->integer('category_id');

        return [
            ...($group instanceof FilterGroup ? [] : ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')]]),
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                ...$this->slugRules(),
                Rule::notIn(['sort', 'page', 'collection', 'roaster', 'city', 'lang']),
                Rule::unique('filter_groups')->where('category_id', $categoryId)->ignore($group),
            ],
            'is_visible' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.not_in' => __('This word is reserved for other catalog filters.')];
    }
}
