<?php namespace CWSpear\Different\Console;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Migration\Manager;

class ExportSchemaCommand extends AbstractCommand
{
    public function configure()
    {
        parent::configure();

        $this->setName('export')
            ->setDescription('Create schema files based on the current state of the database.')
            ->addOption('--environment', '-e', InputOption::VALUE_OPTIONAL, 'The target environment');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        $output->writeln('<info>using database</info> ' . $envOptions['name']);

        // run the migrations
        $start = microtime(true);
        $this->getManager()->setOptions($input->getOptions())->export();
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
