<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Concerns;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Validates a model's attributes against its own rules before every save, so
 * integrity is enforced no matter which API was used to write the record.
 *
 * This hooks into save() rather than the "saving" model event, so it keeps
 * working when an application fakes events (Event::fake()) or saves quietly.
 *
 * The same rules() can be reused by applications in their form requests.
 */
trait ValidatesAttributes
{
    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws ValidationException
     */
    public function save(array $options = []): bool
    {
        $this->prepareAttributes();

        if (PackageConfig::validationEnabled()) {
            $this->validate();
        }

        return parent::save($options);
    }

    /**
     * @throws ValidationException
     */
    public function validate(): void
    {
        Validator::make($this->getAttributes(), $this->rules())->validate();
    }

    /**
     * Derives computed attributes before validation runs.
     */
    protected function prepareAttributes(): void {}

    /**
     * A unique rule bound to this model's own connection and table, ignoring
     * the current record.
     */
    protected function uniqueRule(string $column): Unique
    {
        $table = $this->getConnectionName() === null
            ? $this->getTable()
            : $this->getConnectionName().'.'.$this->getTable();

        return Rule::unique($table, $column)->ignore($this->getKey(), $this->getKeyName());
    }
}
