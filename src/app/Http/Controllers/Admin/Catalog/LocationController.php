<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Roaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    /** Days a location can roast on, in week order. */
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Show the form for a new location.
     */
    public function create(Roaster $roaster): Response
    {
        return $this->form($roaster, null);
    }

    /**
     * Store a new location.
     */
    public function store(Request $request, Roaster $roaster): RedirectResponse
    {
        $roaster->locations()->create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location created.')]);

        return to_route('admin.roasters.edit', $roaster);
    }

    /**
     * Show the form for editing a location.
     */
    public function edit(Location $location): Response
    {
        return $this->form($location->roaster, $location);
    }

    /**
     * Update a location.
     */
    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location saved.')]);

        return to_route('admin.roasters.edit', $location->roaster_id);
    }

    /**
     * Delete a location. Variants it shipped no longer list it.
     */
    public function destroy(Location $location): RedirectResponse
    {
        $roasterId = $location->roaster_id;
        $location->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location deleted.')]);

        return to_route('admin.roasters.edit', $roasterId);
    }

    private function form(Roaster $roaster, ?Location $location): Response
    {
        return Inertia::render('admin/roasters/LocationForm', [
            'roaster' => $roaster->only(['id', 'name', 'city']),
            'location' => $location?->only(['id', 'name', 'city', 'address', 'retailcrm_store_code', 'roast_days', 'is_active']),
            'days' => collect(self::DAYS)->map(fn (string $day) => ['value' => $day, 'label' => Carbon::parse($day)->isoFormat('dddd')]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'retailcrm_store_code' => ['nullable', 'string', 'max:64'],
            'roast_days' => ['array'],
            'roast_days.*' => [Rule::in(self::DAYS)],
            'is_active' => ['boolean'],
        ]);

        // Keep days in week order whatever order they were ticked in.
        $data['roast_days'] = array_values(array_intersect(self::DAYS, $data['roast_days'] ?? [])) ?: null;

        return $data;
    }
}
