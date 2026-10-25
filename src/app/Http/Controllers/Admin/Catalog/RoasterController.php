<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Roaster;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoasterController extends Controller
{
    /**
     * List roasters.
     */
    public function index(): Response
    {
        return Inertia::render('admin/roasters/Index', [
            'roasters' => Roaster::withCount(['products', 'locations'])->orderBy('sort')->get()
                ->map(fn (Roaster $roaster) => $roaster->only(['id', 'name', 'city', 'is_partner', 'is_active', 'products_count', 'locations_count'])),
        ]);
    }

    /**
     * Show the form for a new roaster.
     */
    public function create(): Response
    {
        return Inertia::render('admin/roasters/Form', ['roaster' => null]);
    }

    /**
     * Store a new roaster.
     */
    public function store(Request $request): RedirectResponse
    {
        $roaster = Roaster::create([...$this->validated($request), 'sort' => (int) Roaster::max('sort') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roaster created. Add the places it ships from.')]);

        return to_route('admin.roasters.edit', $roaster);
    }

    /**
     * Show the form for editing a roaster and its locations.
     */
    public function edit(Roaster $roaster): Response
    {
        return Inertia::render('admin/roasters/Form', [
            'roaster' => [
                ...$roaster->only(['id', 'slug', 'name', 'city', 'story', 'website', 'is_partner', 'is_active']),
                'products_count' => $roaster->products()->count(),
                'locations' => $roaster->locations()->withCount('variants')->orderBy('id')->get()
                    ->map(fn (Location $location) => $location->only(['id', 'name', 'city', 'retailcrm_store_code', 'roast_days', 'is_active', 'variants_count'])),
            ],
        ]);
    }

    /**
     * Update a roaster.
     */
    public function update(Request $request, Roaster $roaster): RedirectResponse
    {
        $roaster->update($this->validated($request, $roaster));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roaster saved.')]);

        return back();
    }

    /**
     * Save the order of roasters.
     */
    public function reorder(Request $request): RedirectResponse
    {
        SortOrder::apply(Roaster::query(), $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids']);

        return back();
    }

    /**
     * Delete a roaster with no products or locations.
     */
    public function destroy(Roaster $roaster): RedirectResponse
    {
        if ($roaster->products()->exists() || $roaster->locations()->exists()) {
            throw ValidationException::withMessages(['roaster' => __('Only a roaster without products and locations can be deleted. Switch it off instead.')]);
        }

        $roaster->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roaster deleted.')]);

        return to_route('admin.roasters.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Roaster $roaster = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('roasters')->ignore($roaster)],
            'city' => ['required', 'string', 'max:255'],
            'story' => ['nullable', 'string', 'max:10000'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_partner' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }
}
