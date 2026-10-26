<?php

namespace App\Enums;

enum ReviewStatus: string
{
    /** Waiting for an admin; not shown on the site. */
    case Pending = 'pending';

    case Approved = 'approved';

    /** Hidden for good, kept so the same text is not approved by mistake later. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Approved => __('Published'),
            self::Rejected => __('Rejected'),
        };
    }
}
