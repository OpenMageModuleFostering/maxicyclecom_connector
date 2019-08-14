<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */

class Maxicycle_Connector_Model_Mysql4_Results extends Mage_Core_Model_Mysql4_Abstract {

    public function _construct() {
        $this->_init('maxicycle/results', 'id');
    }

}
