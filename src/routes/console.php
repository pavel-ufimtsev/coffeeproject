<?php

use Illuminate\Support\Facades\Schedule;

// Keep prices in line with retailCRM. Skipped until retailCRM is configured.
Schedule::command('retailcrm:sync-catalog')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->when(fn () => filled(config('services.retailcrm.url')) && filled(config('services.retailcrm.api_key')));
