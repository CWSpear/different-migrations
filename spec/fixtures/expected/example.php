<?php 

return array (
    'table' => 'example',
    'columns' => array (
        'id' => array (
            'type' => 'integer',
            'identity' => true,
        ),
        'string_col' => array (
            'type' => 'string',
        ),
        'index1_col' => array (
            'type' => 'string',
        ),
        'index2_col' => array (
            'type' => 'string',
        ),
        'foreign_id' => array (
            'type' => 'integer',
            'null' => true,
        ),
        'text_col' => array (
            'type' => 'text',
        ),
        'integer_col' => array (
            'type' => 'integer',
        ),
        'biginteger_col' => array (
            'type' => 'biginteger',
        ),
        'float_col' => array (
            'type' => 'float',
        ),
        'decimal_col' => array (
            'type' => 'decimal',
            'limit' => '10',
        ),
        'datetime_col' => array (
            'type' => 'datetime',
        ),
        'timestamp_col' => array (
            'type' => 'timestamp',
            'default' => 'CURRENT_TIMESTAMP',
        ),
        'time_col' => array (
            'type' => 'time',
        ),
        'date_col' => array (
            'type' => 'date',
        ),
        'binary_col' => array (
            'type' => 'binary',
        ),
        'boolean_col' => array (
            'type' => 'boolean',
        ),
        'string_limit_col' => array (
            'type' => 'string',
            'limit' => '100',
        ),
    ),
    'indexes' => array (
        array (
            'columns' => array (
                'string_col',
                'integer_col',
            ),
            'unique' => true,
        ),
        array (
            'columns' => array (
                'index1_col',
            ),
            'unique' => true,
        ),
        array (
            'columns' => array (
                'index2_col',
            ),
            'unique' => false,
        ),
    ),
    'foreign_keys' => array (
        array (
            'local_column' => 'foreign_id',
            'foreign_table' => 'example_foreign',
            'foreign_column' => 'id',
        ),
    ),
);
