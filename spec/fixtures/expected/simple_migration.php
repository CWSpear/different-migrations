<?php

use Phinx\Migration\AbstractMigration;

class SimpleMigration extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('example');
        $table->addColumn('new_col', 'string', array (
            'unique' => true,
        ));
        $table->update();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('example');
        $table->removeColumn('string_col');
        $table->update();
    }
}
