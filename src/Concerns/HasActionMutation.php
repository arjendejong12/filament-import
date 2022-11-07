<?php

namespace Konnco\FilamentImport\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;

trait HasActionMutation
{
    protected bool|Closure $mutateRowsBeforeCreate = false;
    protected bool|Closure $mutateBeforeCreate = false;
    protected bool|Closure $mutateAfterCreate = false;

    public function mutateRowsBeforeCreate(bool|Closure $fn): static
    {
        $this->mutateRowsBeforeCreate = $fn;

        return $this;
    }

    public function doMutateRowsBeforeCreate(array $row)
    {
        $closure = $this->mutateRowsBeforeCreate;

        if (! $closure) {
            return $row;
        }

        return $closure($row);
    }

    public function mutateBeforeCreate(bool|Closure $fn): static
    {
        $this->mutateBeforeCreate = $fn;

        return $this;
    }

    public function doMutateBeforeCreate(array $row)
    {
        $closure = $this->mutateBeforeCreate;

        if (! $closure) {
            return $row;
        }

        return $closure($row);
    }

    public function mutateAfterCreate(bool|Closure $fn): static
    {
        $this->mutateAfterCreate = $fn;

        return $this;
    }

    public function doMutateAfterCreate(Model $model, array $row)
    {
        $closure = $this->mutateAfterCreate;

        if (! $closure) {
            return $model;
        }

        return $closure($model, $row);
    }
}
