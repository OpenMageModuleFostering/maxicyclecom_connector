<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Adminhtml_InstallationController extends Mage_Adminhtml_Controller_action {

    public function indexAction() {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('adminhtml/template')->setTemplate('maxicycle/connector/installation/step1.phtml'));
        $this->renderLayout();
    }

    public function updateAction() {
        if ($this->getRequest()->getPost()) {
            // DB CONNECTION
            $db = Mage::getSingleton('core/resource')->getConnection('core_write');
            $store_id = (int) $this->getRequest()->getPost('store_id');
            $scope = (($store_id == 0) ? 'default' : 'stores');

            // VALUES
            $is_enable = intval($this->getRequest()->getPost('is_enable'));
            $api_key = addslashes($this->getRequest()->getPost('api_key'));
            $product_costs_attribute = addslashes($this->getRequest()->getPost('product_costs_attribute'));
            $product_costs_fixed = addslashes($this->getRequest()->getPost('product_costs_fixed'));
            $product_costs_type = addslashes($this->getRequest()->getPost('product_costs_type'));
            $use_tax = intval($this->getRequest()->getPost('use_tax'));
            $use_shipping = intval($this->getRequest()->getPost('use_shipping'));
            $order_costs = floatval($this->getRequest()->getPost('order_costs'));
            $valid_statuses = $this->getRequest()->getPost('valid_statuses');
            $add_during_checkout_set = $this->getRequest()->getPost('add_during_checkout');

            $add_during_checkout = isset($add_during_checkout_set) ? TRUE : FALSE;
            // KEYS
            $is_enable_key = 'maxicycle/maxicycle_option/enable';
            $api_key_key = 'maxicycle/maxicycle_option/api_key';
            $product_costs_attribute_key = 'maxicycle/maxicycle_option/product_costs_attribute';
            $product_costs_fixed_key = 'maxicycle/maxicycle_option/product_costs_fixed';
            $use_tax_key = 'maxicycle/maxicycle_option/use_tax';
            $use_shipping_key = 'maxicycle/maxicycle_option/use_shipping';
            $order_costs_key = 'maxicycle/maxicycle_option/order_costs';
            $product_costs_type_key = 'maxicycle/maxicycle_option/product_costs_type';
            $valid_statuses_key = 'maxicycle/maxicycle_option/valid_statuses';
            $add_during_checkout_key = 'maxicycle/maxicycle_option/add_during_checkout';

            // UPDATES
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$is_enable_key', '$is_enable') ON DUPLICATE KEY UPDATE value = '$is_enable'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$api_key_key', '$api_key') ON DUPLICATE KEY UPDATE value = '$api_key'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$product_costs_attribute_key', '$product_costs_attribute') ON DUPLICATE KEY UPDATE value = '$product_costs_attribute'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$product_costs_fixed_key', '$product_costs_fixed') ON DUPLICATE KEY UPDATE value = '$product_costs_fixed'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$product_costs_type_key', '$product_costs_type') ON DUPLICATE KEY UPDATE value = '$product_costs_type'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$use_tax_key', '$use_tax') ON DUPLICATE KEY UPDATE value = '$use_tax'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$use_shipping_key', '$use_shipping') ON DUPLICATE KEY UPDATE value = '$use_shipping'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$order_costs_key', '$order_costs') ON DUPLICATE KEY UPDATE value = '$order_costs'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$valid_statuses_key', '" . implode(',', $valid_statuses) . "') ON DUPLICATE KEY UPDATE value = '" . implode(',', $valid_statuses) . "'");
            $db->query("INSERT INTO " . Mage::getSingleton('core/resource')->getTableName('core_config_data') . " (scope,scope_id,path,value) VALUES('$scope', $store_id, '$add_during_checkout_key', '$add_during_checkout') ON DUPLICATE KEY UPDATE value = '$add_during_checkout'");

            // RESET CACHE
            $cacheType = 'config';
            Mage::app()->getCacheInstance()->cleanType($cacheType);
            Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => $cacheType));
            
            // Info and redirect
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('maxicycle')->__('Your data had been updated'));
            $this->_redirect('*/*/', array('store' => $store_id));
        }
    }

}
