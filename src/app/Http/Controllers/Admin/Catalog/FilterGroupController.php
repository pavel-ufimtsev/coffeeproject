<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\FilterGroupRequest;
use App\Models\Category;
use App\Models\FilterGroup;
use App\Models\FilterValue;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FilterGroupController extends Controller
{
    /**
     * List the filter groups of each root category.
     */
    public function index(): Response
    {
        return Inertia::render('admin/filters/Index', [
            'roots' => Category::roots()->orderBy('sort')
                ->with(['filterGroups' => fn ($q) => $q->withCount(['values', 'values as landing_count' => fn ($q) => $q->where('is_landing', true)])])
                ->get()
                ->map(fn (Category $root) => [
                    'id' => $root->id,
                    'name' => $root->name,
                    'groups' => $root->filterGroups->map(fn (FilterGroup $group) => [
                        ...$group->only(['id', 'name', 'slug', 'is_visible', 'values_count', 'landing_count']),
                    ]),
                ]),
        ]);
    }

    /**
     * Show the form for a new filter group.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/filters/GroupForm', [
            'group' => null,
            'categoryId' => $request->integer('category') ?: null,
            'roots' => Category::roots()->orderBy('sort')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a new filter group at the end of its category's groups.
     */
    public function store(FilterGroupRequest $request): RedirectResponse
    {
        $group = FilterGroup::create([
            ...$request->validated(),
            'sort' => (int) FilterGroup::where('category_id', $request->validated('category_id'))->max('sort') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filter created. Now add its values.')]);

        return to_route('admin.filter-groups.edit', $group);
    }

    /**
     * Show a filter group with its values.
     */
    public function edit(FilterGroup $filterGroup): Response
    {
        $filterGroup->load('category');

        return Inertia::render('admin/filters/GroupForm', [
            'group' => [
                ...$filterGroup->only(['id', 'category_id', 'name', 'slug', 'is_visible']),
                'category_name' => $filterGroup->category->name,
                'values' => $filterGroup->values()
                    ->withCount(['products', 'products as active_products_count' => fn ($q) => $q->where('status', ProductStatus::Active)])
                    ->orderBy('sort')
                    ->get()
                    ->map(fn (FilterValue $value) => [
                        ...$value->only(['id', 'name', 'slug', 'parent_id', 'is_landing', 'products_count', 'active_products_count']),
                        'url' => $value->is_landing ? url("/{$filterGroup->category->slug}/{$value->slug}") : null,
                    ]),
            ],
            'categoryId' => $filterGroup->category_id,
            'roots' => Category::roots()->orderBy('sort')->get(['id', 'name']),
        ]);
    }

    /**
     * Update a filter group.
     */
    public function update(FilterGroupRequest $request, FilterGroup $filterGroup): RedirectResponse
    {
        $filterGroup->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filter saved.')]);

        return back();
    }

    /**
     * Save the order of a category's filter groups.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        SortOrder::apply(FilterGroup::where('category_id', FilterGroup::whereKey($ids[0])->value('category_id')), $ids);

        return back();
    }

    /**
     * Delete a filter group that has no values left.
     */
    public function destroy(FilterGroup $filterGroup): RedirectResponse
    {
        if ($filterGroup->values()->exists()) {
            throw ValidationException::withMessages(['group' => __('Delete the values of this filter first.')]);
        }

        $filterGroup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filter deleted.')]);

        return to_route('admin.filter-groups.index');
    }
}
