<?php namespace CWSpear\Different\Config;

use Phinx\Config\Config as PhinxConfig;

class Config extends PhinxConfig
{
    /**
     * Gets the path of the schema files.
     *
     * @return string
     */
    public function getSchemaPath()
    {
        if (!isset($this->values['paths']['schema'])) {
            throw new \UnexpectedValueException('Schema path missing from config file');
        }

        $path = realpath($this->values['paths']['schema']);

        if ($path === false) {
            throw new \UnexpectedValueException(sprintf(
                'Migrations directory "%s" does not exist',
                $this->values['paths']['schema']
            ));
        }

        return $path;
    }

    /**
     * Gets the format for the schema files.
     *
     * @return string
     */
    public function getSchemaFormat()
    {
        if (!isset($this->values['paths']['format'])) {
            throw new \UnexpectedValueException('Schema format missing from config file');
        }

        return $this->values['paths']['format'];
    }
}
