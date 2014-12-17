<?php namespace CWSpear\Different\Console;

use CWSpear\Different\Schema\SchemaManager;
use Phinx\Console\Command\AbstractCommand as PhinxAbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends PhinxAbstractCommand
{
    /**
     * Bootstrap Command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function bootstrap(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($output, $input->getOption('environment'));

        // report the migrations path
        $output->writeln('<info>using migration path</info> ' . $this->getConfig()->getMigrationPath());
    }

    /**
     * Load the migrations manager and inject the config
     *
     * @param OutputInterface $output
     * @param string $environment
     */
    protected function loadManager(OutputInterface $output, $environment = null)
    {
        // we want the signature to be compatible with parent,
        // so $environment is optional in signature only!
        if (is_null($environment)) {
            throw new \InvalidArgumentException;
        }

        if (null === $this->getManager()) {
            $manager = new SchemaManager($this->getConfig(), $output, $environment);
            $this->setManager($manager);
        }
    }
}