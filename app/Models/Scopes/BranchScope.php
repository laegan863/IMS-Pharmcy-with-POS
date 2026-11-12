<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limits a branch-owned model to the signed-in user's branch.
 *
 * - Signed-in user with a branch: only that branch's rows.
 * - Signed-in user without a branch: no rows (fails closed).
 * - Nobody signed in (console commands, queued jobs, seeders): no filter,
 *   so that code must choose its branch explicitly.
 *
 * @implements Scope<Model>
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if ($user->branch_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
    }

    /**
     * The branch new records belong to when none is given.
     */
    public static function currentBranchId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $user->branch_id : null;
    }
}
