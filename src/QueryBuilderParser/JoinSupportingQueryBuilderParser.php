<?php

namespace timgws;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use stdClass;

class JoinSupportingQueryBuilderParser extends QueryBuilderParser
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $joinFields;

    /**
     * @param array<int, string>|null $fields
     * @param array<string, array<string, mixed>>|null $joinFields
     */
    public function __construct(?array $fields = null, ?array $joinFields = null)
    {
        parent::__construct($fields);
        $this->joinFields = $joinFields;
    }

    /**
     * @throws QBParseException
     */
    protected function makeQuery(EloquentBuilder|Builder $query, stdClass $rule, string $queryCondition = 'AND'): EloquentBuilder|Builder
    {
        /*
         * Ensure that the value is correct for the rule, return query on exception
         */
        try {
            $value = $this->getValueForQueryFromRule($rule);
        } catch (QBRuleException) {
            return $query;
        }

        $condition = strtolower($queryCondition);

        if (is_array($this->joinFields) && array_key_exists($rule->field, $this->joinFields)) {
            return $this->buildSubclauseQuery($query, $rule, $value, $condition);
        }

        return $this->convertIncomingQBtoQuery($query, $rule, $value, $condition);
    }

    /**
     * Build a subquery clause if there are join fields that have been specified.
     */
    private function buildSubclauseQuery(EloquentBuilder|Builder $query, stdClass $rule, mixed $value, string $condition): EloquentBuilder|Builder
    {
        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOp = $this->operator_sql[$rule->operator];
        $operator = $sqlOp['operator'];
        $requireArray = $this->operatorRequiresArray($operator);

        $subclause = $this->joinFields[$rule->field];
        $subclause['operator'] = $operator;
        $subclause['value'] = $value;
        $subclause['require_array'] = $requireArray;

        $not = array_key_exists('not_exists', $subclause) && $subclause['not_exists'];

        return $query->whereExists(function (Builder $query) use ($subclause): void {
            $q = $query->selectRaw(1)
                ->from($subclause['to_table'])
                ->whereRaw(
                    $subclause['to_table'] . '.' . $subclause['to_col'] . ' = ' . $subclause['from_table'] . '.' . $subclause['from_col']
                );

            if (array_key_exists('to_clause', $subclause)) {
                $q->where($subclause['to_clause']);
            }

            // noinspection PhpUnhandledExceptionInspection
            $this->buildSubclauseInnerQuery($subclause, $q);
        }, $condition, $not);
    }

    /**
     * The inner query for a subclause.
     *
     * @throws QBParseException
     */
    private function buildSubclauseInnerQuery(array $subclause, Builder $query): void
    {
        if ($subclause['require_array']) {
            $this->buildRequireArrayQuery($subclause, $query);
            return;
        }

        if ($subclause['operator'] === 'NULL' || $subclause['operator'] === 'NOT NULL') {
            $this->buildSubclauseWithNull($subclause, $query, $subclause['operator'] === 'NOT NULL');
            return;
        }

        $this->buildRequireNotArrayQuery($subclause, $query);
    }

    /**
     * The inner query for a subclause when an array is required.
     *
     * @throws QBParseException when an invalid array is passed.
     */
    private function buildRequireArrayQuery(array $subclause, Builder $query): void
    {
        if ($subclause['operator'] === 'IN') {
            $query->whereIn($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] === 'NOT IN') {
            $query->whereNotIn($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] === 'BETWEEN') {
            if (count($subclause['value']) !== 2) {
                throw new QBParseException($subclause['to_value_column'] . ' should be an array with only two items.');
            }

            $query->whereBetween($subclause['to_value_column'], $subclause['value']);
        } elseif ($subclause['operator'] === 'NOT BETWEEN') {
            if (count($subclause['value']) !== 2) {
                throw new QBParseException($subclause['to_value_column'] . ' should be an array with only two items.');
            }

            $query->whereNotBetween($subclause['to_value_column'], $subclause['value']);
        }

    }

    /**
     * The inner query for a subclause when an array is not required.
     */
    private function buildRequireNotArrayQuery(array $subclause, Builder $query): void
    {
        $query->where($subclause['to_value_column'], $subclause['operator'], $subclause['value']);
    }

    /**
     * The inner query for a subclause when the operator is NULL.
     */
    private function buildSubclauseWithNull(array $subclause, Builder $query, bool $isNotNull = false): void
    {
        if ($isNotNull) {
            $query->whereNotNull($subclause['to_value_column']);
            return;
        }

        $query->whereNull($subclause['to_value_column']);
    }
}
