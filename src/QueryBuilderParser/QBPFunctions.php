<?php

namespace timgws;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use stdClass;

trait QBPFunctions
{
    /**
     * @param stdClass $rule
     */
    abstract protected function checkRuleCorrect(stdClass $rule): bool;

    /**
     * @var array<string, array{accept_values: bool, apply_to: array<int, string>}>
     */
    protected array $operators = [
        'equal' => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'not_equal' => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'in' => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'not_in' => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
        'less' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'less_or_equal' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'greater' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'greater_or_equal' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'between' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'not_between' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
        'begins_with' => ['accept_values' => true, 'apply_to' => ['string']],
        'not_begins_with' => ['accept_values' => true, 'apply_to' => ['string']],
        'contains' => ['accept_values' => true, 'apply_to' => ['string']],
        'not_contains' => ['accept_values' => true, 'apply_to' => ['string']],
        'ends_with' => ['accept_values' => true, 'apply_to' => ['string']],
        'not_ends_with' => ['accept_values' => true, 'apply_to' => ['string']],
        'is_empty' => ['accept_values' => false, 'apply_to' => ['string']],
        'is_not_empty' => ['accept_values' => false, 'apply_to' => ['string']],
        'is_null' => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']],
        'is_not_null' => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']],
    ];

    /**
     * @var array<string, array{operator: string, prepend?: string, append?: string}>
     */
    protected array $operator_sql = [
        'equal' => ['operator' => '='],
        'not_equal' => ['operator' => '!='],
        'in' => ['operator' => 'IN'],
        'not_in' => ['operator' => 'NOT IN'],
        'less' => ['operator' => '<'],
        'less_or_equal' => ['operator' => '<='],
        'greater' => ['operator' => '>'],
        'greater_or_equal' => ['operator' => '>='],
        'between' => ['operator' => 'BETWEEN'],
        'not_between' => ['operator' => 'NOT BETWEEN'],
        'begins_with' => ['operator' => 'LIKE', 'prepend' => '%'],
        'not_begins_with' => ['operator' => 'NOT LIKE', 'prepend' => '%'],
        'contains' => ['operator' => 'LIKE', 'append' => '%', 'prepend' => '%'],
        'not_contains' => ['operator' => 'NOT LIKE', 'append' => '%', 'prepend' => '%'],
        'ends_with' => ['operator' => 'LIKE', 'append' => '%'],
        'not_ends_with' => ['operator' => 'NOT LIKE', 'append' => '%'],
        'is_empty' => ['operator' => '='],
        'is_not_empty' => ['operator' => '!='],
        'is_null' => ['operator' => 'NULL'],
        'is_not_null' => ['operator' => 'NOT NULL'],
    ];

    /**
     * @var array<int, string>
     */
    protected array $needs_array = [
        'IN',
        'NOT IN',
        'BETWEEN',
        'NOT BETWEEN',
    ];

    protected function operatorRequiresArray(string $operator): bool
    {
        return in_array($operator, $this->needs_array, true);
    }

    protected function operatorIsNull(string $operator): bool
    {
        return $operator === 'NULL' || $operator === 'NOT NULL';
    }

