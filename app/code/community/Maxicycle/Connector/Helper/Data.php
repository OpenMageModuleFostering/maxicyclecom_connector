<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Helper_Data extends Mage_Core_Helper_Abstract {

    public function getConfiguration($store_id) {
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

        return array(
            'is_enable' => Mage::getStoreConfig($is_enable_key, $store_id),
            'api_key' => Mage::getStoreConfig($api_key_key, $store_id),
            'product_costs_attribute' => Mage::getStoreConfig($product_costs_attribute_key, $store_id),
            'product_costs_fixed' => Mage::getStoreConfig($product_costs_fixed_key, $store_id),
            'product_costs_type' => Mage::getStoreConfig($product_costs_type_key, $store_id),
            'use_tax' => Mage::getStoreConfig($use_tax_key, $store_id),
            'use_shipping' => Mage::getStoreConfig($use_shipping_key, $store_id),
            'order_costs' => Mage::getStoreConfig($order_costs_key, $store_id),
            'valid_statuses' => explode(',', Mage::getStoreConfig($valid_statuses_key, $store_id)),
            'add_during_checkout' => Mage::getStoreConfig($add_during_checkout_key, $store_id)
        );
    }

}
