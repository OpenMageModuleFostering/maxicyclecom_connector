<?php

class Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Campaign extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {
        $campaign = Mage::getModel('maxicycle/campaigns')->load($row->getData('campaign_id'));
        return $campaign->getName();
    }

}
