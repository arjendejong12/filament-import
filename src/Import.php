<?php

namespace Konnco\FilamentImport;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Konnco\FilamentImport\Actions\ImportField;
use Konnco\FilamentImport\Concerns\HasActionMutation;
use Konnco\FilamentImport\Concerns\HasActionUniqueField;
use Maatwebsite\Excel\Concerns\Importable;

class Import
{
    use Importable;
    use HasActionMutation;
    use HasActionUniqueField;

    protected string $spreadsheet;

    protected Collection $fields;

    protected array $formSchemas;

    protected string|Model $model;

    protected string $disk = 'local';

    protected bool $shouldSkipHeader = false;

    protected bool $shouldMassCreate = true;

    public static function make(string $spreadsheetFilePath): self
    {
        return (new self)
            ->spreadsheet($spreadsheetFilePath);
    }

    public function fields(Collection $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function formSchemas(array $formSchemas): static
    {
        $this->formSchemas = $formSchemas;

        return $this;
    }

    public function spreadsheet($spreadsheet): static
    {
        $this->spreadsheet = $spreadsheet;

        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function disk($disk = 'local'): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function skipHeader(bool $shouldSkipHeader): static
    {
        $this->shouldSkipHeader = $shouldSkipHeader;

        return $this;
    }

    public function massCreate($shouldMassCreate = true): static
    {
        $this->shouldMassCreate = $shouldMassCreate;

        return $this;
    }

    public function getSpreadsheetData(): Collection
    {
        return $this->toCollection(new UploadedFile(Storage::disk($this->disk)->path($this->spreadsheet), $this->spreadsheet))
            ->first()
            ->skip((int) $this->shouldSkipHeader);
    }

    public function validated($data, $rules, $customMessages, $line)
    {
        $validator = Validator::make($data, $rules, $customMessages);

        try {
            if ($validator->fails()) {
                Notification::make()
                    ->danger()
                    ->title(trans('filament-import::actions.import_failed_title'))
                    ->body(trans('filament-import::validators.message', ['line' => $line, 'error' => $validator->errors()->first()]))
                    ->persistent()
                    ->send();

                return false;
            }
        } catch (\Exception $e) {
            return $data;
        }

        return $data;
    }

    public function execute()
    {
        $importData = [];
        $importDataProcessing = true;
        foreach ($this->getSpreadsheetData() as $line => $row) {
            $prepareInsert = collect([]);
            $rules = [];
            $validationMessages = [];

            foreach (Arr::dot($this->fields) as $key => $value) {
                $field = $this->formSchemas[$key];
                $fieldValue = $value;

                if ($field instanceof ImportField) {
                    // check if field is optional
                    if (!$field->isRequired() && blank(@$row[$value])) {
                        continue;
                    }

                    $fieldValue = $field->doMutateBeforeCreate($row[$value], collect($row), $line) ?? $row[$value];
                    $rules[$key] = $field->getValidationRules();
                    if (count($field->getCustomValidationMessages())) {
                        $validationMessages[$key] = $field->getCustomValidationMessages();
                    }
                }

                $prepareInsert[$key] = $fieldValue;
            }

            $prepareInsert = $this->validated(data: Arr::undot($prepareInsert), rules: $rules, customMessages: $validationMessages, line: $line + 1);

            if (!$prepareInsert) {
                $importDataProcessing = false;

                break;
            }

            $importData[$line] = $prepareInsert;
        }

        if (!$importDataProcessing) {
            return;
        }

        $importData = $this->doMutateRowsBeforeCreate($importData);
        // Short circuit.
        if ($importData === false) {
            return;
        }

        $importSuccess = true;
        $skipped = 0;
        DB::transaction(function () use ($importData, &$importSuccess, &$skipped) {
            foreach ($importData as $line => $prepareInsert) {
                $prepareInsert = $this->doMutateBeforeCreate($prepareInsert);
                // Short circuit.
                if ($prepareInsert === false) {
                    DB::rollBack();
                    $importSuccess = false;

                    break;
                }

                $prepareInsert = $this->doMutateBeforeCreate($prepareInsert);

                if ($this->uniqueField !== false) {
                    if (is_null($prepareInsert[$this->uniqueField] ?? null)) {
                        DB::rollBack();
                        $importSuccess = false;

                        break;
                    }

                    $exists = (new $this->model)->where($this->uniqueField, $prepareInsert[$this->uniqueField] ?? null)->first();
                    if ($exists instanceof $this->model) {
                        $skipped++;

                        continue;
                    }
                }

                if (! $this->shouldMassCreate) {
                    $model = (new $this->model)->fill($prepareInsert);
                    $model = tap($model, function ($instance) {
                        $instance->save();
                    });
                } else {
                    $model = $this->model::create($prepareInsert);
                }

                $this->doMutateAfterCreate($model, $prepareInsert);
            }
        });

        if ($importSuccess) {
            Notification::make()
                ->success()
                ->title(trans('filament-import::actions.import_succeeded_title'))
                ->body(trans('filament-import::actions.import_succeeded', ['count' => count($this->getSpreadsheetData()), 'skipped' => $skipped]))
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->danger()
                ->title(trans('filament-import::actions.import_failed_title'))
                ->body(trans('filament-import::actions.import_failed'))
                ->persistent()
                ->send();
        }
    }
}
