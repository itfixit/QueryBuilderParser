<?php

namespace timgws\tests\Mocks;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Processors\Processor;

class MemoryConnection extends Connection
{
    public function __construct()
    {
        parent::__construct(static fn () => null, '', '', []);
    }

    protected function getDefaultQueryGrammar(): Grammar
    {
        return new MySqlGrammar($this);
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new MySqlProcessor();
    }

    public function getQueryGrammar(): Grammar
    {
        if (! isset($this->queryGrammar)) {
            $this->queryGrammar = new MySqlGrammar($this);
        }

        return $this->queryGrammar;
    }

    public function getPostProcessor(): Processor
    {
        if (! isset($this->postProcessor)) {
            $this->postProcessor = new MySqlProcessor();
        }

        return $this->postProcessor;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix ?? '';
    }
}
