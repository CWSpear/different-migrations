<?php

use Phinx\Migration\AbstractMigration;

class CreateExampleForeignTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     *
    public function change()
    {
    }
    */
    
    /**
     * Migrate Up.
     */
    public function up()
    {
        // we only need this table for its ID
        // (which is auto-generated )
        $table = $this->table('example_foreign');
        $table->create();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->dropTable('example_foreign');
    }
}