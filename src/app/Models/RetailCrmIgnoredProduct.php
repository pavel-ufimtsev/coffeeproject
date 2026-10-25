<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An active retailCRM product the site does not sell on purpose, so the admin
 * stops listing it as unlinked.
 *
 * @property int $id
 * @property int $retailcrm_product_id
 * @property string $name
 */
#[Fillable(['retailcrm_product_id', 'name'])]
class RetailCrmIgnoredProduct extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'retailcrm_ignored_products';
}
