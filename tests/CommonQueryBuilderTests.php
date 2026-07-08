<?php

namespace timgws\tests;

use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\TestCase;
use timgws\QBParseException;
use timgws\QueryBuilderParser;
use timgws\tests\Mocks\MemoryConnection;

class CommonQueryBuilderTests extends TestCase
{
    protected string $simpleQuery = '{"condition":"AND","rules":[{"id":"price","field":"price","type":"double","operator":"less","value":"10.25"}]}';
    protected string $simpleQueryInjection = '{"condition":"ALSO","rules":[{"id":"price","field":"price","type":"double","operator":"less","value":"10.25"},{"id":"price","field":"price","type":"double","operator":"greater","value":"9.25"}]}';
    protected string $json1 = '{
       "condition":"AND",
       "rules":[
          {
             "id":"price",
             "field":"price",
             "type":"double",
             "operator":"less",
             "value":"10.25"
          },
          {
             "condition":"OR",
             "rules":[
                {
                   "id":"name",
                   "field":"name",
                   "type":"string",
                   "operator":"begins_with",
                   "value":"Thommas"
                },
                {
                   "id":"name",
                   "field":"name",
                   "type":"string",
                   "operator":"equal",
                   "value":"John Doe"
                }
             ]
          }
       ]
    }';

    protected function getParserUnderTest(?array $fields = null): QueryBuilderParser
    {
        return new QueryBuilderParser($fields);
    }

    protected function createQueryBuilder(): Builder
    {
        $connection = new MemoryConnection();

        return new Builder($connection);
    }

    protected function makeJSONForInNotInTest(string $operator = 'in'): string
    {
        return '{
           "condition":"AND",
           "rules":[
              {
                 "id":"price",
                 "field":"price",
                 "type":"double",
                 "operator":"less",
                 "value":"10.25"
              },
              {
                 "condition":"OR",
                 "rules":[{
                   "id":"category",
                   "field":"category",
                   "type":"integer",
                   "operator":"' . $operator . '",
                   "value":[
                      "1", "2"
                   ]}
                 ]
              }
           ]
        }';
    }

    protected function assertQBParseExceptionMessage(string $expectedMessage, callable $callback): void
    {
        try {
            $callback();

            $this->fail('Failed asserting that '.QBParseException::class.' was thrown.');
        } catch (QBParseException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }
}
