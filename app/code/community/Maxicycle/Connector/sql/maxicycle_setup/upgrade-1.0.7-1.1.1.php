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

// Add sku attribute
$installer->getConnection()->addColumn($installer->getTable('sales_flat_quote'), 'maxicycle_sku',
        Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
        'default' => null,
        'comment' => 'Maxicycle chosen sku',
        'length' => 100    
        ));

// Add sku attribute
$installer->getConnection()->addColumn($installer->getTable('sales_flat_order'), 'maxicycle_sku',
        Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable' => true,
        'default' => null,
        'comment' => 'Maxicycle chosen sku',
        'length' => 100    
        ));


$installer->endSetup();