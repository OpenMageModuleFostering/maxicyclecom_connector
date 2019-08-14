<?php

class Aglumbik_Creditmemoext_Block_Adminhtml_Sales_Creditmemo_Renderer_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {
        $value = 0 + (int) $row->getData($this->getColumn()->getIndex());
        $statuses = Mage::helper('creditmemoext')->getCustomStatuses();
        return $value;
    }

}
