<?php namespace CWSpear\Different\Schema;

use Phinx\Config\Config;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\Manager;
use Prophecy\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

class SchemaManager extends Manager
{
    const MANUAL_ADAPTER_INIT = 101;
    const MANUAL_OPTIONS_INIT = null;

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

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var array options
     */
    protected $options;

    /**
     * @param Config $config
     * @param OutputInterface $output
     * @param string $environment
     */
    public function __construct(Config $config, OutputInterface $output, $environment)
    {
        parent::__construct($config, $output);

        // if this statement is true, this they have to set adapter via $this->setAdapter()
        if ($environment !== self::MANUAL_ADAPTER_INIT) {
            $this->adapter = $this->getEnvironment($environment)->getAdapter();
        }
    }

    /**
     * Get the schema of a table by name
     *
     * @param string $table
     * @return array
     */
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

    /**
     * Get the schema for the entire database (an array of table schema)
     *
     * @return array
     */
    public function getDatabaseSchema()
    {
        $tables = $this->adapter->getTables();

        $return = [];
        foreach ($tables as $table) {
            $return[] = $this->getTableSchema($table);
        }

        return $return;
    }

    /**
     * Create a diff on two schema
     *
     * @param array $origin
     * @param array $destination
     * @return array
     */
    public function diffSchema(array $origin, array $destination)
    {
        return $this->arrayDiffAssocRecursive($origin, $destination);
    }

    /**
     * Perform a recursive diff on two associative arrays
     *
     * @see http://php.net/manual/en/function.array-diff-assoc.php#111675
     * @param array $origin
     * @param array $destination
     * @return array
     */
    protected function arrayDiffAssocRecursive(array $origin, array $destination)
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

    /**
     * Export the current database schema to files
     */
    public function export()
    {
        $dir    = $this->getOption('dir');
        $format = $this->getOption('format');

        $tables = $this->diffSchema($this->getDatabaseSchema(), []);

        // @todo create directory/make sure it's writable
        foreach ($tables as $table) {
            $schema = $this->stringifySchema($table);

            $file = "{$dir}/{$table['table']}.{$format}";
            file_put_contents($file, $schema);
            $this->output->writeln("<info>Created schema<info> <comment>{$file}</comment>");
        }
    }

    /**
     * @param AdapterInterface $adapter
     * @return $this
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;

        // normalize options that need it
        if (isset($this->options['dir'])) {
            $this->options['dir'] = rtrim($this->options['dir'], '/');
        }
        if (isset($this->options['format'])) {
            $this->options['format'] = strtolower($this->options['format']);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $option
     * @return mixed
     */
    public function getOption($option)
    {
        if (!isset($this->options[$option])) {
            throw new \InvalidArgumentException("You must set a '{$option}' option with setOptions.");
        }

        return $this->options[$option];
    }

    /**
     * @param $name
     * @return string
     */
    public function getPathFromName($name)
    {
        return "{$this->getOption('dir')}/{$name}.{$this->getOption('format')}";
    }

    /**
     * Checks whether a file exists
     *
     * @param string $filename
     * @return bool
     */
    public function fileExists($filename)
    {
        return file_exists($filename);
    }

    /**
     * Load a schema file into memory by schema name.
     * It uses the format and dir loaded by setOptions()
     *
     * @see setOptions()
     * @param string $name
     * @return array A PHP array representing the schema loaded from file
     */
    public function loadSchema($name)
    {
        $filePath = $this->getPathFromName($name);

        $contents = $this->getFileContents($filePath);
        $schema   = $this->parseSchema($contents);

        return $schema;
    }

    /**
     * Get the contents of a file
     *
     * @param $filePath
     * @return string
     */
    public function getFileContents($filePath)
    {
        if (!$this->fileExists($filePath)) {
            throw new InvalidArgumentException("File {$filePath} not found or not readable.");
        }

        return file_get_contents($filePath);
    }

    /**
     * Turn a PHP array schema into a string based on loaded format (via setOptions)
     *
     * @param array $array
     * @return string
     */
    public function stringifySchema(array $array)
    {
        $format = $this->getOption('format');

        switch ($format) {
            case 'json':
                $str = json_encode($array, JSON_PRETTY_PRINT);
                break;

            // @todo support other formats

            default:
                throw new InvalidArgumentException("{$format} output format is not (yet?) supported");
        }

        return $str;
    }

    /**
     * Convert a schema string read from a file to a PHP array based on loaded format (via setOptions)
     *
     * @param $str
     * @return array
     */
    public function parseSchema($str)
    {
        $format = $this->getOption('format');

        switch ($format) {
            case 'json':
                $array = json_decode($str, true);
                break;

            // @todo support other formats

            default:
                throw new InvalidArgumentException("{$format} output format is not (yet?) supported");
        }

        return $array;
    }
}
