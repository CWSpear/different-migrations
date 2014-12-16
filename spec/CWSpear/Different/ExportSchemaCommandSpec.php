<?php namespace spec\CWSpear\Different;

use CWSpear\Different\ExportSchemaCommand;
use CWSpear\Different\SchemaManager;
use Phinx\Console\Command\Migrate;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class ExportSchemaCommandSpec extends ObjectBehavior
{
    protected $testConfig = [
        '--configuration' => './spec/phinx.yml',
        '--environment'   => 'mysql',
    ];

    function let()
    {
        // this sets up our initial table we are going to export
        // it does its best to be a good mix of everything we
        // might encounter in an existing database
        // $phinxCmd = new Migrate();
        // $input = new ArrayInput($this->testConfig);
        // $output = new NullOutput();
        // $resultCode = $phinxCmd->run($input, $output);


        $cmd = new ExportSchemaCommand();
        $input = new ArrayInput($this->testConfig);
        $output = new NullOutput();
        $resultCode = $this->run($input, $output);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('CWSpear\Different\ExportSchemaCommand');
    }

    function it_should_create_a_schema_file()
    {

    }

    function it_should_match_the_current_schema()
    {
        // $this->
    }
}
