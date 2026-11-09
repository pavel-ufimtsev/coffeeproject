<?php

namespace App\Enums;

enum BlogPostStatus: string
{
    /** Being written or waiting for review; only admins can preview it. */
    case Draft = 'draft';

    /** Goes live by itself when its publish date arrives. */
    case Scheduled = 'scheduled';

    /** Live on the site. */
    case Published = 'published';

    /** Retired: off the site, usually with a redirect to a better page. */
    case Archived = 'archived';

    /**
     * Get the human-readable name of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Published => __('Published'),
            self::Archived => __('Archived'),
        };
    }
}
