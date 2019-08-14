<?php

class Maxicycle_Connector_Block_Adminhtml_Results_Renderer_Order extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {
        $order = Mage::getModel('sales/order')->loadByIncrementId($row->getData('order_id'));
        return '<a href="' . Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/view', array('order_id' => $order->getEntityId())) . '" target="_blank">' . $row->getData('order_id') . '</a>';
    }

}
