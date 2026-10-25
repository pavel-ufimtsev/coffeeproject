<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class SortOrder
{
    /**
     * Save a manual order: each record gets its position in the list as its
     * sort value. IDs that do not belong to the query are ignored.
     *
     * @template TModel of Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>|\Illuminate\Database\Eloquent\Relations\Relation<TModel, *, *>  $query
     * @param  list<int>  $ids
     */
    public static function apply($query, array $ids, string $column = 'sort'): void
    {
        $positions = array_flip($ids);

        $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids)->get()
            ->each(fn (Model $model) => $model->update([$column => $positions[$model->getKey()]]));
    }
}