    /**
     * @throws QBParseException
     */
    protected function validateCondition(?string $condition): ?string
    {
        if ($condition === null) {
            return null;
        }

        $condition = strtolower(trim($condition));

        if ($condition !== 'and' && $condition !== 'or') {
            throw new QBParseException("Condition can only be one of: 'and', 'or'.");
        }

        return $condition;
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws QBParseException
     */
    protected function enforceArrayOrString(bool $requireArray, mixed $value, string $field): mixed
    {
        $this->checkFieldIsAnArray($requireArray, $value, $field);

        if (! $requireArray && is_array($value)) {
            return $this->convertArrayToFlatValue($field, $value);
        }

        return $value;
    }

    /**
     * @throws QBParseException
     */
    protected function checkFieldIsAnArray(bool $requireArray, mixed $value, string $field): void
    {
        if ($requireArray && ! is_array($value)) {
            throw new QBParseException("Field ($field) should be an array, but it isn't.");
        }
    }

    /**
     * @param array<int, mixed> $value
     * @return mixed
     * @throws QBParseException
     */
    protected function convertArrayToFlatValue(string $field, array $value): mixed
    {
        if (count($value) !== 1) {
            throw new QBParseException("Field ($field) should not be an array, but it is.");
        }

        return $value[0];
    }

    /**
     * @param mixed $value
     * @return Carbon|array<int, Carbon>
     */
    protected function convertDatetimeToCarbon(mixed $value): Carbon|array
    {
        if (is_array($value)) {
            return array_map(static fn ($date) => new Carbon($date), $value);
        }

        return new Carbon($value);
    }

    /**
     * @param mixed $value
     * @param array{operator: string, prepend?: string, append?: string} $sqlOperator
     * @return mixed
     */
    protected function appendOperatorIfRequired(bool $requireArray, mixed $value, array $sqlOperator): mixed
    {
        if (! $requireArray) {
            if (isset($sqlOperator['append'])) {
                $value = $sqlOperator['append'] . $value;
            }

            if (isset($sqlOperator['prepend'])) {
                $value .= $sqlOperator['prepend'];
            }
        }

        return $value;
    }

    /**
     * @throws QBParseException
     */
    private function decodeJSON(mixed $json): object
    {
        if ($json === null || $json === 'null') {
            return (object) [];
        }

        $query = json_decode($json, false);

        if (json_last_error()) {
            throw new QBParseException('JSON parsing threw an error: ' . json_last_error_msg());
        }

        if (! is_object($query)) {
            throw new QBParseException('The query is not valid JSON');
        }

        return $query;
    }

    /**
     * @throws QBRuleException
     */
    private function getRuleValue(stdClass $rule): mixed
    {
        if (! $this->checkRuleCorrect($rule)) {
            throw new QBRuleException('The query builder rule is invalid.');
        }

        return $rule->value;
    }

    /**
     * @throws QBParseException
     */
    private function ensureFieldIsAllowed(?array $fields, string $field): void
    {
        if (is_array($fields) && ! in_array($field, $fields, true)) {
            throw new QBParseException("Field ($field) does not exist in fields list");
        }
    }

    /**
     * @param array{operator: string} $sqlOperator
     * @param array<int, mixed> $value
     * @throws QBParseException
     */
    protected function makeQueryWhenArray(
        EloquentBuilder|Builder $query,
        stdClass $rule,
        array $sqlOperator,
        array $value,
        string $condition
    ): EloquentBuilder|Builder {
        if ($sqlOperator['operator'] === 'IN' || $sqlOperator['operator'] === 'NOT IN') {
            return $this->makeArrayQueryIn($query, $rule, $sqlOperator['operator'], $value, $condition);
        }

        if ($sqlOperator['operator'] === 'BETWEEN' || $sqlOperator['operator'] === 'NOT BETWEEN') {
            return $this->makeArrayQueryBetween($query, $rule, $sqlOperator['operator'], $value, $condition);
        }

        throw new QBParseException('makeQueryWhenArray could not return a value');
    }

    /**
     * @param array{operator: string} $sqlOperator
     * @throws QBParseException
     */
    protected function makeQueryWhenNull(
        EloquentBuilder|Builder $query,
        stdClass $rule,
        array $sqlOperator,
        string $condition
    ): EloquentBuilder|Builder {
        if ($sqlOperator['operator'] === 'NULL') {
            return $query->whereNull($rule->field, $condition);
        }

        if ($sqlOperator['operator'] === 'NOT NULL') {
            return $query->whereNotNull($rule->field, $condition);
        }

        throw new QBParseException('makeQueryWhenNull was called on an SQL operator that is not null');
    }

    /**
     * @throws QBParseException
     */
    private function makeArrayQueryIn(
        EloquentBuilder|Builder $query,
        stdClass $rule,
        string $operator,
        array $value,
        string $condition
    ): EloquentBuilder|Builder {
        if ($operator === 'NOT IN') {
            return $query->whereNotIn($rule->field, $value, $condition);
        }

        return $query->whereIn($rule->field, $value, $condition);
    }

    /**
     * @throws QBParseException
     */
    private function makeArrayQueryBetween(
        EloquentBuilder|Builder $query,
        stdClass $rule,
        string $operator,
        array $value,
        string $condition
    ): EloquentBuilder|Builder {
        if (count($value) !== 2) {
            throw new QBParseException($rule->field . ' should be an array with only two items.');
        }

        if ($operator === 'NOT BETWEEN') {
            return $query->whereNotBetween($rule->field, $value, $condition);
        }

        return $query->whereBetween($rule->field, $value, $condition);
    }
}
