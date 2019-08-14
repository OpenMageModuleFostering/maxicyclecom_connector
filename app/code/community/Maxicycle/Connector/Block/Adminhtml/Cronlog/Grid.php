<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Block_Adminhtml_Cronlog_Grid extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('crondataGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection() {
        $collection = Mage::getModel('maxicycle/cronlog')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {

        $this->addColumn('cron_id', array(
            'header' => Mage::helper('maxicycle')->__('Cron ID'),
            'align' => 'left',
            'width' => '50px',
            'index' => 'campain_id',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('maxicycle')->__('Created At'),
            'index' => 'created_at',
            'type' => 'datetime'
        ));

        $this->addColumn('data', array(
            'header' => Mage::helper('maxicycle')->__('Data'),
            'align' => 'left',
            'index' => 'data',
        ));

        return parent::_prepareColumns();
    }

}
