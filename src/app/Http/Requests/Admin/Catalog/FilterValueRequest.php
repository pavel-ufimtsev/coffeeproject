<?php

namespace App\Http\Requests\Admin\Catalog;

use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Services\Catalog\CatalogSlugs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FilterValueRequest extends FormRequest
{
    use LandingTextRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $value = $this->value();
        $group = $this->group();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [...$this->slugRules(), Rule::unique('filter_values')->where('filter_group_id', $group->id)->ignore($value)],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('filter_values', 'id')->where('filter_group_id', $group->id)->whereNull('parent_id'),
                ...($value ? [Rule::notIn([$value->id])] : []),
            ],
            'is_landing' => ['boolean'],
            ...$this->landingTextRules(),
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
                    && $conflict = CatalogSlugs::conflict($this->group()->category, $this->string('slug')->value(), $this->value())) {
                    $validator->errors()->add('slug', $conflict);
                }
            },
        ];
    }

    private function value(): ?FilterValue
    {
        $value = $this->route('filter_value');

        return $value instanceof FilterValue ? $value : null;
    }

    private function group(): FilterGroup
    {
        /** @var FilterGroup */
        return $this->value()->group ?? $this->route('filter_group');
    }
}
