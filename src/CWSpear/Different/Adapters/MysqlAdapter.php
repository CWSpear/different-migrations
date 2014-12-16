<?php namespace CWSpear\Different\Adapters;

use Phinx\Db\Adapter\MysqlAdapter as PhinxMysqlAdapter;

class MysqlAdapter extends PhinxMysqlAdapter
{
    public static function fromAdapter(PhinxMysqlAdapter $adapter)
    {
        $this = $adapter;
    }
}