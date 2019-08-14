<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */

class Maxicycle_Connector_Model_Results extends Mage_Core_Model_Abstract {

    public function _construct() {
        parent::_construct();
        $this->_init('maxicycle/results');
    }
}
