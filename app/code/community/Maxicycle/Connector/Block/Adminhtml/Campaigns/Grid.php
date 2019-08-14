<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Block_Adminhtml_Campaigns_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('maxicycleGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection() {
        $collection = Mage::getModel('maxicycle/campaigns')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        $stores = $this->getStores();

        $this->addColumn('campaign_id', array(
            'header' => Mage::helper('maxicycle')->__('Campaign ID'),
            'align' => 'left',
            'width' => '50px',
            'index' => 'campaign_id',
        ));

        $this->addColumn('name', array(
            'header' => Mage::helper('maxicycle')->__('Name'),
            'align' => 'left',
            'index' => 'name',
        ));

        $this->addColumn('sku', array(
            'header' => Mage::helper('maxicycle')->__('Inserts SKU'),
            'align' => 'left',
            'index' => 'product_sku',
        ));

        $this->addColumn('campaign_start', array(
            'header' => Mage::helper('maxicycle')->__('Campaign Start'),
            'index' => 'campaign_start',
            'type' => 'datetime'
        ));

        $this->addColumn('campaign_end', array(
            'header' => Mage::helper('maxicycle')->__('Campaign End'),
            'index' => 'campaign_end',
            'type' => 'datetime'
        ));

        $this->addColumn('response_time_end', array(
            'header' => Mage::helper('maxicycle')->__('Response Time End'),
            'index' => 'response_time_end',
            'type' => 'datetime'
        ));

        $this->addColumn('store_id', array(
            'header' => Mage::helper('maxicycle')->__('Store'),
            'index' => 'store_id',
            'width' => '100px',
            'type' => 'options',
            'options' => $stores,
        ));

        $this->addColumn('action', array(
            'header' => Mage::helper('maxicycle')->__('Action'),
            'width' => '200',
            'type' => 'action',
            'getter' => 'getCampaignId',
            'actions' => array(
                array(
                    'caption' => Mage::helper('maxicycle')->__('Regenerate results'),
                    'url'     => array('base' => '*/*/regenerate'),
                    'field'   => 'campaign_id',
                    'onclick' => "if(!confirm('Do you realy want to re-generate results?')) return false;"
                )
            )
        ));

        return parent::_prepareColumns();
    }

    public function getStores() {
        $stores = Mage::app()->getStores();
        $options = array();
        foreach ($stores as $store) {
            $options[$store->getId()] = $store->getName();
        }
        return $options;
    }

}
