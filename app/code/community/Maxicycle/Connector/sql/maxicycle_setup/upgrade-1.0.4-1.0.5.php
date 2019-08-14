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

// Add order attribute
$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote'), 'maxicycle_response_to_order_id',
        Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
        'default' => null,
        'comment' => 'Maxicycle response id',
        'length' => 100    
        ));
$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote'), 'maxicycle_order_type',
        Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
        'default' => null,
        'comment' => 'Maxicycle order type' ,
        'length' => 100      
        ));
$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote'), 'maxicycle_campaign_id', 
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'length' => 10,
            'nullable' => true,
            'default' => null,
            'comment' => 'Maxicycle campaign ID for order'
        ));
$installer->endSetup();
