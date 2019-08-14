<?php

class Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Flag2 extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {
        if ((int)$row->getData('export_flag')) {
            return 'Yes';
        } else {
            return 'No';
        }
    }

}
