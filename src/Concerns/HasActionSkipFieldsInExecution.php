<?php

namespace Konnco\FilamentImport\Concerns;

trait HasActionSkipFieldsInExecution
{
    protected array $skipFieldsInExecution = [];

    public function skipFieldsInExecution(array $skipFieldsInExecution): static
    {
        $this->skipFieldsInExecution = $skipFieldsInExecution;

        return $this;
    }
}
