<?php

use Phinx\Migration\AbstractMigration;

class CreateExampleTable extends AbstractMigration
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
        $table = $this->table('example');
        $table->addColumn('string_col', 'string');
        $table->addColumn('index1_col', 'string');
        $table->addColumn('index2_col', 'string');
        $table->addColumn('foreign_id', 'integer', array('null' => true));
        $table->addColumn('text_col', 'text');
        $table->addColumn('integer_col', 'integer');
        $table->addColumn('biginteger_col', 'biginteger');
        $table->addColumn('float_col', 'float');
        $table->addColumn('decimal_col', 'decimal');
        $table->addColumn('datetime_col', 'datetime');
        $table->addColumn('timestamp_col', 'timestamp');
        $table->addColumn('time_col', 'time');
        $table->addColumn('date_col', 'date');
        $table->addColumn('binary_col', 'binary');
        $table->addColumn('boolean_col', 'boolean');

        $table->addColumn('string_limit_col', 'string', array('length' => 100));
        // keep going...

        $table->addIndex(array('string_col', 'integer_col'), array('unique' => true));
        $table->addIndex(array('index1_col'), array('unique' => true));
        $table->addIndex(array('index2_col'));
        $table->addForeignKey('foreign_id', 'example_foreign', 'id', array('delete'=> 'CASCADE', 'update'=> 'SET_NULL'));

        $table->create();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->dropTable('example');
    }
}