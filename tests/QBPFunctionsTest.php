<?php

namespace timgws\tests;

use Carbon\Carbon;
use JsonException;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use timgws\QueryBuilderParser;

/**
 * Class QBPFunctionsTest
 *
 * Uses reflection to get to one particularly
 *
 * @package timgws\tests
 */
class QBPFunctionsTest extends CommonQueryBuilderTests
{
    protected static function getMethod(string $name): ReflectionMethod
    {
        try {
            $class = new ReflectionClass(QueryBuilderParser::class);
            $method = $class->getMethod($name);
        } catch (\ReflectionException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        // noinspection PhpExpressionResultUnusedInspection
        $method->setAccessible(true);

        return $method;
    }

    public function testOperatorNotValid(): void
    {
        $method = self::getMethod('makeQueryWhenArray');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        try {
            $rule = json_decode($this->makeJSONForInNotInTest('contains'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $this->assertQBParseExceptionMessage('makeQueryWhenArray could not return a value', function () use ($method, $qb, $builder, $rule): void {
            $method->invokeArgs($qb, [
                $builder,
                $rule->rules[1],
                ['operator' => 'CONTAINS'],
                ['AND'],
                'AND',
            ]);
        });
    }

    public function testOperatorNotValidForNull(): void
    {
        $method = self::getMethod('makeQueryWhenNull');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        try {
            $rule = json_decode($this->makeJSONForInNotInTest('contains'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $this->assertQBParseExceptionMessage('makeQueryWhenNull was called on an SQL operator that is not null', function () use ($method, $qb, $builder, $rule): void {
            $method->invokeArgs($qb, [
                $builder,
                $rule->rules[1],
                ['operator' => 'CONTAINS'],
                'AND',
            ]);
        });
    }

    public function testDate(): void
    {
        $method = self::getMethod('convertDatetimeToCarbon');

        $qb = $this->getParserUnderTest();

        /** @var Carbon $carbonDate */
        $carbonDate = $method->invokeArgs($qb, ['2010-12-11']);

        $this->assertEquals('2010', $carbonDate->year);
        $this->assertEquals('12', $carbonDate->month);
    }

    public function testDateArray(): void
    {
        $method = self::getMethod('convertDatetimeToCarbon');

        $qb = $this->getParserUnderTest();

        /** @var Carbon[] $carbonDate */
        $carbonDates = $method->invokeArgs($qb, [['2010-12-11', '2001-01-02']]);

        $this->assertCount(2, $carbonDates);
        $this->assertEquals('2010', $carbonDates[0]->year);
        $this->assertEquals('2001', $carbonDates[1]->year);
    }
}
