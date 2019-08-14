<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */

class Maxicycle_Connector_Block_Adminhtml_Campaigns extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_campaigns';
    $this->_blockGroup = 'maxicycle';
    $this->_headerText = Mage::helper('maxicycle')->__('Maxicycle Campaigns');
    parent::__construct();
    $this->_removeButton('add');
  }
}