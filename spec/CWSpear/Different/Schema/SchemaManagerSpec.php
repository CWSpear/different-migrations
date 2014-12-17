<?php namespace spec\CWSpear\Different\Schema;

use CWSpear\Different\Schema\SchemaManager;
use Phinx\Config\Config;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\Manager;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SchemaManagerSpec extends ObjectBehavior
{
    protected $outputDir   = './spec/fixtures/actual';
    protected $expectedDir = './spec/fixtures/expected';

    function let(Config $config)
    {
        $options = array(
            'host' => 'localhost', // TESTS_PHINX_DB_ADAPTER_MYSQL_HOST,
            'name' => 'phinx', // TESTS_PHINX_DB_ADAPTER_MYSQL_DATABASE,
            'user' => 'root', // TESTS_PHINX_DB_ADAPTER_MYSQL_USERNAME,
            'pass' => 'root', // TESTS_PHINX_DB_ADAPTER_MYSQL_PASSWORD,
        );
        $adapter = new MysqlAdapter($options, new NullOutput());

        $this->beConstructedWith($config, new NullOutput(), SchemaManager::MANUAL_ADAPTER_INIT);
        $this->setAdapter($adapter);
    }

    /**
     * Do some tasks on tear down
     */
    function letGo()
    {
        $this->clearOutputDir();
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('CWSpear\Different\Schema\SchemaManager');
    }

    function it_should_set_and_get_options()
    {
        $this->setOptions([
            'apple'  => 'apple',
            'banana' => 'carrot',
            'money'  => ['$', '$$$', '$$$$$'],
        ]);

        $this->getOption('apple')
            ->shouldReturn('apple');

        $this->getOption('banana')
            ->shouldReturn('carrot');

        $this->getOption('money')
            ->shouldReturn(['$', '$$$', '$$$$$']);
    }

    function it_should_stringify_schema()
    {
        $this->setOptions(['format' => 'json'])
            ->stringifySchema(['test' => 'array'])
            ->shouldReturn("{\n    \"test\": \"array\"\n}");

        // @todo add tests for other formats when support is added
    }

    function it_should_parse_schema()
    {
        $this->setOptions(['format' => 'json'])
            ->parseSchema("{\n    \"test\": \"array\"\n}")
            ->shouldReturn(['test' => 'array']);

        // @todo add tests for other formats when support is added
    }

    function it_should_format_paths_from_names()
    {
        $this->setOptions([
            'dir'    => $this->expectedDir,
            'format' => 'json',
        ])->getPathFromName('example')
            ->shouldReturn("{$this->expectedDir}/example.json");

        $this->setOptions([
            'dir'    => 'banana',
            'format' => 'json',
        ])->getPathFromName('banana')
            ->shouldReturn('banana/banana.json');

        $this->setOptions([
            'dir'    => $this->expectedDir,
            'format' => 'xml',
        ])->getPathFromName('funny')
            ->shouldReturn("{$this->expectedDir}/funny.xml");
    }

    function it_should_load_schema()
    {
        $expected = $this->loadExpected('example.php', true);

        $this->setOptions([
            'dir'    => $this->expectedDir,
            'format' => 'json',
        ])->loadSchema('example')
            ->shouldReturn($expected);

        // @todo add tests for other formats when support is added?
    }

    function it_should_squawk_on_unsupported_format()
    {
        $this->setOptions(['format' => 'foo']);
        $this->shouldThrow('CWSpear\Different\Exceptions\InvalidFormatException')
            ->duringStringifySchema([]);

        $this->shouldThrow('CWSpear\Different\Exceptions\InvalidFormatException')
            ->duringParseSchema('');
    }

    function it_should_squawk_on_unset_option()
    {
        $this->shouldThrow('CWSpear\Different\Exceptions\UnsetOptionException')
            ->duringGetOption('foobar');
    }

    function it_should_squawk_on_file_not_found()
    {
        $this->shouldThrow('CWSpear\Different\Exceptions\FileNotFoundException')
            ->duringGetFileContents('foobar.bleh');
    }

    function it_should_get_table_schema()
    {
        $expected = $this->loadExpected('example.php', true);

        $this->setOptions([
            'dir'    => $this->outputDir,
            'format' => 'json',
        ])->getTableSchema('example')
            ->shouldReturn($expected);
    }

    // Export Test 1
    function it_should_export_a_schema_file()
    {
        $this->clearOutputDir();

        $this->setOptions([
            'dir'    => $this->outputDir,
            'format' => 'json',
        ])->export();

        $this->fileExists("{$this->outputDir}/example.json")->shouldBe(true);
        $this->fileExists("{$this->outputDir}/example_foreign.json")->shouldBe(true);
    }

    // Export Test 2
    function its_export_should_match_the_current_schema()
    {
        $this->clearOutputDir();

        $this->setOptions([
            'dir'    => $this->outputDir,
            'format' => 'json',
        ])->export();

        $this->compareFixture('example.json');

        $this->compareFixture('example_foreign.json');

        $fixtureSchema = $this->parseSchema($this->getFileContents("{$this->expectedDir}/example_foreign.json"));
        $this->loadSchema('example_foreign')
            ->shouldEqual($fixtureSchema);
    }

    function it_should_create_migrations()
    {
        $diffUp   = $this->loadExpected('simple_diff_up.php', true);
        $diffDown = $this->loadExpected('simple_diff_down.php', true);

        $this->createMigration($diffUp, $diffDown);

        $this->compareFixture('simple_migration.php');
    }

    /**
     * Compare expected to actual outputs based on filename (i.e. example.json)
     *
     * @param $fileName
     */
    protected function compareFixture($fileName)
    {
        $this->getFileContents("{$this->outputDir}/{$fileName}")
            ->shouldEqual($this->getFileContents("{$this->expectedDir}/{$fileName}"));
    }

    /**
     * Clears the output directory so as to start the test with a clean slate
     */
    protected function clearOutputDir()
    {
        // delete any exist test schema output
        $files = scandir($this->outputDir);
        foreach ($files as $file) {
            if ($file[0] === '.') continue;

            unlink("{$this->outputDir}/{$file}");
        }
    }

    /**
     * Load file from expected fixtures directory
     *
     * @param string $fileName
     * @param bool $include whether to use include() or file_get_contents()
     * @return string
     */
    protected function loadExpected($fileName, $include = false)
    {
        $filePath = "{$this->expectedDir}/{$fileName}";
        if ($include) {
            return include($filePath);
        } else {
            return file_get_contents($filePath);
        }
    }
}
