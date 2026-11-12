<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records every create, update and delete of the model in the audit log,
 * with the old and new values of its fillable attributes.
 *
 * Override auditExcept() to keep secrets out of the log.
 */
trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logFillable()
            ->logExcept($this->auditExcept())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Attributes never written to the audit log.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return [];
    }
}
