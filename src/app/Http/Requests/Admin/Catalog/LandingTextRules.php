<?php

namespace App\Http\Requests\Admin\Catalog;

/**
 * Rules for the texts every catalog listing page has: heading, intro and SEO.
 */
trait LandingTextRules
{
    /**
     * @return array<string, list<string>>
     */
    protected function landingTextRules(): array
    {
        return [
            'h1' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function slugRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }
}
