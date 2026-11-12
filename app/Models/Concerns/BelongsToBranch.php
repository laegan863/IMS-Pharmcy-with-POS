<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every table that holds branch data: limits queries to the signed-in
 * user's branch and fills branch_id on create when it was not given.
 *
 * Use withoutGlobalScope(BranchScope::class) only for deliberate
 * cross-branch work, such as owner reports or branch transfers.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('branch_id') === null) {
                $model->setAttribute('branch_id', BranchScope::currentBranchId());
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
