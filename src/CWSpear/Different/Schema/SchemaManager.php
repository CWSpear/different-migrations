<?php namespace CWSpear\Different\Schema;

use CWSpear\Different\Exceptions\FileNotFoundException;
use CWSpear\Different\Exceptions\InvalidFormatException;
use CWSpear\Different\Exceptions\UnsetOptionException;
use CWSpear\Different\Config\Config;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Output\OutputInterface;

class SchemaManager extends Manager
{
    const MANUAL_ADAPTER_INIT = 101;

    const ARRAY_OPEN_SQUARE = '[';
    const ARRAY_CLOSE_SQUARE = ']';

    const ARRAY_OPEN_ROUND = 'array(';
    const ARRAY_CLOSE_ROUND = ')';

     const LINE_SEPARATOR = "\n        ";

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

        // if this statement is true, then they have to set adapter via $this->setAdapter()
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

        // get all the foreign keys
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

        // get all the indexes. or is it indices?
        $rawIndexes = $this->adapter->getIndexes($table);
        $indexes = [];
        foreach ($rawIndexes as $name => $indexInfo) {
            // skip if key is primary or a foreign key.
            // those will automatically be given indexes
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
        $format = $this->getConfig()->getSchemaFormat();

        $tables = $this->diffSchema($this->getDatabaseSchema(), []);

        foreach ($tables as $table) {
            $schema = $this->stringifySchema($table);

            $filePath = $this->saveToFile("{$table['table']}.{$format}", $schema);
            $this->output->writeln("<info>Created schema<info> <comment>{$filePath}</comment>");
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
     * Set options (options needed differs on command).
     *
     * This is an array of options passed in threw the
     * command line a la InputInterface::getOptions()
     *
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
     * @throws UnsetOptionException
     */
    public function getOption($option)
    {
        if (!isset($this->options[$option])) {
            throw new UnsetOptionException("You must set a '{$option}' option with setOptions.");
        }

        return $this->options[$option];
    }

    /**
     * @param $name
     * @return string
     */
    public function getPathFromName($name)
    {
        return str_replace(getcwd(), '.', "{$this->getConfig()->getSchemaPath()}/{$name}.{$this->getConfig()->getSchemaFormat()}");
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
     * @throws FileNotFoundException
     */
    public function getFileContents($filePath)
    {
        if (!$this->fileExists($filePath)) {
            throw new FileNotFoundException("File '{$filePath}' not found or not readable.");
        }

        return file_get_contents($filePath);
    }

    /**
     * Turn a PHP array schema into a string based on loaded format (via setOptions)
     *
     * @param array $array
     * @return string
     * @throws InvalidFormatException
     */
    public function stringifySchema(array $array)
    {
        $format = $this->getConfig()->getSchemaFormat();

        switch ($format) {
            case 'json':
                $str = json_encode($array, JSON_PRETTY_PRINT);
                break;

            // @todo support other formats

            default:
                throw new InvalidFormatException("'{$format}' output format is not (yet?) supported");
        }

        return $str;
    }

    /**
     * Convert a schema string read from a file to a PHP array based on loaded format (via setOptions)
     *
     * @param $str
     * @return array
     * @throws InvalidFormatException
     */
    public function parseSchema($str)
    {
        $format = $this->getConfig()->getSchemaFormat();

        switch ($format) {
            case 'json':
                $array = json_decode($str, true);
                break;

            // @todo support other formats

            default:
                throw new InvalidFormatException("'{$format}' output format is not (yet?) supported");
        }

        return $array;
    }

    /**
     * Escape strings (and don't wrap booleans, ints, etc in quotes)
     *
     * @param mixed $var
     * @return mixed
     */
    protected function formatToStr($var)
    {
        if (is_array($var)) {
            $str = var_export($var, true);
            $str = str_replace('  ', '    ', $str);
            return str_replace("\n", self::LINE_SEPARATOR, $str);
        }

        return var_export($var, true);
    }

    /**
     * Create a migration based on up and down diffs
     *
     * @param array $up
     * @param array $down
     * @param string $table
     */
    public function createMigration(array $up, array $down, $table)
    {
        if (isset($up['table'])) {
            $tableChange = 'create';
        } else {
            $tableChange = 'update';
        }

        $upLines = ["\$table = \$this->table({$this->formatToStr($table)});"];

        if (isset($up['columns'])) {
            $upLines = $this->buildColumns($up['columns'], $upLines, 'add');
        }

        $upLines[] = "\$table->{$tableChange}();";


        if ($tableChange === 'create') {
            $downLines = ["\$this->dropTable({$this->formatToStr($table)});"];
        } else {
            $downLines = ["\$table = \$this->table({$this->formatToStr($table)});"];

            if (isset($down['columns'])) {
                $downLines = $this->buildColumns($down['columns'], $downLines, 'remove');
            }

            $downLines[] = "\$table->{$tableChange}();";
        }


    }

    /**
     * @param array $columns
     * @param array $lines
     * @param $move "add" or "remove"
     * @return array
     * @throws \Exception
     */
    protected function buildColumns(array $columns, array $lines, $move)
    {
        if ($move !== 'add' && $move !== "remove") {
            throw new \Exception("'\$move' can only be 'add' or 'remove'");
        }

        foreach ($columns as $name => $opts) {
            $type = isset($opts['type']) ? $opts['type'] : 'string';
            unset($opts['type']);

            $line = "\$table->{$move}Column({$this->formatToStr($name)}";

            // only need to add options on add
            if ($move === 'add') {
                $line .= ", '{$type}'";

                if (!empty($opts)) {
                    $optStr = $this->formatToStr($opts);
                    $line .= ", " .$optStr;
                }
            }

            $line .= ');';

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param $fileName
     * @param $contents
     * @param bool $isMigration
     * @return string the path to the file just saved
     * @throws UnsetOptionException
     */
    protected function saveToFile($fileName, $contents, $isMigration = false)
    {
        $dir = $isMigration ? $this->getConfig()->getMigrationPath() : $this->getConfig()->getSchemaPath();
        $filePath = "{$dir}/{$fileName}";
        file_put_contents($filePath, $contents);
        return $filePath;
    }
}
