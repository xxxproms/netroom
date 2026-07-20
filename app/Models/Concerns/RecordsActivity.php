<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Logs every change to a model with its before/after values, which is what the
 * audit page reads. A model narrows what is recorded by overriding
 * auditableAttributes().
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditableAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename($this));
    }

    /**
     * Attributes worth a log entry.
     *
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return ['*'];
    }
}
