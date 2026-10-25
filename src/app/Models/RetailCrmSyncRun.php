<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One run of the retailCRM catalog sync and what it changed or found.
 *
 * @property int $id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $products_checked
 * @property int $variants_changed
 * @property list<array<string, mixed>>|null $changes
 * @property list<array<string, mixed>>|null $issues
 * @property string|null $error
 */
#[Fillable(['started_at', 'finished_at', 'products_checked', 'variants_changed', 'changes', 'issues', 'error'])]
class RetailCrmSyncRun extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'retailcrm_sync_runs';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'changes' => 'array',
            'issues' => 'array',
        ];
    }
}
