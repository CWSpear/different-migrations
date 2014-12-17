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
use Symfony\Component\Console\Output\OutputInterface;

class SchemaManagerSpec extends ObjectBehavior
{
    protected $outputDir  = './spec/schema';
    protected $fixtureDir = './spec/fixtures';

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
     * Do some tasks on cleanup
     */
    function letGo()
    {
        $this->clearOutputDir();
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('CWSpear\Different\Schema\SchemaManager');
    }

    function it_should_export_a_schema_file(OutputInterface $output)
    {
        $this->clearOutputDir();
        $this->setOptions([
            'dir'    => $this->outputDir,
            'format' => 'json',
        ])->export();

        $this->fileExists("{$this->outputDir}/example.json")->shouldBe(true);
        $this->fileExists("{$this->outputDir}/example_foreign.json")->shouldBe(true);
    }

    function its_export_should_match_the_current_schema()
    {
        $this->clearOutputDir();
        $this->setOptions([
            'dir'    => $this->outputDir,
            'format' => 'json',
        ])->export();

        $this->getFileContents("{$this->outputDir}/example.json")
             ->shouldEqual($this->getFileContents("./spec/fixtures/example.json"));

        $this->getFileContents("{$this->outputDir}/example_foreign.json")
             ->shouldEqual($this->getFileContents("./spec/fixtures/example_foreign.json"));


        $fixtureSchema = $this->parseSchema($this->getFileContents("./spec/fixtures/example_foreign.json"));
        $this->loadSchema('example_foreign')
            ->shouldEqual($fixtureSchema);
    }

    /**
     * Execute a command with the provided input
     *
     * @param Command $command
     * @param array $input
     * @return int
     * @throws \Exception
     */
    protected function executeCommand(Command $command, array $input)
    {
        $input = new ArrayInput($input);
        $output = new NullOutput();
        $command->run($input, $output);
        return $command;
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
}
