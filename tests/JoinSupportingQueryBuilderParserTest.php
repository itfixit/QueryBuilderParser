<?php

namespace timgws\tests;

use Carbon\Carbon;
use timgws\JoinSupportingQueryBuilderParser;
use timgws\QueryBuilderParser;

class JoinSupportingQueryBuilderParserTest extends CommonQueryBuilderTests
{
    protected function getParserUnderTest(?array $fields = null): QueryBuilderParser
    {
        return new JoinSupportingQueryBuilderParser($fields, $this->getJoinFields());
    }

    private function getJoinFields(): array
    {
        return array(
            'join1' => array(
                'from_table'      => 'master',
                'from_col'        => 'm_col',
                'to_table'        => 'subtable',
                'to_col'          => 's_col',
                'to_value_column' => 's_value',
            ),
            'join2' => array(
                'from_table'      => 'master2',
                'from_col'        => 'm2_col',
                'to_table'        => 'subtable2',
                'to_col'          => 's2_col',
                'to_value_column' => 's2_value',
                'not_exists'      => true,
            ),
            'joinwithclause' => array(
                'from_table'      => 'master',
                'from_col'        => 'm_col',
                'to_table'        => 'subtable',
                'to_col'          => 's_col',
                'to_value_column' => 's_value',
                'to_clause'       => array('othercol' => 'value'),
            ),
        );
    }

