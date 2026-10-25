<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\FilterValueRequest;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Models\Redirect;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FilterValueController extends Controller
{
    /**
     * Show the form for a new value.
     */
    public function create(FilterGroup $filterGroup): Response
    {
        return $this->form($filterGroup, null);
    }

    /**
     * Store a new value at the end of the group.
     */
    public function store(FilterValueRequest $request, FilterGroup $filterGroup): RedirectResponse
    {
        $value = $filterGroup->values()->create([...$request->validated(), 'sort' => (int) $filterGroup->values()->max('sort') + 1]);

        if ($value->is_landing) {
            Redirect::release($this->path($value));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Value created.')]);

        return to_route('admin.filter-groups.edit', $filterGroup);
    }

    /**
     * Show the form for editing a value and its landing page.
     */
    public function edit(FilterValue $filterValue): Response
    {
        return $this->form($filterValue->group, $filterValue);
    }

    /**
     * Update a value. A landing page that moves or is switched off redirects
     * from its old address.
     */
    public function update(FilterValueRequest $request, FilterValue $filterValue): RedirectResponse
    {
        $wasLanding = $filterValue->is_landing;
        $oldPath = $this->path($filterValue);

        $isLanding = $request->boolean('is_landing');

        $filterValue->update($request->validated());

        if ($wasLanding) {
            Redirect::moved($oldPath, $isLanding ? $this->path($filterValue) : $filterValue->group->category->path());
        } elseif ($isLanding) {
            Redirect::release($this->path($filterValue));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Value saved.')]);

        return to_route('admin.filter-groups.edit', $filterValue->filter_group_id);
    }

    /**
     * Save the order of a group's values.
     */
    public function reorder(Request $request, FilterGroup $filterGroup): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::apply($filterGroup->values(), $ids);

        return back();
    }

    /**
     * Delete a value. Its landing page redirects to the root category.
     */
    public function destroy(FilterValue $filterValue): RedirectResponse
    {
        $group = $filterValue->group;

        if ($filterValue->is_landing) {
            Redirect::moved($this->path($filterValue), $group->category->path());
        }

        $filterValue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Value deleted.')]);

        return to_route('admin.filter-groups.edit', $group);
    }

    private function form(FilterGroup $group, ?FilterValue $value): Response
    {
        $group->load('category');

        return Inertia::render('admin/filters/ValueForm', [
            'group' => [...$group->only(['id', 'name', 'slug']), 'category_name' => $group->category->name, 'category_slug' => $group->category->slug],
            'value' => $value ? [
                ...$value->only(['id', 'name', 'slug', 'parent_id', 'is_landing', 'h1', 'intro', 'seo_title', 'seo_description']),
                'url' => $value->is_landing ? url($this->path($value)) : null,
                'products' => $value->products()->with('category.parent')->orderBy('name')->get()
                    ->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'status' => $product->status->value, 'status_label' => $product->status->label()]),
            ] : null,
            'parents' => $group->values()->whereNull('parent_id')->when($value, fn ($q) => $q->whereKeyNot($value->id))->orderBy('sort')->get(['id', 'name']),
        ]);
    }

    private function path(FilterValue $value): string
    {
        return "/{$value->group->category->slug}/{$value->slug}";
    }
}
