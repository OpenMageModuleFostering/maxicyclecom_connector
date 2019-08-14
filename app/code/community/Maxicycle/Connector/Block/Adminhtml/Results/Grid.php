<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Block_Adminhtml_Results_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('retultsGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection() {
        $collection = Mage::getModel('maxicycle/results')->getCollection();
//        $collection->addFieldToFilter('export_flag',0);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {

        $this->addColumn('id', array(
            'header' => Mage::helper('maxicycle')->__('Result ID'),
            'align' => 'left',
            'width' => '50px',
            'index' => 'id',
        ));

        $this->addColumn('campaign_id', array(
            'header' => Mage::helper('maxicycle')->__('Campaign'),
            'align' => 'left',
            'index' => 'campaign_id',
            'renderer' => 'Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Campaign',
        ));

        $this->addColumn('order_id', array(
            'header' => Mage::helper('maxicycle')->__('Order Nr.'),
            'align' => 'left',
            'index' => 'order_id',
            'renderer' => 'Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Order',
        ));

        $this->addColumn('campaign_order_type', array(
            'header' => Mage::helper('maxicycle')->__('Campaign Order Type'),
            'align' => 'left',
            'index' => 'campaign_order_type',
        ));

        $this->addColumn('response_to_order_id', array(
            'header' => Mage::helper('maxicycle')->__('Response To Order ID'),
            'align' => 'left',
            'index' => 'response_to_order_id',
        ));

        $this->addColumn('grand_total', array(
            'header' => Mage::helper('maxicycle')->__('Grand Total'),
            'align' => 'left',
            'index' => 'grand_total',
        ));

        $this->addColumn('order_profit', array(
            'header' => Mage::helper('maxicycle')->__('Gross Total'),
            'align' => 'left',
            'index' => 'order_profit',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('maxicycle')->__('Created At'),
            'index' => 'created_at',
            'type' => 'datetime'
        ));

        $this->addColumn('export_flag', array(
            'header' => Mage::helper('maxicycle')->__('Exported to SaaS'),
            'align' => 'left',
            'index' => 'export_flag',
            'renderer' => 'Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Flag2',
        ));

        return parent::_prepareColumns();
    }

}
