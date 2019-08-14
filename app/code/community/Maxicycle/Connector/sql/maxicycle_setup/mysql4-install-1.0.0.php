<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
$installer = $this;
$installer->startSetup();

/* Create base table for campaigns */
$table = $installer->getConnection()
        ->newTable($installer->getTable('maxicycle/campaigns'))
        ->addColumn('campaign_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
                ), 'Campaign ID')
        ->addColumn('name', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable' => false,
                ), 'Name')
        ->addColumn('description', Varien_Db_Ddl_Table::TYPE_BLOB, null, array(
            'nullable' => false,
                ), 'Description')
        ->addColumn('product_sku', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable' => false,
                ), 'Product SKU')
        ->addColumn('campaign_start', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => false,
                ), 'Campaign Start')
        ->addColumn('campaign_end', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => false,
                ), 'Campaign End')
        ->addColumn('response_time_end', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => false,
                ), 'Response Time End')
        ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Store')
        ->addColumn('campaign_status', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Campaign Status')
        ->addColumn('condition', Varien_Db_Ddl_Table::TYPE_BLOB, null, array(
    'nullable' => false,
        ), 'Condition');


/* Create base table for results */
$table2 = $installer->getConnection()
        ->newTable($installer->getTable('maxicycle/results'))
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
                ), 'ID')
        ->addColumn('campaign_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
                ), 'Campaign ID')
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Order ID')
        ->addColumn('maxicycle_customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Maxicycle Customer ID')
        ->addColumn('campaign_order_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable' => false,
                ), 'Campaign Order Type')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => false,
                ), 'Created At')
        ->addColumn('last_order_update_date', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
            'nullable' => false,
                ), 'Last Order Update Date')
        ->addColumn('response_to_order_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable' => false,
                ), 'Response to order ID')
        ->addColumn('grand_total', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
            'nullable' => false,
                ), 'Grand Total')
        ->addColumn('order_profit', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,4', array(
            'nullable' => false,
                ), 'Order Profit')
        ->addColumn('export_flag', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable' => false,
                ), 'Export Flag');

/* Create base table for campaigns */
$table3 = $installer->getConnection()
        ->newTable($installer->getTable('maxicycle/cronlog'))
        ->addColumn('cron_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity' => true,
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
                ), 'Cron ID')
        ->addColumn('data', Varien_Db_Ddl_Table::TYPE_BLOB, null, array(
            'nullable' => false,
                ), 'Data')
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
    'nullable' => false,
        ), 'Created At');

$installer->run("DROP TABLE IF EXISTS {$this->getTable('maxicycle/cronlog')}");
$installer->getConnection()->createTable($table3);
$installer->run("DROP TABLE IF EXISTS {$this->getTable('maxicycle/results')}");
$installer->getConnection()->createTable($table2);
$installer->run("DROP TABLE IF EXISTS {$this->getTable('maxicycle/campaigns')}");
$installer->getConnection()->createTable($table);

// Add order attribute
$installer->getConnection()->addColumn($installer->getTable('sales_flat_order'),
        'maxicycle_customer_id',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'length' => 10,
            'nullable' => true,
            'default' => null,
            'comment' => 'CustomerId for Maxicycle Plugin'
        ));

$installer->endSetup();

