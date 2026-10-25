<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RedirectController extends Controller
{
    /**
     * List redirects, most used first by default.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['hits', 'unused', 'recent'])],
        ]);
        $sort = $filters['sort'] ?? 'hits';

        $redirects = Redirect::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('from_path', 'like', "%{$search}%")
                ->orWhere('to_path', 'like', "%{$search}%")))
            ->when($sort === 'hits', fn (Builder $query) => $query->orderByDesc('hits'))
            ->when($sort === 'unused', fn (Builder $query) => $query->where('hits', 0)->orderBy('from_path'))
            ->when($sort === 'recent', fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->orderBy('from_path')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Redirect $redirect) => [
                ...$redirect->only(['id', 'from_path', 'to_path', 'status_code', 'hits']),
                'last_hit_at' => $redirect->last_hit_at?->toDateString(),
            ]);

        return Inertia::render('admin/redirects/Index', [
            'redirects' => $redirects,
            'filters' => ['search' => $filters['search'] ?? '', 'sort' => $sort],
            'total' => Redirect::count(),
        ]);
    }

    /**
     * Show the form for a new redirect.
     */
    public function create(): Response
    {
        return Inertia::render('admin/redirects/Form', ['redirect' => null]);
    }

    /**
     * Store a new redirect.
     */
    public function store(Request $request): RedirectResponse
    {
        Redirect::create($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect created.')]);

        return to_route('admin.redirects.index');
    }

    /**
     * Show the form for editing a redirect.
     */
    public function edit(Redirect $redirect): Response
    {
        return Inertia::render('admin/redirects/Form', [
            'redirect' => [
                ...$redirect->only(['id', 'from_path', 'to_path', 'status_code', 'hits']),
                'last_hit_at' => $redirect->last_hit_at?->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Update a redirect.
     */
    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        $redirect->update($this->validated($request, $redirect));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect saved.')]);

        return to_route('admin.redirects.index');
    }

    /**
     * Delete a redirect.
     */
    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Redirect deleted.')]);

        return to_route('admin.redirects.index');
    }

    /**
     * Validate a redirect. Old addresses are stored normalised, and a target
     * that itself redirects is refused so visitors never go through a chain.
     *
     * @return array{from_path: string, to_path: string, status_code: int}
     */
    private function validated(Request $request, ?Redirect $redirect = null): array
    {
        $request->merge(['from_path' => Redirect::normalizePath((string) parse_url((string) $request->input('from_path'), PHP_URL_PATH))]);

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:512', 'not_in:/', Rule::unique('redirects')->ignore($redirect)],
            'to_path' => ['required', 'string', 'max:512', 'regex:#^(/|https?://)#', 'different:from_path'],
            'status_code' => ['required', 'integer', Rule::in([301, 302])],
        ], [
            'to_path.regex' => __('Start the new address with / or https://.'),
        ]);

        $next = Redirect::where('from_path', Redirect::normalizePath((string) parse_url($data['to_path'], PHP_URL_PATH)))->value('to_path');
        if (str_starts_with($data['to_path'], '/') && $next !== null) {
            throw ValidationException::withMessages(['to_path' => __(':path redirects to :next itself. Use the final address.', ['path' => $data['to_path'], 'next' => $next])]);
        }

        return $data;
    }
}
