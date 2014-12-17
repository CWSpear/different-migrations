<?php namespace CWSpear\Different\Console;

use CWSpear\Different\Config\Config;
use CWSpear\Different\Schema\SchemaManager;
use Phinx\Console\Command\AbstractCommand as PhinxAbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends PhinxAbstractCommand
{
    /**
     * @{inheritdoc}
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
     * @{inheritdoc}
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

    /**
     * @{inheritdoc}
     */
    protected function loadConfig(InputInterface $input, OutputInterface $output)
    {
        $configFilePath = $this->locateConfigFile($input);
        $output->writeln('<info>using config file</info> .' . str_replace(getcwd(), '', realpath($configFilePath)));

        $parser = $input->getOption('parser');

        // If no parser is specified try to determine the correct one from the file extension.  Defaults to YAML
        if (null === $parser) {
            $extension = pathinfo($configFilePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case 'json':
                    $parser = 'json';
                    break;
                case 'php':
                    $parser = 'php';
                    break;
                case 'yml':
                default:
                    $parser = 'yaml';
                    break;
            }
        }

        switch (strtolower($parser)) {
            case 'json':
                $config = Config::fromJson($configFilePath);
                break;
            case 'php':
                $config = Config::fromPhp($configFilePath);
                break;
            case 'yaml':
                $config = Config::fromYaml($configFilePath);
                break;
            default:
                throw new \InvalidArgumentException(sprintf('\'%s\' is not a valid parser.', $parser));
        }

        $output->writeln('<info>using config parser</info> ' . $parser);
        die ('banana');
        $this->setConfig($config);
    }
}