<?php

namespace timgws;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use stdClass;

class QueryBuilderParser
{
    use QBPFunctions;

    /**
     * The fields (if any) that we allow to filter on using QBP
     */
    protected ?array $fields;

    /**
     * A list of all the callbacks that can be called to cleanse provided values from QBP
     *
     * @var array<string, callable>
     */
    private array $cleanFieldCallbacks = [];

    /**
     * @param array<int, string>|null $fields a list of all the fields that are allowed to be filtered by the QueryBuilder
     */
    public function __construct(?array $fields = null)
    {
        $this->fields = $fields;
    }

    /**
     * QueryBuilderParser's parse function!
     *
     * Build a query based on JSON that has been passed into the function, onto the builder passed into the function.
     *
     * @param mixed $json
     * @throws QBParseException
     */
    public function parse(mixed $json, EloquentBuilder|Builder $querybuilder): EloquentBuilder|Builder
    {
        $query = $this->decodeJSON($json);

        if (! isset($query->rules) || ! is_array($query->rules)) {
            return $querybuilder;
        }

        if (count($query->rules) < 1) {
            return $querybuilder;
        }

        return $this->loopThroughRules($query->rules, $querybuilder, $query->condition ?? 'AND');
    }

    /**
     * Called by parse, loops through all the rules to find out if nested or not.
     *
     * @param array<int, stdClass> $rules
     * @throws QBParseException
     */
    protected function loopThroughRules(array $rules, EloquentBuilder|Builder $querybuilder, ?string $queryCondition = 'AND'): EloquentBuilder|Builder
    {
        foreach ($rules as $rule) {
            /*
             * If makeQuery does not see the correct fields, it will return the QueryBuilder without modifications
             */
            if ($this->isNested($rule)) {
                $querybuilder = $this->createNestedQuery($querybuilder, $rule, $queryCondition);
            } else {
                $querybuilder = $this->makeQuery($querybuilder, $rule, $queryCondition);
            }
        }

        return $querybuilder;
    }

    /**
     * Determine if a particular rule is actually a group of other rules.
     */
    protected function isNested(stdClass $rule): bool
    {
        return isset($rule->rules) && is_array($rule->rules) && count($rule->rules) > 0;
    }

    /**
     * Create nested queries
     *
     * When a rule is actually a group of rules, we want to build a nested query with the specified condition (AND/OR)
     *
     * @throws QBParseException
     */
    protected function createNestedQuery(EloquentBuilder|Builder $querybuilder, stdClass $rule, ?string $condition = null): EloquentBuilder|Builder
    {
        $condition ??= $rule->condition;
        $condition = $this->validateCondition($condition);

        return $querybuilder->whereNested(function ($query) use ($rule): void {
            foreach ($rule->rules as $loopRule) {
                $function = $this->isNested($loopRule) ? 'createNestedQuery' : 'makeQuery';
                $this->{$function}($query, $loopRule, $rule->condition);
            }
        }, $condition);
    }

    /**
     * Check if a given rule is correct.
     *
     * Just before making a query for a rule, we want to make sure that the field, operator and value are set
     */
    protected function checkRuleCorrect(stdClass $rule): bool
    {
        if (! isset($rule->operator, $rule->id, $rule->field, $rule->type)) {
            return false;
        }

        if (! isset($this->operators[$rule->operator])) {
            return false;
        }

        return true;
    }

    /**
     * Give back the correct value when we don't accept one.
     */
    protected function operatorValueWhenNotAcceptingOne(stdClass $rule): ?string
    {
        if ($rule->operator === 'is_empty' || $rule->operator === 'is_not_empty') {
            return '';
        }

        return null;
    }

    /**
     * Ensure that the value for a field is correct.
     *
     * Append/Prepend values for SQL statements, etc.
     *
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     * @throws QBParseException
     */
    protected function getCorrectValue(mixed $operator, stdClass $rule, mixed $value): mixed
    {
        $field = $rule->field;
        $sqlOperator = $this->operator_sql[$rule->operator];
        $requireArray = $this->operatorRequiresArray($operator);

        $value = $this->enforceArrayOrString($requireArray, $value, $field);

        /*
         *  Turn datetime into Carbon object so that it works with "between" operators etc.
         */
        if ($rule->type === 'date') {
            $value = $this->convertDatetimeToCarbon($value);
        }

        return $this->appendOperatorIfRequired($requireArray, $value, $sqlOperator);
    }

