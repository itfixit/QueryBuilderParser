<?php
namespace timgws\tests\Mocks;


/**
 * Class Connection
 *
 * Connection, with mocked collection.
 *
 * @package timgws\tests\Mocks
 */
class Connection extends \Jenssegers\Mongodb\Connection implements \Jenssegers\Mongodb\Contracts\ConnectionContract
{
    private object|null $mockedCollection = null;

    /**
     * Get a MongoDB collection.
     *
     * @param  string   $name
     * @return Collection
     */
    public function setCollection(string $name): object
    {
        $this->mockedCollection = \Mockery::mock('MongoCollection');

        return $this->mockedCollection;
    }

    public function getCollection(string $name): object
    {
        if (is_null($this->mockedCollection)) {
            return $this->setCollection($name);
        }

        return $this->mockedCollection;
    }
}
