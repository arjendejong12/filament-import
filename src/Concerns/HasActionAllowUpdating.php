<?php

namespace Konnco\FilamentImport\Concerns;

trait HasActionAllowUpdating
{
    protected bool|string $allowUpdatingExistingModelAttribute = false;
    protected array $allowUpdatingExistingModelValues = [];

    public function allowUpdatingExistingModel(bool|string $fieldToCheck, array $fieldsToUpdate): static
    {
        $this->allowUpdatingExistingModelAttribute = $fieldToCheck;
        $this->allowUpdatingExistingModelValues = $fieldsToUpdate;

        return $this;
    }
}