    /**
     * makeQuery: The money maker!
     *
     * Take a particular rule and make build something that the QueryBuilder would be proud of.
     *
     * Make sure that all the correct fields are in the rule object then add the expression to
     * the query that was given by the user to the QueryBuilder.
     *
     * @throws QBParseException
     */
    protected function makeQuery(EloquentBuilder|Builder $query, stdClass $rule, string $queryCondition = 'AND'): EloquentBuilder|Builder
    {
        /*
         * Ensure that the value is correct for the rule, return query on exception
         */
        $this->validateCondition($queryCondition);

        try {
            $value = $this->getValueForQueryFromRule($rule);
        } catch (QBRuleException) {
            return $query;
        }

        return $this->convertIncomingQBtoQuery($query, $rule, $value, $queryCondition);
    }

    /**
     * Convert an incoming rule from jQuery QueryBuilder to the Eloquent Querybuilder
     *
     * (This used to be part of makeQuery, where the name made sense, but I pulled it
     * out to reduce some duplicated code inside JoinSupportingQueryBuilder)
     *
     * @param mixed $value the value that needs to be queried in the database.
     * @throws QBParseException
     */
    protected function convertIncomingQBtoQuery(
        EloquentBuilder|Builder $query,
        stdClass $rule,
        mixed $value,
        string $queryCondition = 'AND'
    ): EloquentBuilder|Builder {
        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOperator = $this->operator_sql[$rule->operator];
        $operator = $sqlOperator['operator'];
        $condition = strtolower($queryCondition);

        if ($this->operatorRequiresArray($operator)) {
            return $this->makeQueryWhenArray($query, $rule, $sqlOperator, $value, $condition);
        }

        if ($this->operatorIsNull($operator)) {
            return $this->makeQueryWhenNull($query, $rule, $sqlOperator, $condition);
        }

        return $query->where($rule->field, $sqlOperator['operator'], $value, $condition);
    }

    /**
     * Add a filter for cleaning values that are inputted from a QueryBuilder (eg, for ACL)
     *
     * @param callable|null $callback
     * @throws QBParseException
     */
    public function clean(string $field, ?callable $callback = null): self
    {
        if (isset($this->cleanFieldCallbacks[$field])) {
            throw new QBParseException("Field $field already has a clean callback set.");
        }

        if ($callback === null) {
            return $this;
        }

        $this->cleanFieldCallbacks[$field] = $callback;

        return $this;
    }

    /**
     * Ensure that the value is correct for the rule, try and set it if it's not.
     *
     * @throws QBRuleException
     * @throws QBParseException
     */
    protected function getValueForQueryFromRule(stdClass $rule): mixed
    {
        /*
         * Make sure most of the common fields from the QueryBuilder have been added.
         */
        if (isset($rule->field, $this->cleanFieldCallbacks[$rule->field])) {
            $rule->value = call_user_func($this->cleanFieldCallbacks[$rule->field], $rule->value);
        }

        $value = $this->getRuleValue($rule);

        /*
         * The field must exist in our list.
         */
        $this->ensureFieldIsAllowed($this->fields, $rule->field);

        /*
         * If the SQL Operator is set not to have a value, make sure that we set the value to null.
         */
        if ($this->operators[$rule->operator]['accept_values'] === false) {
            return $this->operatorValueWhenNotAcceptingOne($rule);
        }

        /*
         * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
         * into on one that we can use inside the SQL query
         */
        $sqlOperator = $this->operator_sql[$rule->operator];
        $operator = $sqlOperator['operator'];

        /*
         * \o/ Ensure that the value is an array only if it should be.
         */
        return $this->getCorrectValue($operator, $rule, $value);
    }
}
