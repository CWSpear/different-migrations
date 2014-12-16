<?php namespace CWSpear\Different;

use Phinx\Migration\Manager;
use ArrayObject;

class SchemaManager
{
    protected $defaultCol = [
        'limit'      => null,
        'null'       => false,
        'default'    => null,
        'identity'   => false,
        'after'      => null,
        'update'     => null,
        'precision'  => null,
        'scale'      => null,
        'comment'    => null,
        'signed'     => true,
        'properties' => [],
    ];

    public function __construct(Manager $manager, $environment)
    {
        $this->adapter = $manager->getEnvironment($environment)->getAdapter();
        $this->manager = $manager;
    }

    public function getTableSchema($table)
    {
        $cols = $this->adapter->getColumns($table);
        $columns = [];
        foreach ($cols as $col) {
            $array = [];

            // anything that has a getter of getSomething
            // or isSomething is a property we want to extract.
            // this loop does that for us
            foreach (get_class_methods($col) as $method) {
                // match methods such as getSomething() and isSomething()
                $getterRegex = '/^(?:get|is)([A-Z])/';
                if (preg_match($getterRegex, $method)) {
                    // we want the property names to be without get/is,
                    // i.e. getSomething -> something
                    $array[preg_replace_callback($getterRegex, function ($matches) {
                        return strtolower($matches[1]);
                    }, $method)] = $col->{$method}();
                }
            }
        
            // remove fields that are defaults
            $diffed = $this->diffSchema($array, $this->defaultCol);

            // remove name from the array and make it the key
            $name = $diffed['name'];
            unset($diffed['name']);
            $columns[$name] = $diffed;
        }

        // this (indexes, FKs) all may only work on MySQL...?
        $rawForeignKeys = $this->adapter->getForeignKeys($table);
        $foreignKeys = [];
        $fkNames = [];
        foreach ($rawForeignKeys as $foreignKey) {
            $foreignKeys[] = [
                'local_column'  => $foreignKey['columns'][0],
                'foreign_table' => $foreignKey['referenced_table'],
                'foreign_column'=> $foreignKey['referenced_columns'][0],
            ];
            $fkNames[$foreignKey['columns'][0]] = true;
        }

        $rawIndexes = $this->adapter->getIndexes($table);
        $indexes = [];
        foreach ($rawIndexes as $name => $indexInfo) {
            if ($name === 'PRIMARY' || !empty($fkNames[$name])) {
                continue;
            }

            $indexes[] = $indexInfo;
        }

        return [
            'table'        => $table,
            'columns'      => $columns,
            'indexes'      => $indexes,
            'foreign_keys' => $foreignKeys,
        ];
    }

    public function getDatabaseSchema()
    {
        $tables = $this->getTables();

        $return = [];
        foreach ($tables as $table) {
            $return[] = $this->getTableSchema($table);
        }

        return $return;
    }

    public function diffSchema($origin, $destination)
    {
        return $this->arrayDiffAssocRecursive($origin, $destination);
    }

    /**
     * @see http://php.net/manual/en/function.array-diff-assoc.php#111675
     */
    protected function arrayDiffAssocRecursive($origin, $destination)
    {
        $difference = array();
        foreach ($origin as $key => $value) {
            if (is_array($value)) {
                if (!isset($destination[$key]) || !is_array($destination[$key])) {
                    $difference[$key] = $value;
                } else {
                    $newDiff = $this->arrayDiffAssocRecursive($value, $destination[$key]);
                    if (!empty($newDiff)) {
                        $difference[$key] = $newDiff;
                    }
                }
            } elseif (!array_key_exists($key, $destination) || $destination[$key] !== $value) {
                $difference[$key] = $value;
            }
        }
        return $difference;
    }

    // this needs to go somewhere else...
    protected function getTables()
    {
        // SQLite
        // $rows = $this->adapter->fetchAll('select * from sqlite_master where `type` = \'table\'');

        // MySQL
        $rows = $this->adapter->fetchAll('show tables');

        $tables = [];
        foreach ($rows as $row) {
            $table = $row[0];
            // @TODO get phinxlog name from config?
            if ($table === 'phinxlog' || $table === 'sqlite_sequence') {
                continue;
            }
            $tables[] = $table;
        }

        return $tables;
    }
}
