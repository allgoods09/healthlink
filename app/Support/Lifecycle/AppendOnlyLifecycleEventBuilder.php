<?php

namespace App\Support\Lifecycle;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

// Model events do not protect bulk or quiet mutations. Block those ORM paths too.
class AppendOnlyLifecycleEventBuilder extends Builder
{
    public function update(array $values)
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function delete()
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function forceDelete()
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw new LogicException('Resident lifecycle events are append-only.');
    }
}
