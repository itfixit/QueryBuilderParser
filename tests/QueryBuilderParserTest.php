<?php

namespace timgws\tests;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class QueryBuilderParserTest extends CommonQueryBuilderTests
{
    public function testSimpleEmptyQuery(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse("{}", $builder);
        $this->assertEquals('select *', $builder->toSql());
    }

    public function testSimpleQuery(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->simpleQuery, $builder);

        $this->assertEquals('select * where `price` < ?', $builder->toSql());
    }

    public function testSimpleQueryNoInjection(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("Condition can only be one of", function () use ($qb, $builder): void {
            $qb->parse($this->simpleQueryInjection, $builder);
        });
    }

    public function testMoreComplexQuery(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->json1, $builder);

        $this->assertEquals('select * where `price` < ? and (`name` LIKE ? or `name` = ?)', $builder->toSql());
    }

    public function testBetterThenTheLastTime(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $json = '{"condition":"AND","rules":[{"id":"anchor_text","field":"anchor_text","type":"string","input":"text","operator":"contains","value":"www"},{"condition":"OR","rules":[{"id":"citation_flow","field":"citation_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"},{"id":"trust_flow","field":"trust_flow","type":"double","input":"text","operator":"greater_or_equal","value":"30"}]}]}';
        $qb->parse($json, $builder);

        $this->assertEquals('select * where `anchor_text` LIKE ? and (`citation_flow` >= ? or `trust_flow` >= ?)', $builder->toSql());
    }

    public function testCategoryIn(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest(), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` in (?, ?))', $builder->toSql());
    }

    public function testCategoryNotIn(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest('not_in'), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` not in (?, ?))', $builder->toSql());
    }

    public function testCategoryInvalidArray(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("should not be an array, but it is", function () use ($qb, $builder): void {
            $qb->parse($this->makeJSONForInNotInTest('contains'), $builder);
        });
    }

    public function testManyNestedQuery(): void
    {
        // $('#builder-basic').queryBuilder('setRules', /** This object */);
        $json = '{
           "condition":"AND",
           "rules":[
              {
                 "id":"price",
                 "field":"price",
                 "type":"double",
                 "input":"text",
                 "operator":"less",
                 "value":"10.25"
              }, {
                 "condition":"AND",
                 "rules":[
                    {
                       "id":"category",
                       "field":"category",
                       "type":"integer",
                       "input":"select",
                       "operator":"in",
                       "value":[
                          "1", "2"
                       ]
                    }, {
                       "condition":"OR",
                       "rules":[
                          {
                             "id":"name",
                             "field":"name",
                             "type":"string",
                             "input":"text",
                             "operator":"equal",
                             "value":"dgfssdfg"
                          }, {
                             "id":"name",
                             "field":"name",
                             "type":"string",
                             "input":"text",
                             "operator":"not_equal",
                             "value":"dgfssdfg"
                          }, {
                             "condition":"AND",
                             "rules":[
                                {
                                   "id":"name",
                                   "field":"name",
                                   "type":"string",
                                   "input":"text",
                                   "operator":"equal",
                                   "value":"sadf"
                                },
                                {
                                   "id":"name",
                                   "field":"name",
                                   "type":"string",
                                   "input":"text",
                                   "operator":"equal",
                                   "value":"sadf"
                                }
                             ]
                          }
                       ]
                    }
                 ]
              }
           ]
        }';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($json, $builder);

        //$this->assertEquals('select * where `price` < ? AND (`category` in (?, ?) OR (`name` = ? AND (`name` = ?)))', $builder->toSql());
        $this->assertEquals('select * where `price` < ? and (`category` in (?, ?) and (`name` = ? or `name` != ? or (`name` = ? and `name` = ?)))', $builder->toSql());
        //$this->assertEquals('/* This test currently fails. This should be fixed. */', $builder->toSql());
    }

    public function testJSONParseException(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("JSON parsing threw an error", function () use ($qb, $builder): void {
            $qb->parse('{}]JSON', $builder);
        });
    }

    private function getBetweenJSON($hasTwoValues = true, $isnot = false): string
    {
        $v = '"2","3"'.((!$hasTwoValues ? ',"3"' : ''));
        $o = ( $isnot ? "not_" : "" ) . 'between';

        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"' . $o . '","value":['.$v.']}]}';

        return $json;
    }

    public function testBetweenOperator(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(), $builder);
        $this->assertEquals('select * where `price` between ? and ?', $builder->toSql());
    }

    public function testNotBetweenOperator(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->getBetweenJSON(true, true), $builder);
        $this->assertEquals('select * where `price` not between ? and ?', $builder->toSql());
    }

    private function noRulesOrEmptyRules($hasRules = false): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $rules = '{"condition":"AND"}';
        if ($hasRules) {
            $rules = '{"condition":"AND","rules":[]}';
        }

        $qb->parse($rules, $builder);

        $this->assertEquals('select *', $builder->toSql());
    }

    public function testNoRulesNoQuery(): void
    {
        $this->noRulesOrEmptyRules();
        $this->noRulesOrEmptyRules(true);
    }

    #[DataProvider('operatorCoverageProvider')]
    public function testOperatorCoverage($field, $type, $operator, $value, $expectedSql, array $expectedBindings): void
    {
        $json = json_encode([

            'condition' => 'AND',
            'rules' => [
                [
                    'id' => $field,
                    'field' => $field,
                    'type' => $type,
                    'input' => 'text',
                    'operator' => $operator,
                    'value' => $value,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($json, $builder);

        $this->assertEquals($expectedSql, $builder->toSql());
        $this->assertEquals($expectedBindings, $builder->getBindings());
    }

    public static function operatorCoverageProvider(): array
    {
        return [
            'less_or_equal' => ['price', 'double', 'less_or_equal', '10.25', 'select * where `price` <= ?', ['10.25']],
            'greater' => ['price', 'double', 'greater', '10.25', 'select * where `price` > ?', ['10.25']],
            'not_begins_with' => ['name', 'string', 'not_begins_with', 'Tim', 'select * where `name` NOT LIKE ?', ['Tim%']],
            'not_contains' => ['name', 'string', 'not_contains', 'Tim', 'select * where `name` NOT LIKE ?', ['%Tim%']],
            'not_ends_with' => ['name', 'string', 'not_ends_with', 'Tim', 'select * where `name` NOT LIKE ?', ['%Tim']],
            'is_not_empty' => ['name', 'string', 'is_not_empty', 'ignored', 'select * where `name` != ?', ['']],
            'is_not_null' => ['name', 'string', 'is_not_null', null, 'select * where `name` is not null', []],
        ];
    }

    public function testValueBecomesNull(): void
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_null","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
        $this->assertEquals('select * where `price` is null', $builder->toSql());
    }

    public function testBothValuesBecomesNull(): void
    {
        $v = '1.23';
        $json = '{"condition":"OR","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_null","value":['.$v.']},{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_not_null","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
        $this->assertEquals('select * where `price` is null or `price` is not null', $builder->toSql());
    }

    public function testValueBecomesEmpty(): void
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_empty","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(1, $sqlBindings);
        $this->assertSame('', $sqlBindings[0]);
    }

    public function testValueIsValid(): void
    {
        $v = '1.23';
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"is_truely_empty","value":['.$v.']}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
    }

    public function testParseAcceptsEloquentBuilder(): void
    {
        $builder = new EloquentBuilder($this->createQueryBuilder());
        $qb = $this->getParserUnderTest();

        $qb->parse($this->simpleQuery, $builder);

        $this->assertEquals('select * where `price` < ?', $builder->getQuery()->toSql());
        $this->assertEquals(['10.25'], $builder->getQuery()->getBindings());
    }

    private function beginsOrEndsWithTest($begins = 'begins', $not = false): void
    {
        $operator = (!$not ? '' : 'not_') . $begins . '_with';
        $like = $not ? 'NOT LIKE' : 'LIKE';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $json = '{"condition":"AND","rules":[{"id":"anchor_text","field":"anchor_text","type":"string","input":"text","operator":"' . $operator . '","value":"www"}]}';
        $qb->parse($json, $builder);

        $bindings_are = $begins === 'begins' ? ['www%'] : ['%www'];
        if ($begins === 'begins') {
            $bindings_are = ['www%'];
        } else {
            $bindings_are = ['%www'];
        }

        $this->assertEquals('select * where `anchor_text` ' . $like . ' ?', $builder->toSql());
        $this->assertEquals($bindings_are, $builder->getBindings());
    }

    public function testBeginsWith(): void
    {
        $this->beginsOrEndsWithTest('begins');
    }

    public function testBeginsNotWith(): void
    {
        $this->beginsOrEndsWithTest('begins', true);
    }

    public function testEndsWith(): void
    {
        $this->beginsOrEndsWithTest('ends');
    }

    public function testEndsNotWith(): void
    {
        $this->beginsOrEndsWithTest('ends', true);
    }

    public function testInputIsNotArray(): void
    {
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"equal","value":["tim","simon"]}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("should not be an array, but it is", function () use ($qb, $builder, $json): void {
            $qb->parse($json, $builder);
        });
    }

    public function testRuleHasInputAndType(): void
    {
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","inputs":"text",'
            .'"operator":"is_truely_empty","value":["1.23"]}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);

        $sqlBindings = $builder->getBindings();
        $this->assertCount(0, $sqlBindings);
    }

    public function testFieldNotInittedNotAllowed(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest(array('this_field_is_allowed_but_is_not_present_in_the_json_string'));

        $this->assertQBParseExceptionMessage("does not exist in fields list", function () use ($qb, $builder): void {
            $qb->parse($this->json1, $builder);
        });
    }

    public function testBetweenMustBeArray(): void
    {
        $json = $this->_buildJsonTestForBetween(true);

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("should be an array, but it isn't", function () use ($qb, $builder, $json): void {
            $qb->parse($json, $builder);
        });
    }

    public function testThrowExceptionInvalidJSON(): void
    {
        $json = $this->_buildJsonTestForBetween(false /*invalid json */);

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("JSON parsing threw an error", function () use ($qb, $builder, $json): void {
            $qb->parse($json, $builder);
        });
    }

    /**
     * Build a JSON string
     *
     * @see testBetweenMustBeArray
     * @see testThrowExceptionInvalidJSON
     * @param $validJSON
     * @return string
     */
    private function _buildJsonTestForBetween($validJSON): string
    {
        $json = '{"condition":"AND","rules":['
            .'{"id":"price","field":"price","type":"double","input":"text",'
            .'"operator":"between","value":"1"}]}';

        if (!$validJSON) {
            $json .= '[';
        }

        return $json;
    }

    /**
     * This is a similar test to testBetweenOperator, however, this will throw an exception if
     * there is more then two values for the 'BETWEEN' operator.
     */
    public function testBetweenOperatorThrowsException(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("should be an array with only two items.", function () use ($qb, $builder): void {
            $qb->parse($this->getBetweenJSON(false), $builder);
        });
    }

    /**
     * @see testBetweenOperatorThrowsException
     */
    public function testNotBetweenOperatorThrowsException(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("should be an array with only two items.", function () use ($qb, $builder): void {
            $qb->parse($this->getBetweenJSON(false, true), $builder);
        });
    }

    /**
     * QBP can only accept objects, not arrays.
     *
     * Make sure an exception is thrown if the JSON is valid, but after parsing,
     * we don't get back an object
     */
    public function testArrayDoesNotParse(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("The query is not valid JSON", function () use ($qb, $builder): void {
            $qb->parse('["test1","test2"]', $builder);
        });
    }

    /**
     * Just a quick test to make sure that QBP::isNested returns false when
     * there is no nested rules inside the rules...
     */
    public function testIsNestedReturnsFalseWhenEmptyNestedRules(): void
    {
        $some_json_input = '{
       "condition":"AND",
       "rules":[{
             "condition":"OR",
             "rules":[]
          }]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($some_json_input, $builder);
        $this->assertEquals('select *', $builder->toSql());
    }

    public function testQueryContains(): void
    {
        $some_json_input = '{"condition":"AND","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"Johnny"},{"condition":"AND","rules":[{"id":"category","field":"category","type":"integer","input":"select","operator":"equal","value":"2"},{"id":"in_stock","field":"in_stock","type":"integer","input":"radio","operator":"equal","value":"1"},{"condition":"OR","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"begins_with","value":"tim"},{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"timgws"}]},{"condition":"OR","rules":[{"id":"name","field":"name","type":"string","input":"text","operator":"ends_with","value":"builder"},{"id":"name","field":"name","type":"string","input":"text","operator":"contains","value":"qbp"},{"id":"name","field":"name","type":"string","input":"text","operator":"begins_with","value":"query"}]}]}]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($some_json_input, $builder);

        $expected_sql = 'select * where `name` like ? and (`category` = ? and `in_stock` = ? and (`name` like ? or `name` like ?) and (`name` like ? or `name` like ? or `name` like ?))';
        $sql = $builder->toSql();

        $this->assertEquals(strtolower($expected_sql), strtolower($sql));
    }

    /**
     * QBP should successfully parse OR conditions.
     */
    public function testNestedOrGroup(): void
    {
        $json = '{"condition":"AND",
        "rules":[
        {"id":"email_pool","field":"email_pool","type":"string","input":"select","operator":"contains","value":["Fundraising"]},
        {"condition":"OR","rules":[
            {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
            {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Banbury"]}]}]}';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);
        $this->assertEquals('select * where `email_pool` LIKE ? and (`geo_constituency` in (?) or `geo_constituency` in (?))',
            $builder->toSql());
    }


    /**
     * Null check is not using isnull function instead checking = 'NULL'
     *
     * Tests for #10
     */
    public function testIsNullBecomesNullInQuery(): void
    {
        $json = '{
            "condition": "OR",
                "rules": [
                {
                    "id": "t_o",
                    "field": "t_o",
                    "type": "integer",
                    "input": "text",
                    "operator": "equal",
                    "value": "0"
                },
                {
                    "id": "t_o",
                    "field": "t_o",
                    "type": "integer",
                    "input": "text",
                    "operator": "is_null",
                    "value": null
                }
                ]
        }';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $qb->parse($json, $builder);
        $this->assertEquals('select * where `t_o` = ? or `t_o` is null',
            $builder->toSql());
        $bindings_are = ['0'];
        $this->assertEquals($bindings_are, $builder->getBindings());
    }

    /**
     * @throws \timgws\QBParseException
     */
    public function testIncorrectCondition(): void
    {
        $json = '{"condition":null,"rules":[
            {"condition":"AXOR","rules":[
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"is_empty","value":["Aberdeen South"]},
                {"condition":"AXOR","rules":[
                    {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]},
                    {"id":"geo_constituency","field":"geo_constituency","type":"string","input":"select","operator":"in","value":["Aberdeen South"]}
                ]}
            ]}
        ]}';

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $this->assertQBParseExceptionMessage("Condition can only be one of: 'and', 'or'.", function () use ($qb, $builder, $json): void {
            $qb->parse($json, $builder);
        });
    }

    public function testCleanCorrectlyCleansName(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->clean('name');
        $qb->clean('name', function () {
            return 'Tim';
        });

        $qb->parse($this->json1, $builder);

        $bindings = $builder->getRawBindings();
        $this->assertEquals('select * where `price` < ? and (`name` LIKE ? or `name` = ?)', $builder->toSql());

        if (is_array($bindings)) {
            $this->assertEquals(['10.25', 'Tim%', 'Tim'], $bindings['where']);
        }

        $this->assertQBParseExceptionMessage('Field name already has a clean callback set.', function () use ($qb): void {
            $qb->clean('name', function ($value) {
                return 'Thomas';
            });
        });
    }

    public function testNoJsonProvided(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse('null', $builder);

        $this->assertEquals('select *', $builder->toSql());
    }

}
