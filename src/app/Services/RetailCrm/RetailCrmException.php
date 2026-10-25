<?php

namespace App\Services\RetailCrm;

use RuntimeException;

class RetailCrmException extends RuntimeException
{
    /**
     * Create an exception for missing configuration.
     */
    public static function notConfigured(): self
    {
        return new self('retailCRM is not configured: set RETAILCRM_URL and RETAILCRM_API_KEY in .env.');
    }
}
