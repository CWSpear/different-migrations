<?php namespace CWSpear\Different;

use Prophecy\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Console\Command\AbstractCommand;
use Phinx\Migration\Manager;
use CWSpear\Different\SchemaManager;
use JSON_PRETTY_PRINT;

class ExportSchemaCommand extends AbstractCommand
{
    public function configure()
    {
        parent::configure();
         
        $this->setName('export')
             ->setDescription('Create schema files based on the current state of the database.')
             ->addOption('--environment', '-e', InputOption::VALUE_OPTIONAL, 'The target environment')
             ->addOption('--output', '-o', InputOption::VALUE_OPTIONAL, 'The output directory for schema files', '.')
             ->addOption('--format', '-f', InputOption::VALUE_OPTIONAL, 'Override the default format', 'json');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<error>Not Yet Implemented</error>');
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
        $this->export($environment, $input, $output);
        $end = microtime(true);
        
        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }

    protected function export($environment, InputInterface $input, OutputInterface $output)
    {
        $manager = new SchemaManager($this->getManager(), $environment);
        $export = $manager->diffSchema($manager->getDatabaseSchema(), []);

        // @todo support other formats
        $format = strtolower($input->getOption('format'));

        $outputDir = $input->getOption('output');
        foreach ($export as $table) {
            switch ($format) {
                case 'json':
                    $schema = json_encode($table, JSON_PRETTY_PRINT);
                    break;

                default:
                    throw new InvalidArgumentException("{$format} output format is not (yet?) supported");
            }

            $file = "{$outputDir}/{$table['table']}.{$format}";
            file_put_contents($file, $schema);
        }
    }
}