    public function testJoinWhere(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"double","input":"text","operator":"less","value":"10.25"}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` < ?)',
          $builder->toSql());
    }

    public function testJoinNotExistsWhere(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"double","input":"text","operator":"less","value":"10.25"}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` < ?)',
          $builder->toSql());
    }

    public function testJoinIn(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` in (?, ?))',
          $builder->toSql());
    }

    public function testJoinNotExistsIn(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` in (?, ?))',
          $builder->toSql());
    }

    public function testJoinNotIn(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"not_in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` not in (?, ?))',
          $builder->toSql());
    }

    // Urgh, double negative
    public function testJoinNotExistsNotIn(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"not_in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` not in (?, ?))',
          $builder->toSql());
    }

    public function testJoinBetween(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"between","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` between ? and ?)',
          $builder->toSql());
    }

    public function testJoinNotBetween(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join1","field":"join1","type":"text","input":"select","operator":"not_between","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and `s_value` not between ? and ?)',
          $builder->toSql());
    }

    public function testJoinNotExistsBetween(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"between","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` between ? and ?)',
            $builder->toSql());
    }

    // Bugfix for #14
    public function testJoinIsNull(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"is_null","value":""}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` is null)',
          $builder->toSql());
    }

    // Bugfix for #14
    public function testJoinIsNotNull(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"is_not_null","value":""}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` is not null)',
          $builder->toSql());
    }

    public function testJoinNotExistsBetweenWithThreeItems(): void
    {
        $this->assertQBParseExceptionMessage("should be an array with only two items", function (): void {
            $this->_testJoinNotExistsBetweenWithThreeItems(false);
        });
    }

    public function testJoinNotExistsNotBetweenWithThreeItems(): void
    {
        $this->assertQBParseExceptionMessage("should be an array with only two items", function (): void {
            $this->_testJoinNotExistsBetweenWithThreeItems(true);
        });
    }

    /**
      * @see testJoinNotExistsBetweenWithThreeItems()
      * @see testJoinNotExistsNotBetweenWithThreeItems()
      */
    private function _testJoinNotExistsBetweenWithThreeItems($not_between = false): void
    {
        $json_operator = ($not_between ? 'not_' : '') . 'between';
        $sql_operator = ($not_between ? 'not ' : '') . 'between';
        $json = '{"condition":"AND","rules":[{"id":"join2","field":"join2","type":"text","input":"select","operator":"'. $json_operator . '","value":["a","b","c"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where not exists (select 1 from `subtable2` where subtable2.s2_col = master2.m2_col and `s2_value` ' . $sql_operator . ' ? and ?)',
            $builder->toSql());
    }

    public function testJoinNotExistsBetweenWithFieldThatDoesNotExist(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"join4","field":"join4","type":"text","input":"select","operator":"between","value":["a","b","c"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest(array('this_field_is_allowed_but_is_not_present_in_the_json_string'));

        $this->assertQBParseExceptionMessage("does not exist in fields list", function () use ($parser, $json, $builder): void {
            $parser->parse($json, $builder);
        });
    }

    public function testJoinWithClause(): void
    {
        $json = '{"condition":"AND","rules":[{"id":"joinwithclause","field":"joinwithclause","type":"text","input":"select","operator":"in","value":["a","b"]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and (`othercol` = ?) and `s_value` in (?, ?))',
          $builder->toSql());
    }

    /**
     * JoinSupportingQueryBuilderParser always return AND even if OR is the condition
     * (Fix for bug #13)
     */
    public function testJoinWorksWithOrCondition(): void
    {
        $json = '{"condition":"OR","rules":[{"id":"joinwithclause","field":"joinwithclause","type":"double","input":"text","operator":"less","value":"10.25"},{"condition":"OR","rules":[{"id":"joinwithclause","field":"joinwithclause","type":"integer","input":"select","operator":"equal","value":"2"},{"id":"joinwithclause","field":"joinwithclause","type":"integer","input":"select","operator":"equal","value":"1"}]}]}';

        $builder = $this->createQueryBuilder();

        $parser = $this->getParserUnderTest();
        $parser->parse($json, $builder);

        $this->assertEquals('select * where exists (select 1 from `subtable` where subtable.s_col = master.m_col and (`othercol` = ?) and `s_value` < ?) or (exists (select 1 from `subtable` where subtable.s_col = master.m_col and (`othercol` = ?) and `s_value` = ?) or exists (select 1 from `subtable` where subtable.s_col = master.m_col and (`othercol` = ?) and `s_value` = ?))',
            $builder->toSql());
    }

    public function testCategoryIn(): void
    {
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($this->makeJSONForInNotInTest(), $builder);

        $this->assertEquals('select * where `price` < ? and (`category` in (?, ?))', $builder->toSql());
    }

    /**
     * Test for #21 (Cast datetimes and add 'not between' operator)
     */
    public function testDateBetween(): void
    {
        $incoming = '{ "condition": "AND", "rules": [ { "id": "dollar_amount", "field": "dollar_amount", "type": "double", "input": "number", "operator": "less", "value": "546" }, { "id": "needed_by_date", "field": "needed_by_date", "type": "date", "input": "text", "operator": "between", "value": [ "10/22/2017", "10/28/2017" ] } ], "not": false, "valid": true }';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($incoming, $builder);

        $this->assertEquals('select * where `dollar_amount` < ? and `needed_by_date` between ? and ?', $builder->toSql());

        $bindings = $builder->getBindings();
        $this->assertCount(3, $bindings);
        $this->assertEquals('546', $bindings[0]);
        $this->assertInstanceOf(Carbon::class, $bindings[1]);
        $this->assertInstanceOf(Carbon::class, $bindings[2]);
    }

    /**
     * Test for #21 (Cast datetimes and add 'not between' operator)
     */
    public function testDateNotBetween(): void
    {
        $incoming = '{ "condition": "AND", "rules": [ { "id": "needed_by_date", "field": "needed_by_date", "type": "date", "input": "text", "operator": "not_between", "value": [ "10/22/2017", "10/28/2017" ] } ], "not": false, "valid": true }';
        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();

        $qb->parse($incoming, $builder);

        $this->assertEquals('select * where `needed_by_date` not between ? and ?', $builder->toSql());

        $bindings = $builder->getBindings();
        $this->assertCount(2, $bindings);
        $this->assertInstanceOf(Carbon::class, $bindings[0]);
        $this->assertInstanceOf(Carbon::class, $bindings[1]);
        $this->assertEquals(2017, $bindings[0]->year);
        $this->assertEquals(22, $bindings[0]->day);
        $this->assertEquals(28, $bindings[1]->day);
    }
}
