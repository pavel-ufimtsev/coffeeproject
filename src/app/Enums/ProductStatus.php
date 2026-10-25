<?php

namespace App\Enums;

enum ProductStatus: string
{
    /** Being prepared; never shown on the site. */
    case Draft = 'draft';

    /** Listed in the catalog and can be bought. */
    case Active = 'active';

    /** Not listed; its page stays reachable so old links keep working. */
    case Archived = 'archived';

    /**
     * Get the human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Active => __('Active'),
            self::Archived => __('Archived'),
        };
    }
}
