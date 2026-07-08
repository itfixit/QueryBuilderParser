<?php

namespace timgws\tests\mongodb;

use PHPUnit\Framework\TestCase;

if (class_exists(\Jenssegers\Mongodb\Connection::class)) {
    class TestMongoDB extends TestCase
    {
        private string $simpleQuery = '{"condition":"AND","rules":[{"id":"price","field":"price","type":"double","operator":"less","value":"10.25"}]}';

        private \timgws\tests\Mocks\Connection $mockConnection;

        private function getParserUnderTest(): \timgws\QueryBuilderParser
        {
            return new \timgws\QueryBuilderParser();
        }

        private function getOptions(): array
        {
            return [
                'typeMap' => [
                    'root' => 'array',
                    'document' => 'array',
                ],
            ];
        }

        public function testSimpleQuery(): void
        {
            $this->mockConnection = new \timgws\tests\Mocks\Connection();
            $builder = new \Jenssegers\Mongodb\Query\Builder($this->mockConnection, new \Jenssegers\Mongodb\Query\Processor());
            $builder = $builder->from('tim');
            $qb = $this->getParserUnderTest();

            $qb->parse($this->simpleQuery, $builder);

            $wheres = [
                'price' => [
                    '$lt' => 10.25,
                ],
            ];

            $mock = $this->mockConnection->getCollection('');
            $mock->shouldReceive('find')
                ->once()
                ->with($wheres, $this->getOptions())
                ->andReturn(new \ArrayIterator([]));

            $builder->get();
        }

        public function testManyNestedQuery(): void
        {
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

            $this->mockConnection = new \timgws\tests\Mocks\Connection();
            $builder = new \Jenssegers\Mongodb\Query\Builder($this->mockConnection, new \Jenssegers\Mongodb\Query\Processor());
            $builder = $builder->from('tim');
            $qb = $this->getParserUnderTest();

            $qb->parse($json, $builder);

            $wheres = json_decode('{"$and":[{"price":{"$lt":"10.25"}},{"$and":[{"category":{"$in":["1","2"]}},{"$or":[{"name":"dgfssdfg"},{"name":{"$ne":"dgfssdfg"}},{"$and":[{"name":"sadf"},{"name":"sadf"}]}]}]}]}', true, 512, JSON_THROW_ON_ERROR);

            $mock = $this->mockConnection->getCollection('');
            $mock->shouldReceive('find')
                ->once()
                ->with($wheres, $this->getOptions())
                ->andReturn(new \ArrayIterator([]));

            $builder->get();
        }
    }
} else {
    class TestMongoDB extends TestCase
    {
        public function testMongoDbPackageIsOptional(): void
        {
            $this->markTestSkipped('jenssegers/mongodb is not installed.');
        }
    }
}
