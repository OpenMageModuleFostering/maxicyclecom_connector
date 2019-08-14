<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Adminhtml_CronlogController extends Mage_Adminhtml_Controller_action {

    public function indexAction() {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('maxicycle/adminhtml_cronlog'));
        $this->renderLayout();
    }

}
