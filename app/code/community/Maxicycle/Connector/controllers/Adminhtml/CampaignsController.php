<?php

/**
 * Maxicycle
 *
 * @category    Maxicycle
 * @package     Maxicycle_Connector
 * @copyright   Copyright (c) 2015 (http://www.maxicycle.com)
 */
class Maxicycle_Connector_Adminhtml_CampaignsController extends Mage_Adminhtml_Controller_action {

    public function indexAction() {
        $this->loadLayout();
        $this->_addContent($this->getLayout()->createBlock('maxicycle/adminhtml_campaigns'));
        $this->renderLayout();
    }

    public function regenerateAction() {
        $campaign_id = (int) $this->getRequest()->getParam('campaign_id');
        if ($campaign_id != 0) {
            $campaign = Mage::getModel('maxicycle/campaigns')->load($campaign_id);
            if ($campaign) {
                // DB connection
                $db = Mage::getSingleton('core/resource')->getConnection('core_write');
                $resource = Mage::getSingleton('core/resource');
                $config = Mage::helper('maxicycle')->getConfiguration($campaign->getStoreId());
                // Delete old results
                $db->query("DELETE FROM " . $resource->getTableName('maxicycle/results') . " WHERE campaign_id = $campaign_id");
                // Get orders which should be in results according to date
                $orders = $db->query("SELECT entity_id FROM " . $resource->getTableName('sales_flat_order') . " WHERE maxicycle_campaign_id = $campaign_id AND created_at >= '" . $campaign->getCampaignStart() . "' AND created_at <= '" . $campaign->getResponseTimeEnd() . "'");
                foreach ($orders as $o) {
                    // Load order object
                    $order = Mage::getModel('sales/order')->load((int) $o['entity_id']);
                    // Gross profit
                    $gross_profit = 0.0000;
                    // Recount gross_profit according to module configuration
                    $order_items = $order->getAllVisibleItems();
                    $use_tax = intval($config['use_tax']);
                    $product_costs_type = $config['product_costs_type'];
                    $product_costs_attribute = $config['product_costs_attribute'];
                    $product_costs_fixed = $config['product_costs_fixed'];
                    $order_costs = floatval($config['order_costs']);

                    // Loop over all order items
                    foreach ($order_items as $item) {
                        // Load product
                        $product = Mage::getModel('catalog/product')->load($item->getProductId());
                        // Check if use product price with or without tax
                        if (!$use_tax) {
                            // With
                            if ($product_costs_type == '1') {
                                // Attribute
                                if (trim($product_costs_attribute) != '') {
                                    // Decrease product price by buy price
                                    $bp = floatval($product->getData($product_costs_attribute));
                                    $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePriceInclTax()) - $bp));
                                } else {
                                    // Use original product price
                                    $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePriceInclTax()));
                                }
                            } else {
                                // Fixed
                                if (floatval($product_costs_fixed) != 0) {
                                    // Decrease product price by percentage
                                    $fixed = 1 + floatval($product_costs_fixed) / 100.00;
                                    $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePriceInclTax()) / $fixed));
                                } else {
                                    // Use original product price
                                    $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePriceInclTax()));
                                }
                            }
                        } else {
                            // Without
                            if ($product_costs_type == '1') {
                                // Attribute
                                if (trim($product_costs_attribute) != '') {
                                    // Decrease product price by buy price
                                    $bp = floatval($product->getData($product_costs_attribute));
                                    $gross_profit += (floatval($item->getQtyOrdered()) * (floatval($item->getBasePrice() - $bp)));
                                } else {
                                    // Use original product price
                                    $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice()));
                                }
                            } else {
                                // Fixed
                                if (floatval($product_costs_fixed) != 0) {
                                    // Decrease product price by percentage
                                    $fixed = 1 + floatval($product_costs_fixed) / 100.00;
                                    $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice() / $fixed));
                                } else {
                                    // Use original product price
                                    $gross_profit += (floatval($item->getQtyOrdered()) * floatval($item->getBasePrice()));
                                }
                            }
                        }
                    }

                    // Decrease order costs
                    $gross_profit -= floatval($order_costs);

                    // Prepare data for Maxicycle results table
                    $data = array(
                        'campaign_id' => $campaign_id,
                        'order_id' => $order->getIncrementId(),
                        'maxicycle_customer_id' => $order->getMaxicycleCustomerId(),
                        'created_at' => $order->getCreatedAt(),
                        'response_to_order_id' => $order->getMaxicycleResponseToOrderId(),
                        'grand_total' => $order->getGrandTotal(),
                        'order_profit' => $gross_profit,
                        'last_order_update_date' => $order->getUpdatedAt(),
                        'campaign_order_type' => $order->getMaxicycleOrderType(),
                        'export_flag' => 0
                    );
                    
                    // Save into result table
                    Mage::getModel('maxicycle/results')->setData($data)->save();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('maxicycle')->__('Results had been generated.'));
                $this->_redirect('*/*/');
            } else {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('maxicycle')->__('Campaign not exist'));
                $this->_redirect('*/*/');
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('maxicycle')->__('Campaign not exist'));
            $this->_redirect('*/*/');
        }
    }

}
