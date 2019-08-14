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

$installer->run("
ALTER TABLE `{$installer->getTable('maxicycle/results')}` CHANGE `campaign_order_type` `campaign_order_type` TEXT NULL DEFAULT NULL
");

//// Add sku attribute
//$installer->getConnection()->modifyColumn($installer->getTable('maxicycle/results'), 'campaign_order_type', 
//                Varien_Db_Ddl_Table::TYPE_VARCHAR, array('nullable' => true), 'Campaign Order Type' );


$installer->endSetup();