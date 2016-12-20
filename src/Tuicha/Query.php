<?php

namespace Tuicha;

use Tuicha;
use IteratorIterator;
use MongoDB\Driver;
use MongoDB\Driver\Command;

class Query extends Cursor
{
    protected $connection;
    protected $query;
    protected $fields;
    protected $metadata;
    protected $namespace;
    protected $class;
    protected $filters = [];

    protected function doQuery()
    {
        $query = new Driver\Query($this->query, [
            'selector' => $this->fields,
        ]);

        $cursor = $this->connection['connection']
            ->executeQuery($this->connection['collection'], $query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'stdclass', 'array' => 'array']);
        $this->setResultSet(new IteratorIterator($cursor));
    }

    public function __construct($class, $query, $fields)
    {
        $this->class      = $class;
        $this->metadata   = Metadata::of($class);
        $this->query      = $query;
        $this->fields     = $fields;
        $this->connection = $this->metadata->getConnection();
    }

    public function first()
    {
        $query = new Driver\Query($this->query, [
            'selector' => $this->fields,
            'limit' => 1,
        ]);

        $cursor = $this->connection['connection']->executeQuery($this->connection['collection'], $query);
        $cursor->setTypeMap(['root' => 'array', 'document' => 'stdclass', 'array' => 'array']);

        $result = $cursor->toArray();

        if (empty($result)) {
            return NULL;
        }

        return $this->metadata->newInstance($result[0]);
    }

    public function filter(Callable $fnc)
    {
        $this->filters[] = $fnc;
    }

    public function count()
    {
        return Tuicha::command([
            'count' => $this->metadata->GetCollectionName(),
            'query' => $this->query,
        ], $this->connection)->toArray()[0]->n;
    }
}
